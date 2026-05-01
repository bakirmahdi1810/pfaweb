<?php
/**
 * Add New Book - Standalone Page
 * Admin only page to add a new book to the catalog.
 */

require_once 'config/app.php';

checkAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $level = isset($_POST['level']) ? trim((string)$_POST['level']) : '';
    $grade = isset($_POST['grade']) ? trim((string)$_POST['grade']) : '';
    $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
    $book_name = isset($_POST['book_name']) ? trim((string)$_POST['book_name']) : '';
    $language = isset($_POST['language']) ? trim((string)$_POST['language']) : '';

    if (empty($level) || empty($grade) || empty($subject) || empty($book_name)) {
        $error = 'Level, Grade, Subject, and Book Name are required.';
    } else {
        try {
            // Duplicate check
            $stmt = $connexion->prepare(
                'SELECT id FROM school_books WHERE level = ? AND grade = ? AND subject = ? AND book_name = ?'
            );
            $stmt->execute([$level, $grade, $subject, $book_name]);
            if ($stmt->rowCount() > 0) {
                $error = 'This book already exists in the catalog.';
            } else {
                $stmt = $connexion->prepare(
                    'INSERT INTO school_books (level, grade, subject, book_name, language) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$level, $grade, $subject, $book_name, $language]);
                $_SESSION['message'] = 'Book added successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: ./admin_dashboard.php');
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add New Book - Admin';
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="section-title">
                <i class="lni lni-plus"></i> Add New Book
            </h1>
            <p class="text-muted mb-0">Add a new book to the school catalog.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="./admin_dashboard.php" class="btn btn-secondary">
                <i class="lni lni-arrow-left"></i> Back to Admin Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="lni lni-close-circle"></i> <?php echo escape($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="level" class="form-label">Level *</label>
                        <select name="level" id="level" class="form-control" required>
                            <option value="">Select Level</option>
                            <option value="Primary">Primary</option>
                            <option value="Middle">Middle</option>
                            <option value="Secondary">Secondary</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="grade" class="form-label">Grade *</label>
                        <input type="text" name="grade" id="grade" class="form-control" required
                               placeholder="e.g., 1st, 2nd, 3rd">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" name="subject" id="subject" class="form-control" required
                               placeholder="e.g., Mathematics, English">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="language" class="form-label">Language</label>
                        <select name="language" id="language" class="form-control">
                            <option value="">Select Language</option>
                            <option value="Arabic">Arabic</option>
                            <option value="French">French</option>
                            <option value="English">English</option>
                            <option value="Mixed">Mixed</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="book_name" class="form-label">Book Name *</label>
                    <input type="text" name="book_name" id="book_name" class="form-control" required
                           placeholder="Enter the book name">
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="./admin_dashboard.php" class="btn btn-secondary me-md-2">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="lni lni-save"></i> Add Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>