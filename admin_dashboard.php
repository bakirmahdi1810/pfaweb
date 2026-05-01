<?php
/**
 * Admin Dashboard - Platform Statistics & Management
 * 
 * This is the main administration page for the BookShare platform.
 * It provides comprehensive statistics and management tools for administrators.
 * 
 * Features:
 * - Platform-wide statistics (users, donations, requests, matches)
 * - Book catalog management (view, add, edit, delete books)
 * - Inventory overview
 * - Delete book functionality with cascade deletion
 * 
 * Access Control:
 * Only users with role='admin' can access this page.
 * The checkAdmin() function verifies this before allowing access.
 * 
 * @package BookShare
 * @version 1.0
 * @requires admin Only administrators can access this page
 */

// Include the application configuration file
require_once 'config/app.php';

/**
 * Security Check: Ensure user is logged in AND is an administrator.
 * 
 * checkAdmin() first calls checkLogin() to ensure user is logged in,
 * then verifies the user's role is 'admin'. Regular users are redirected
 * to the dashboard.
 */
checkAdmin();

// =====================================================
// HANDLE BOOK DELETION ACTION
// =====================================================

/**
 * Process book deletion when admin submits the delete form.
 * 
 * This handles the deletion of books from the catalog.
 * IMPORTANT: This performs cascade deletion - it removes all related
 * records in other tables before deleting the book itself:
 * 1. inventory - Stock records for this book
 * 2. donations - Donation records mentioning this book
 * 3. requests - Request records for this book
 * 4. matches - Match records involving this book
 * 5. school_books - The book itself
 * 
 * This ensures data integrity and prevents orphaned records.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_book') {
    // Get the book ID from the form and ensure it's a valid integer
    $book_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($book_id > 0) {
        try {
            // Step 1: Delete inventory records for this book
            $stmt = $connexion->prepare('DELETE FROM inventory WHERE book_id = ?');
            $stmt->execute([$book_id]);

            // Step 2: Delete donation records for this book
            $stmt = $connexion->prepare('DELETE FROM donations WHERE book_id = ?');
            $stmt->execute([$book_id]);

            // Step 3: Delete request records for this book
            $stmt = $connexion->prepare('DELETE FROM requests WHERE book_id = ?');
            $stmt->execute([$book_id]);

            // Step 4: Delete match records for this book
            $stmt = $connexion->prepare('DELETE FROM matches WHERE book_id = ?');
            $stmt->execute([$book_id]);

            // Step 5: Finally, delete the book itself
            $stmt = $connexion->prepare('DELETE FROM school_books WHERE id = ?');
            $stmt->execute([$book_id]);

            // Set success message to display to admin
            $_SESSION['message'] = 'Book deleted successfully!';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            // Set error message if deletion fails
            $_SESSION['message'] = 'Error deleting book: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    // Refresh the page to show updated list
    header('Location: ./admin_dashboard.php');
    exit();
}

// =====================================================
// GET PLATFORM STATISTICS
// =====================================================

/**
 * Fetch various platform statistics for display on the dashboard.
 * These statistics help administrators understand platform usage.
 */
$stats = [];

