<?php
/**
 * My Matches Page
 * 
 * This page displays all book exchanges (matches) that involve the current user.
 * A user can be either:
 * - A donor (someone who gave a book)
 * - A requester (someone who received a book)
 * 
 * Features:
 * - List all matches for the user
 * - Show contact information for the other party
 * - Mark matches as read when viewed
 * - Display book details and match status
 * - Unread match badges
 * 
 * @package BookShare
 * @version 1.0
 * @requires login User must be logged in to view matches
 */

// Include the application configuration file
require_once 'config/app.php';

/**
 * Security Check: Ensure user is logged in.
 */
checkLogin();

// =====================================================
// CACHE CONTROL HEADERS
// =====================================================

/**
 * Prevent browser caching to ensure fresh data.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// =====================================================
// CHECK DATABASE SUPPORT FOR READ TRACKING
// =====================================================

/**
 * Ensure the database supports per-user read tracking.
 * This adds columns if they don't exist.
 */
$matches_support_read = ensureMatchesReadSupport($connexion);

// =====================================================
// MARK MATCHES AS READ
// =====================================================

/**
 * When user opens this page, mark all their matches as read.
 * 
 * This provides a better user experience by automatically
 * clearing the "unread" badges when the user views their matches.
 * Both donor and requester sides are updated.
 */
if ($matches_support_read) {
    // Mark all matches where user is donor as read
    $stmtMarkDonorRead = $connexion->prepare(
        'UPDATE matches SET donor_is_read = 1 WHERE donor_id = ? AND donor_is_read = 0'
    );
    $stmtMarkDonorRead->execute([$_SESSION['user_id']]);

    // Mark all matches where user is requester as read
    $stmtMarkRequesterRead = $connexion->prepare(
        'UPDATE matches SET requester_is_read = 1 WHERE requester_id = ? AND requester_is_read = 0'
    );
    $stmtMarkRequesterRead->execute([$_SESSION['user_id']]);
}

// =====================================================
// GET ALL MATCHES FOR CURRENT USER
// =====================================================

/**
 * Build query to fetch all matches involving this user.
 * 
 * The query joins:
 * - matches table (the main match records)
 * - school_books table (book details)
 * - users table twice (donor info and requester info)
 * - requests table (original request status)
 * 
 * It filters to only show matches where the user is either
 * the donor or the requester.
 */
$query = 'SELECT m.*, b.book_name as title, b.subject as author,
                 d.id as donor_id, d.nom as donor_nom, d.prenom as donor_prenom, d.email as donor_email, d.tel as donor_tel,
                 r.id as requester_id, r.nom as requester_nom, r.prenom as requester_prenom, r.email as requester_email, r.tel as requester_tel,
                 req.status as request_status';

// Add read status based on database support
if (!$matches_support_read) {
    // If no support, default to read
    $query .= ', 1 as is_read';
} else {
    // Compute per-user read status for display ("New" badge)
    $query .= ', CASE
                    WHEN m.donor_id = :uid THEN m.donor_is_read
                    WHEN m.requester_id = :uid THEN m.requester_is_read
                    ELSE 1
                END as is_read';
}

// Build the FROM and JOIN clauses
$query .= '
     FROM matches m
     JOIN school_books b ON m.book_id = b.id
     JOIN users d ON m.donor_id = d.id
     JOIN users r ON m.requester_id = r.id
     JOIN requests req ON m.request_id = req.id
     WHERE m.donor_id = :uid_filter OR m.requester_id = :uid_filter';

// Order by read status (unread first), then by date (newest first)
if ($matches_support_read) {
    $query .= ' ORDER BY is_read ASC, m.matched_at DESC';
} else {
    $query .= ' ORDER BY m.matched_at DESC';
}

// Prepare and execute the query
$stmt = $connexion->prepare($query);
$stmt->bindValue(':uid_filter', (int)$_SESSION['user_id'], PDO::PARAM_INT);
if ($matches_support_read) {
    $stmt->bindValue(':uid', (int)$_SESSION['user_id'], PDO::PARAM_INT);
}
$stmt->execute();
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Matches - BookShare';
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="section-title">
                <i class="lni lni-handshake"></i> My Book Exchanges
            </h1>
        </div>
    </div>

    <?php if (count($matches) > 0): ?>
        <div class="row">
            <?php foreach ($matches as $match): ?>
                <div class="col-md-6 mb-4">
                    <div class="match-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">
                                    <i class="lni lni-book"></i>
                                    <?php echo escape($match['title']); ?>
                                </h5>
                                <small class="text-muted">by <?php echo escape($match['author']); ?></small>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <span class="status-label matched">
                                    <i class="lni lni-check-circle"></i> Matched
                                </span>
                                <?php if (!$match['is_read']): ?>
                                    <span class="badge bg-danger">New</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Book Condition -->
                        <div class="mb-3">
                            <label class="small fw-bold text-primary">Book Condition:</label>
                            <span class="condition-badge condition-<?php echo strtolower($match['condition_given']); ?>">
                                <?php echo escape($match['condition_given']); ?>
                            </span>
                        </div>

                        <!-- My Role -->
                        <?php if ((int)$match['donor_id'] === (int)$_SESSION['user_id']): ?>
                            <!-- I'm the Donor -->
                            <div class="mb-3">
                                <div class="label">
                                    <i class="lni lni-info-circle"></i> Requester Information
                                </div>
                                <div class="contact-info">
                                    <p class="mb-2">
                                        <strong><?php echo escape($match['requester_prenom'] . ' ' . $match['requester_nom']); ?></strong>
                                    </p>
                                    <div class="mb-2">
                                        <i class="lni lni-envelope"></i>
                                        <a href="mailto:<?php echo escape($match['requester_email']); ?>">
                                            <?php echo escape($match['requester_email']); ?>
                                        </a>
                                    </div>
                                    <div>
                                        <i class="lni lni-phone"></i>
                                        <a href="tel:<?php echo escape($match['requester_tel']); ?>">
                                            <?php echo escape($match['requester_tel']); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- I'm the Requester -->
                            <div class="mb-3">
                                <div class="label">
                                    <i class="lni lni-info-circle"></i> Donor Information
                                </div>
                                <div class="contact-info">
                                    <p class="mb-2">
                                        <strong><?php echo escape($match['donor_prenom'] . ' ' . $match['donor_nom']); ?></strong>
                                    </p>
                                    <div class="mb-2">
                                        <i class="lni lni-envelope"></i>
                                        <a href="mailto:<?php echo escape($match['donor_email']); ?>">
                                            <?php echo escape($match['donor_email']); ?>
                                        </a>
                                    </div>
                                    <div>
                                        <i class="lni lni-phone"></i>
                                        <a href="tel:<?php echo escape($match['donor_tel']); ?>">
                                            <?php echo escape($match['donor_tel']); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Matched Date -->
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <div class="text-muted small">
                                <i class="lni lni-timer"></i>
                                Matched on
                                <?php
                                if (!empty($match['matched_at'])) {
                                    $matchedAt = new DateTime($match['matched_at']);
                                    echo $matchedAt->format('M d, Y \a\t H:i');
                                } else {
                                    echo 'Date not available';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="lni lni-handshake"></i>
            <h3>No Matches Yet</h3>
            <p>You don't have any book exchanges yet.</p>
            <div class="mt-3">
                <a href="./donate.php" class="btn btn-primary me-2">
                    <i class="lni lni-gift"></i> Donate Books
                </a>
                <a href="./request.php" class="btn btn-outline-primary">
                    <i class="lni lni-search"></i> Request Books
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'layout/footer.php'; ?>
