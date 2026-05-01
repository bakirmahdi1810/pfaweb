<?php
/**
 * User Dashboard Page
 * 
 * This is the main landing page for logged-in users. It provides:
 * - Welcome message with user's name
 * - Quick action cards for navigation
 * - User statistics (donations, requests, matches)
 * - Recent activity display
 * - Unread match notifications
 * 
 * The dashboard shows personalized data based on the logged-in user.
 * Only authenticated users can access this page (checked via checkLogin()).
 * 
 * @package BookShare
 * @version 1.0
 * @requires login User must be logged in to access
 */

// Include the application configuration file
require_once 'config/app.php';

/**
 * Security Check: Ensure user is logged in.
 * 
 * This function (defined in config/app.php) checks if the user
 * has a valid session. If not, it redirects to login.php.
 */
checkLogin();

// =====================================================
// CACHE CONTROL HEADERS
// =====================================================

/**
 * Prevent browser caching of dashboard data.
 * 
 * These headers ensure the browser always fetches fresh data
 * when the user returns to the dashboard, especially after
 * viewing or interacting with matches.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// =====================================================
// GET UNREAD MATCHES COUNT
// =====================================================

/**
 * Check if database supports per-user read tracking.
 * 
 * ensureMatchesReadSupport() ensures the matches table has
 * the required columns for tracking read status per user.
 */
$matches_support_read = ensureMatchesReadSupport($connexion);
$unread_matches = 0;

/**
 * Count unread matches for the current user.
 * 
 * A match is "unread" if either:
 * - The user is the donor and hasn't read it (donor_is_read = 0)
 * - The user is the requester and hasn't read it (requester_is_read = 0)
 * 
 * This count is displayed as a badge on the My Matches card.
 */