// Stat 1: Total regular users (role = 'user')
$stmt = $connexion->query('SELECT COUNT(*) as count FROM users WHERE role = "user"');
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Stat 2: Total admin users (role = 'admin')
$stmt = $connexion->query('SELECT COUNT(*) as count FROM users WHERE role = "admin"');
$stats['total_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Stat 3: Total matches made (successful exchanges)
$stmt = $connexion->query('SELECT COUNT(*) as count FROM matches');
$stats['total_matches'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Stat 4: Pending matches (not yet completed)
$stmt = $connexion->query('SELECT COUNT(*) as count FROM matches WHERE status = "pending"');
$stats['pending_matches'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total books in system
$stmt = $connexion->query('SELECT COUNT(*) as count FROM school_books');
$stats['total_books'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total donations
$stmt = $connexion->query('SELECT COUNT(*) as count FROM donations');
$stats['total_donations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total requests
$stmt = $connexion->query('SELECT COUNT(*) as count FROM requests');
$stats['total_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total inventory stock
$stmt = $connexion->query('SELECT COALESCE(SUM(stock), 0) as total_stock FROM inventory');
$stats['total_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_stock'];

// Get books with inventory for display
$query = '
    SELECT 
        b.*,
        COALESCE(SUM(i.stock), 0) as total_stock,
        COUNT(DISTINCT d.id) as donation_count
    FROM school_books b
    LEFT JOIN inventory i ON b.id = i.book_id
    LEFT JOIN donations d ON b.id = d.book_id
    GROUP BY b.id
    ORDER BY b.level, b.grade, b.subject ASC
';

$stmt = $connexion->prepare($query);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Admin Dashboard - BookShare';
include 'layout/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-5">
        <div class="col-12">
            <h1 class="section-title">
                <i class="lni lni-stats-up"></i> Admin Dashboard
            </h1>
            <p class="lead">Welcome, <?php echo escape($_SESSION['prenom'] ?? 'Admin'); ?> <?php echo escape($_SESSION['nom'] ?? ''); ?>!</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-5">
        <div class="col-md-6 col-lg-2 col-xl-2 mb-3">
            <div class="stat-card">
                <div class="stat-icon matches">
                    <i class="lni lni-handshake"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_matches']; ?></h3>
                    <p>Total Matches</p>
                    <small><?php echo $stats['pending_matches']; ?> pending</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-2 col-xl-2 mb-3">
            <div class="stat-card">
                <div class="stat-icon books">
                    <i class="lni lni-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_books']; ?></h3>
                    <p>Books in Catalog</p>
                    <small><?php echo $stats['total_stock']; ?> in stock</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-2 col-xl-2 mb-3">
            <div class="stat-card">
                <div class="stat-icon donations">
                    <i class="lni lni-gift"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_donations']; ?></h3>
                    <p>Donations Made</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-2 col-xl-2 mb-3">
            <div class="stat-card">
                <div class="stat-icon requests">
                    <i class="lni lni-search"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_requests']; ?></h3>
                    <p>Books Requested</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-2 col-xl-2 mb-3">
            <div class="stat-card">
                <div class="stat-icon admin">
                    <i class="lni lni-lock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_admins']; ?></h3>
                    <p>Admin Users</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Catalog Management Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="section-title mt-5">
                <i class="lni lni-list"></i> Book Catalog Management
            </h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="./add_book.php" class="btn btn-success">
                <i class="lni lni-plus"></i> Add New Book
            </a>
        </div>
    </div>

    <!-- Search Filter -->
    <div class="row mb-4">
        <div class="col-md-6">
            <input type="text" id="searchInput" class="form-control" 
                   placeholder="Search by book name, subject, or grade...">
        </div>
    </div>

    <!-- Books Table -->
    <?php if (count($books) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover" id="catalogTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Book Name</th>
                        <th>Level</th>
                        <th>Grade</th>
                        <th>Subject</th>
                        <th>Stock</th>
                        <th>Donations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?php echo $book['id']; ?></td>
                            <td><?php echo escape($book['book_name']); ?></td>
                            <td><?php echo escape($book['level']); ?></td>
                            <td><?php echo escape($book['grade']); ?></td>
                            <td><?php echo escape($book['subject']); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $book['total_stock']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $book['donation_count']; ?></span>
                            </td>
                            <td>
                                <a href="./edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-sm btn-warning btn-icon">
                                    <i class="lni lni-pencil"></i> Modify
                                </a>
                                <form method="POST" action="./admin_dashboard.php" class="d-inline">
                                    <input type="hidden" name="action" value="delete_book">
                                    <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger btn-icon" 
                                            onclick="return confirm('Are you sure you want to delete this book and all related records?');">
                                        <i class="lni lni-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="lni lni-book"></i>
            <h3>No books in catalog</h3>
            <p><a href="./add_book.php" class="btn btn-primary mt-3">Manage books</a></p>
        </div>
    <?php endif; ?>
</div>

<style>
    .stat-card {
        display: flex;
        align-items: center;
        padding: 20px;
        border-radius: 8px;
        background: linear-gradient(135deg, #f5f5f5, #ffffff);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        border-left: 4px solid #007bff;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        margin-right: 20px;
        flex-shrink: 0;
    }

    .stat-icon.users {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-icon.matches {
        background: linear-gradient(135deg, #f093fb, #f5576c);
    }

    .stat-icon.books {
        background: linear-gradient(135deg, #4facfe, #00f2fe);
    }

    .stat-icon.donations {
        background: linear-gradient(135deg, #43e97b, #38f9d7);
    }

    .stat-icon.requests {
        background: linear-gradient(135deg, #fa709a, #fee140);
    }

    .stat-icon.admin {
        background: linear-gradient(135deg, #30cfd0, #330867);
    }

    .stat-content h3 {
        margin: 0;
        font-size: 28px;
        font-weight: bold;
        color: #333;
    }

    .stat-content p {
        margin: 5px 0 0 0;
        color: #666;
        font-size: 14px;
    }

    .stat-content small {
        display: block;
        color: #999;
        font-size: 12px;
        margin-top: 3px;
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .section-title {
        color: #333;
        font-weight: bold;
        margin-bottom: 20px;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setupTableFilter('searchInput', 'catalogTable');
});

function setupTableFilter(inputId, tableId) {
    const searchInput = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
}
</script>

<?php include 'layout/footer.php'; ?>
