<?php
/**
 * Edit Book - Standalone Page
 * Admin only page to modify an existing book.
 */

require_once 'config/app.php';

checkAdmin();

$error = '';
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch book
$stmt = $connexion->prepare('SELECT * FROM school_books WHERE id = ?');
$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    $_SESSION['message'] = 'Book not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ./admin_dashboard.php');
    exit();
}

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
            // Duplicate check (exclude current id)
            $stmt = $connexion->prepare(
                'SELECT id FROM school_books WHERE level = ? AND grade = ? AND subject = ? AND book_name = ? AND id <> ?'
            );
            $stmt->execute([$level, $grade, $subject, $book_name, $book_id]);
            if ($stmt->rowCount() > 0) {
                $error = 'Another book with the same details already exists.';
            } else {
                $stmt = $connexion->prepare(
                    'UPDATE school_books SET level = ?, grade = ?, subject = ?, book_name = ?, language = ? WHERE id = ?'
                );
                $stmt->execute([$level, $grade, $subject, $book_name, $language, $book_id]);
                $_SESSION['message'] = 'Book updated successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: ./admin_dashboard.php');
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Book - Admin';
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="section-title">
                <i class="lni lni-pencil"></i> Edit Book
            </h1>
            <p class="text-muted mb-0">Modify book details in the school catalog.</p>
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
                            <option value="Primary" <?php echo $book['level'] === 'Primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="Middle" <?php echo $book['level'] === 'Middle' ? 'selected' : ''; ?>>Middle</option>
                            <option value="Secondary" <?php echo $book['level'] === 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="grade" class="form-label">Grade *</label>
                        <input type="text" name="grade" id="grade" class="form-control" required
                               value="<?php echo escape($book['grade']); ?>"
                               placeholder="e.g., 1st, 2nd, 3rd">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" name="subject" id="subject" class="form-control" required
                               value="<?php echo escape($book['subject']); ?>"
                               placeholder="e.g., Mathematics, English">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="language" class="form-label">Language</label>
                        <select name="language" id="language" class="form-control">
                            <option value="">Select Language</option>
                            <option value="Arabic" <?php echo $book['language'] === 'Arabic' ? 'selected' : ''; ?>>Arabic</option>
                            <option value="French" <?php echo $book['language'] === 'French' ? 'selected' : ''; ?>>French</option>
                            <option value="English" <?php echo $book['language'] === 'English' ? 'selected' : ''; ?>>English</option>
                            <option value="Mixed" <?php echo $book['language'] === 'Mixed' ? 'selected' : ''; ?>>Mixed</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="book_name" class="form-label">Book Name *</label>
                    <input type="text" name="book_name" id="book_name" class="form-control" required
                           value="<?php echo escape($book['book_name']); ?>"
                           placeholder="Enter the book name">
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="./admin_dashboard.php" class="btn btn-secondary me-md-2">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="lni lni-save"></i> Update Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>