if ($matches_support_read) {
    try {
        $stmtMatches = $connexion->prepare(
            'SELECT COUNT(*) as count
             FROM matches
             WHERE (donor_id = ? AND donor_is_read = 0)
                OR (requester_id = ? AND requester_is_read = 0)'
        );
        $stmtMatches->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $unread_matches = (int)($stmtMatches->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Throwable $e) {
        // If query fails, default to 0
        $unread_matches = 0;
    }
}

// Set page title for the header
$pageTitle = 'Dashboard - BookShare';

// Include the header layout (navigation bar)
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row mb-5">
        <div class="col-12">
            <h1 class="section-title">
                <i class="lni lni-dashboard"></i> Your Dashboard
            </h1>
            <p class="lead">Welcome, <?php echo escape($_SESSION['prenom'] ?? 'User'); ?> <?php echo escape($_SESSION['nom'] ?? ''); ?>!</p>
        </div>
    </div>

    <!-- Quick Action Cards -->
    <div class="row mb-5">
        <div class="col-md-6 col-lg-4 mb-3">
            <a href="./browse_catalog.php" class="text-decoration-none">
                <div class="dashboard-card dashboard-card-modern">
                    <div>
                        <i class="lni lni-book"></i>
                        <h3>Books in Stock</h3>
                        <p class="small">Browse available books by area</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4 mb-3">
            <a href="./donate.php" class="text-decoration-none">
                <div class="dashboard-card">
                    <div>
                        <i class="lni lni-gift"></i>
                        <h3>Donate Books</h3>
                        <p class="small">Share your books with others</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4 mb-3">
            <a href="./request.php" class="text-decoration-none">
                <div class="dashboard-card" style="background: linear-gradient(135deg, #f97316, #ea580c);">
                    <div>
                        <i class="lni lni-search"></i>
                        <h3>Request Books</h3>
                        <p class="small">Find books you want to read</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-lg-4 mb-3">
            <a href="./my_matches.php" class="text-decoration-none">
                <div class="dashboard-card" style="position: relative; background: linear-gradient(135deg, #16a34a, #0891b2);">
                    <?php if ($unread_matches > 0): ?>
                        <span class="unread-match-badge unread-match-badge-lg"
                              style="position:absolute;top:12px;right:12px;background:#dc3545;color:#fff;border-radius:50%;min-width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-weight:700;z-index:2;">
                            <?php echo $unread_matches; ?>
                        </span>
                    <?php endif; ?>
                    <div>
                        <i class="lni lni-handshake"></i>
                        <h3>My Matches</h3>
                        <p class="small">View your book exchanges</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4 mb-3">
            <a href="./account_settings.php" class="text-decoration-none">
                <div class="dashboard-card" style="background: linear-gradient(135deg, #ec4899, #a855f7);">
                    <div>
                        <i class="lni lni-cog"></i>
                        <h3>Account Settings</h3>
                        <p class="small">Update your profile</p>
                    </div>
                </div>
            </a>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <a href="./admin_dashboard.php" class="text-decoration-none">
                <div class="dashboard-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                    <div>
                        <i class="lni lni-stats-up"></i>
                        <h3>Admin Dashboard</h3>
                        <p class="small">Manage catalog and platform stats</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="section-title"><i class="lni lni-bar-chart"></i> Your Activity</h2>
        </div>
    </div>

    <div class="row">
        <?php
        // Get user stats
        $stmtDonated = $connexion->prepare(
            'SELECT COUNT(*) as count FROM donations WHERE user_id = ?'
        );
        $stmtDonated->execute([$_SESSION['user_id']]);
        $donated = $stmtDonated->fetch(PDO::FETCH_ASSOC)['count'];

        $stmtRequested = $connexion->prepare(
            'SELECT COUNT(*) as count FROM requests WHERE user_id = ?'
        );
        $stmtRequested->execute([$_SESSION['user_id']]);
        $requested = $stmtRequested->fetch(PDO::FETCH_ASSOC)['count'];

        $stmtPending = $connexion->prepare(
            'SELECT COUNT(*) as count FROM requests WHERE user_id = ? AND status = ?'
        );
        $stmtPending->execute([$_SESSION['user_id'], 'pending']);
        $pending = $stmtPending->fetch(PDO::FETCH_ASSOC)['count'];

        $stmtMatches = $connexion->prepare(
            'SELECT COUNT(*) as count FROM matches WHERE donor_id = ? OR requester_id = ?'
        );
        $stmtMatches->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $matches = $stmtMatches->fetch(PDO::FETCH_ASSOC)['count'];
        ?>
        
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <i class="lni lni-gift" style="font-size: 2.5rem; color: #4a69bd;"></i>
                    <h3 class="card-title mt-3"><?php echo $donated; ?></h3>
                    <p class="card-text text-muted">Books Donated</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <i class="lni lni-search" style="font-size: 2.5rem; color: #f08a5d;"></i>
                    <h3 class="card-title mt-3"><?php echo $requested; ?></h3>
                    <p class="card-text text-muted">Books Requested</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <i class="lni lni-hourglass" style="font-size: 2.5rem; color: #f39c12;"></i>
                    <h3 class="card-title mt-3"><?php echo $pending; ?></h3>
                    <p class="card-text text-muted">Pending Requests</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <i class="lni lni-handshake" style="font-size: 2.5rem; color: #2ecc71;"></i>
                    <h3 class="card-title mt-3"><?php echo $matches; ?></h3>
                    <p class="card-text text-muted">Successful Matches</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="section-title"><i class="lni lni-history"></i> Recent Activity</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="lni lni-gift"></i> Recent Donations
                </div>
                <div class="card-body">
                    <?php
                    $stmtRecent = $connexion->prepare(
                        'SELECT d.*, b.book_name as title FROM donations d 
                         JOIN school_books b ON d.book_id = b.id 
                         WHERE d.user_id = ? 
                         ORDER BY d.donated_at DESC LIMIT 5'
                    );
                    $stmtRecent->execute([$_SESSION['user_id']]);
                    $recent_donations = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

                    if (count($recent_donations) > 0):
                    ?>
                        <ul class="list-unstyled">
                            <?php foreach ($recent_donations as $donation): ?>
                                <li class="mb-3 pb-3 border-bottom">
                                    <strong><?php echo escape($donation['title']); ?></strong><br>
                                    <small class="text-muted">
                                        <span class="condition-badge condition-<?php echo strtolower($donation['condition_state']); ?>">
                                            <?php echo escape($donation['condition_state']); ?>
                                        </span>
                                        on <?php echo date('M d, Y', strtotime($donation['donated_at'])); ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No recent donations</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="lni lni-search"></i> Recent Requests
                </div>
                <div class="card-body">
                    <?php
                    $stmtRecentReq = $connexion->prepare(
                        'SELECT r.*, b.book_name as title FROM requests r 
                         JOIN school_books b ON r.book_id = b.id 
                         WHERE r.user_id = ? 
                         ORDER BY r.requested_at DESC LIMIT 5'
                    );
                    $stmtRecentReq->execute([$_SESSION['user_id']]);
                    $recent_requests = $stmtRecentReq->fetchAll(PDO::FETCH_ASSOC);

                    if (count($recent_requests) > 0):
                    ?>
                        <ul class="list-unstyled">
                            <?php foreach ($recent_requests as $request): ?>
                                <li class="mb-3 pb-3 border-bottom">
                                    <strong><?php echo escape($request['title']); ?></strong><br>
                                    <small class="text-muted">
                                        <span class="status-label <?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                        on <?php echo date('M d, Y', strtotime($request['requested_at'])); ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No recent requests</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
