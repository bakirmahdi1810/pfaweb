<?php
/**
 * Request Books Page
 * 
 * This page allows logged-in users to request books from the platform.
 * When a user requests a book:
 * 1. A request record is created in the 'requests' table
 * 2. The request status is set to 'pending'
 * 3. The matching engine is triggered to find available donations
 * 
 * Features:
 * - Book selection dropdown
 * - Minimum acceptable condition selection
 * - Automatic matching with available inventory
 * - Redirect to matches page on success
 * 
 * @package BookShare
 * @version 1.0
 * @requires login User must be logged in to request
 */

// Include the application configuration file
require_once 'config/app.php';

/**
 * Security Check: Ensure user is logged in.
 */
checkLogin();

// Initialize error message variable
$error = '';

// =====================================================
// HANDLE REQUEST FORM SUBMISSION
// =====================================================

/**
 * Process request form when user submits.
 * 
 * This block handles the book request process:
 * 1. Validate book and condition are selected
 * 2. Verify user and book exist in database
 * 3. Create request record with 'pending' status
 * 4. Trigger matching engine to find donations
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form inputs
    $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;  // Convert to integer for security
    $target_state = isset($_POST['target_state']) ? trim($_POST['target_state']) : '';  // Minimum acceptable condition

    // =====================================================
    // VALIDATION
    // =====================================================
    
    // Check if book and condition are selected
    if ($book_id === 0 || empty($target_state)) {
        $error = 'Please select a book and minimum acceptable condition.';
    } else {
        // Verify the user still exists in the database
        $stmtUserCheck = $connexion->prepare('SELECT id FROM users WHERE id = ?');
        $stmtUserCheck->execute([$_SESSION['user_id']]);
        
        // If user doesn't exist, log them out and redirect to login
        if ($stmtUserCheck->rowCount() === 0) {
            $_SESSION['user_id'] = null;
            unset($_SESSION['user_id']);
            header('Location: ./login.php');
            exit();
        }
        
        // Verify the book exists in the catalog
        $stmt = $connexion->prepare('SELECT id FROM school_books WHERE id = ?');
        $stmt->execute([$book_id]);

        if ($stmt->rowCount() === 0) {
            $error = 'Book not found.';
        } else {
            // =====================================================
            // PROCESS REQUEST
            // =====================================================
            
            try {
                // Step 1: Create request record in the database
                // Status is 'pending' - will be changed to 'matched' if a match is found
                $stmtRequest = $connexion->prepare(
                    'INSERT INTO requests (user_id, book_id, target_state, status) VALUES (?, ?, ?, ?)'
                );
                $stmtRequest->execute([$_SESSION['user_id'], $book_id, $target_state, 'pending']);

                // Get the newly created request ID
                $request_id = $connexion->lastInsertId();

                // Step 2: Trigger the matching engine
                // This will check if any donations match this request
                require_once 'match_engine.php';
                matchPendingRequests($connexion);

                // Set success message and redirect to matches page
                $_SESSION['message'] = 'Your request has been submitted. We\'ll notify you if a match is found!';
                $_SESSION['message_type'] = 'success';
                header('Location: ./my_matches.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Error processing request: ' . $e->getMessage();
            }
        }
    }
}

// Get all books
$stmtBooks = $connexion->query(
    'SELECT b.id, b.book_name, b.level, b.grade, b.subject, COALESCE(SUM(i.stock), 0) as total_stock 
     FROM school_books b 
     LEFT JOIN inventory i ON b.id = i.book_id 
     GROUP BY b.id 
     ORDER BY b.level, b.grade, b.subject ASC'
);
$books = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Request Books - BookShare';
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="lni lni-search"></i> Request a Book
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="lni lni-alert-circle"></i> <?php echo escape($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" data-validate="true">
                        <fieldset>
                            <legend>Search for a Book</legend>

                            <div class="row mb-3">
                                <label for="level_filter" class="col-sm-2 col-form-label">School Type</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="level_filter">
                                        <option value="">-- Select School Type --</option>
                                        <option value="Primary">Primary School</option>
                                        <option value="College">College</option>
                                        <option value="High School">High School</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="grade_filter" class="col-sm-2 col-form-label">Grade/Year</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="grade_filter">
                                        <option value="">-- Select Grade --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="subject_filter" class="col-sm-2 col-form-label">Subject</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="subject_filter">
                                        <option value="">-- Select Subject --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="book_id" class="col-sm-2 col-form-label">Book Title</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="book_id" name="book_id" required>
                                        <option value="">-- Select a Book --</option>
                                        <?php foreach ($books as $book): ?>
                                            <option value="<?php echo $book['id']; ?>" data-level="<?php echo escape($book['level']); ?>" data-grade="<?php echo escape($book['grade']); ?>" data-subject="<?php echo escape($book['subject']); ?>">
                                                <?php echo escape($book['book_name'] . ' (' . $book['subject'] . ' - ' . $book['grade'] . ')'); ?>
                                                (<?php echo $book['total_stock']; ?> available)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (count($books) === 0): ?>
                                        <small class="text-muted d-block mt-2">No books available yet.</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="target_state" class="col-sm-2 col-form-label">Minimum Condition</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="target_state" name="target_state" required>
                                        <option value="">-- Select Acceptable Condition --</option>
                                        <option value="Damaged">Accept any condition (Including damaged)</option>
                                        <option value="Acceptable">Fair condition or better</option>
                                        <option value="Good">Good condition or better</option>
                                        <option value="New">New condition only</option>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <div class="alert alert-info">
                            <i class="lni lni-info-circle"></i>
                            <strong>How it works:</strong> When you submit your request, our system will automatically search for matching books. If found, both you and the donor will be notified with contact information to arrange the exchange.
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-10 offset-sm-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="lni lni-check"></i> Submit Request
                                </button>
                                <a href="./dashboard.php" class="btn btn-outline-secondary">
                                    <i class="lni lni-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Available Books -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="lni lni-book"></i> Available Books
                </div>
                <div class="card-body">
                    <?php if (count($books) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Book Name</th>
                                        <th>School Type</th>
                                        <th>Grade</th>
                                        <th>Subject</th>
                                        <th>Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $book): ?>
                                        <tr class="book-row <?php echo $book['total_stock'] === 0 ? 'text-muted' : ''; ?>" data-level="<?php echo escape($book['level']); ?>" data-grade="<?php echo escape($book['grade']); ?>" data-subject="<?php echo escape($book['subject']); ?>">
                                            <td><?php echo escape($book['book_name']); ?></td>
                                            <td><?php echo escape($book['level']); ?></td>
                                            <td><?php echo escape($book['grade']); ?></td>
                                            <td><?php echo escape($book['subject']); ?></td>
                                            <td>
                                                <?php if ($book['total_stock'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $book['total_stock']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No books available yet. Check back soon!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const levelFilter = document.getElementById('level_filter');
    const gradeFilter = document.getElementById('grade_filter');
    const subjectFilter = document.getElementById('subject_filter');
    const bookSelect = document.getElementById('book_id');

    // Update grades when school type changes
    levelFilter.addEventListener('change', async function() {
        const level = this.value;
        gradeFilter.innerHTML = '<option value="">-- Select Grade --</option>';
        subjectFilter.innerHTML = '<option value="">-- Select Subject --</option>';
        
        if (!level) {
            filterBooks();
            return;
        }

        try {
            const response = await fetch('api_get_filter_options.php?action=get_grades&level=' + encodeURIComponent(level));
            const data = await response.json();
            if (data.grades && Array.isArray(data.grades)) {
                data.grades.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item;
                    option.textContent = item;
                    gradeFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching grades:', error);
        }
        filterBooks();
    });

    // Update subjects when grade changes
    gradeFilter.addEventListener('change', async function() {
        const level = levelFilter.value;
        const grade = this.value;
        subjectFilter.innerHTML = '<option value="">-- Select Subject --</option>';
        
        if (!level || !grade) {
            filterBooks();
            return;
        }

        try {
            const response = await fetch('api_get_filter_options.php?action=get_subjects&level=' + encodeURIComponent(level) + '&grade=' + encodeURIComponent(grade));
            const data = await response.json();
            if (data.subjects && Array.isArray(data.subjects)) {
                data.subjects.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item;
                    option.textContent = item;
                    subjectFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching subjects:', error);
        }
        filterBooks();
    });

    // Filter books and table when any filter changes
    subjectFilter.addEventListener('change', filterBooks);
    levelFilter.addEventListener('change', function() {
        filterBooks();
    });

    function filterBooks() {
        const selectedLevel = levelFilter.value;
        const selectedGrade = gradeFilter.value;
        const selectedSubject = subjectFilter.value;
        let firstVisibleBookOption = null;

        // Filter book options
        Array.from(bookSelect.options).forEach(option => {
            if (option.value === '') return;
            const optionLevel = option.getAttribute('data-level');
            const optionGrade = option.getAttribute('data-grade');
            const optionSubject = option.getAttribute('data-subject');

            let show = true;
            if (selectedLevel && optionLevel !== selectedLevel) show = false;
            if (selectedGrade && optionGrade !== selectedGrade) show = false;
            if (selectedSubject && optionSubject !== selectedSubject) show = false;

            option.style.display = show ? '' : 'none';
            if (show && firstVisibleBookOption === null) {
                firstVisibleBookOption = option;
            }
        });

        // Auto-select book once filters are fully selected.
        if (selectedLevel && selectedGrade && selectedSubject && firstVisibleBookOption) {
            bookSelect.value = firstVisibleBookOption.value;
        } else {
            bookSelect.value = '';
        }

        // Filter table rows
        document.querySelectorAll('.book-row').forEach(row => {
            const rowLevel = row.getAttribute('data-level');
            const rowGrade = row.getAttribute('data-grade');
            const rowSubject = row.getAttribute('data-subject');

            let show = true;
            if (selectedLevel && rowLevel !== selectedLevel) show = false;
            if (selectedGrade && rowGrade !== selectedGrade) show = false;
            if (selectedSubject && rowSubject !== selectedSubject) show = false;

            row.style.display = show ? '' : 'none';
        });
    }
});
</script>

<?php include 'layout/footer.php'; ?>
