<?php
/**
 * Donate Books Page
 * 
 * This page allows logged-in users to donate books to the platform.
 * When a user donates a book:
 * 1. A donation record is created in the 'donations' table
 * 2. The inventory stock is updated (incremented)
 * 3. The matching engine is triggered to find matching requests
 * 
 * Features:
 * - Book selection dropdown
 * - Condition state selection (New, Good, Acceptable, Damaged)
 * - Automatic inventory management
 * - Automatic request matching
 * 
 * @package BookShare
 * @version 1.0
 * @requires login User must be logged in to donate
 */

// Include the application configuration file
require_once 'config/app.php';

// Include the matching engine (for automatic matching after donation)
require_once 'match_engine.php';

/**
 * Security Check: Ensure user is logged in.
 */
checkLogin();

// Initialize message variables
$error = '';     // Error message to display
$success = '';   // Success message to display

// =====================================================
// HANDLE DONATION FORM SUBMISSION
// =====================================================

/**
 * Process donation form when user submits.
 * 
 * This block handles the book donation process:
 * 1. Validate book and condition are selected
 * 2. Verify user and book exist in database
 * 3. Create donation record
 * 4. Update inventory stock
 * 5. Trigger matching engine
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form inputs
    $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;  // Convert to integer for security
    $condition_state = isset($_POST['condition_state']) ? trim($_POST['condition_state']) : '';

    // =====================================================
    // VALIDATION
    // =====================================================
    
    // Check if book and condition are selected
    if ($book_id === 0 || empty($condition_state)) {
        $error = 'Please select a book and condition state.';
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
            // PROCESS DONATION
            // =====================================================
            
            try {
                // Step 1: Create donation record in the database
                $stmtDonate = $connexion->prepare(
                    'INSERT INTO donations (user_id, book_id, condition_state) VALUES (?, ?, ?)'
                );
                $stmtDonate->execute([$_SESSION['user_id'], $book_id, $condition_state]);

                // Step 2: Update inventory stock
                // First check if inventory record exists for this book+condition
                $stmtCheckInv = $connexion->prepare(
                    'SELECT id FROM inventory WHERE book_id = ? AND condition_state = ?'
                );
                $stmtCheckInv->execute([$book_id, $condition_state]);
                
                if ($stmtCheckInv->rowCount() > 0) {
                    // Inventory record exists, increment stock by 1
                    $stmtInv = $connexion->prepare(
                        'UPDATE inventory SET stock = stock + 1 WHERE book_id = ? AND condition_state = ?'
                    );
                    $stmtInv->execute([$book_id, $condition_state]);
                } else {
                    // Record doesn't exist, create it
                    $stmtInv = $connexion->prepare(
                        'INSERT INTO inventory (book_id, condition_state, stock) VALUES (?, ?, 1)'
                    );
                    $stmtInv->execute([$book_id, $condition_state]);
                }

                // Trigger matching engine to find requests for this book
                matchPendingRequests($connexion);

                $_SESSION['message'] = 'Thank you! Your book has been added to our inventory.';
                $_SESSION['message_type'] = 'success';
                header('Location: ./donate.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Error processing donation: ' . $e->getMessage();
            }
        }
    }
}

// Get all books
$stmtBooks = $connexion->query(
    'SELECT id, level, grade, subject, book_name FROM school_books ORDER BY level, grade, subject, book_name ASC'
);
$books = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Donate Books - BookShare';
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="lni lni-gift"></i> Donate a Book
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
                            <legend>Book Information</legend>

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
                                    <select class="form-select" id="grade_filter" disabled>
                                        <option value="">-- Select Grade --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="subject_filter" class="col-sm-2 col-form-label">Subject</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="subject_filter" disabled>
                                        <option value="">-- Select Subject --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="book_id" class="col-sm-2 col-form-label">Book Title</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="book_id" name="book_id" required disabled>
                                        <option value="">-- Select a Book --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="condition_state" class="col-sm-2 col-form-label">Book Condition</label>
                                <div class="col-sm-10">
                                    <select class="form-select" id="condition_state" name="condition_state" required>
                                        <option value="">-- Select Condition --</option>
                                        <option value="New">New (Never read, perfect condition)</option>
                                        <option value="Good">Good (Minor wear, fully readable)</option>
                                        <option value="Acceptable">Acceptable (Some wear, fully readable)</option>
                                        <option value="Damaged">Damaged (Significant wear, still readable)</option>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <div class="alert alert-info">
                            <i class="lni lni-info-circle"></i>
                            <strong>Thank you for donating!</strong> Your book will help another reader discover their next favorite story.
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-10 offset-sm-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="lni lni-check"></i> Confirm Donation
                                </button>
                                <a href="./dashboard.php" class="btn btn-outline-secondary">
                                    <i class="lni lni-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Donation Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="lni lni-help"></i> Condition Guidelines
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><span class="condition-badge condition-new">New</span></h5>
                            <p>Book appears unread with perfect binding. No marks or wear.</p>
                        </div>
                        <div class="col-md-6">
                            <h5><span class="condition-badge condition-good">Good</span></h5>
                            <p>Minor wear and handling marks, but in solid condition with clean pages.</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h5><span class="condition-badge condition-acceptable">Acceptable</span></h5>
                            <p>Noticeable wear to cover and edges, highlight marks, but text is clear.</p>
                        </div>
                        <div class="col-md-6">
                            <h5><span class="condition-badge condition-damaged">Damaged</span></h5>
                            <p>Heavy wear, stains, or damage, but pages are intact and readable.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cascading filter logic
const levelFilter = document.getElementById('level_filter');
const gradeFilter = document.getElementById('grade_filter');
const subjectFilter = document.getElementById('subject_filter');
const bookIdSelect = document.getElementById('book_id');

levelFilter.addEventListener('change', async function() {
    gradeFilter.innerHTML = '<option value="">-- Select Grade --</option>';
    subjectFilter.innerHTML = '<option value="">-- Select Subject --</option>';
    bookIdSelect.innerHTML = '<option value="">-- Select a Book --</option>';
    
    if (!this.value) {
        gradeFilter.disabled = true;
        subjectFilter.disabled = true;
        bookIdSelect.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`./api_get_filter_options.php?action=get_grades&level=${encodeURIComponent(this.value)}`);
        const data = await response.json();
        
        if (data.grades) {
            data.grades.forEach(grade => {
                const option = document.createElement('option');
                option.value = grade;
                option.textContent = grade;
                gradeFilter.appendChild(option);
            });
            gradeFilter.disabled = false;
        }
    } catch (error) {
        console.error('Error fetching grades:', error);
    }
});

gradeFilter.addEventListener('change', async function() {
    subjectFilter.innerHTML = '<option value="">-- Select Subject --</option>';
    bookIdSelect.innerHTML = '<option value="">-- Select a Book --</option>';
    
    if (!this.value || !levelFilter.value) {
        subjectFilter.disabled = true;
        bookIdSelect.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`./api_get_filter_options.php?action=get_subjects&level=${encodeURIComponent(levelFilter.value)}&grade=${encodeURIComponent(this.value)}`);
        const data = await response.json();
        
        if (data.subjects) {
            data.subjects.forEach(subject => {
                const option = document.createElement('option');
                option.value = subject;
                option.textContent = subject;
                subjectFilter.appendChild(option);
            });
            subjectFilter.disabled = false;
        }
    } catch (error) {
        console.error('Error fetching subjects:', error);
    }
});

subjectFilter.addEventListener('change', async function() {
    bookIdSelect.innerHTML = '<option value="">-- Select a Book --</option>';
    
    if (!this.value || !gradeFilter.value || !levelFilter.value) {
        bookIdSelect.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`./api_get_filter_options.php?action=get_books&level=${encodeURIComponent(levelFilter.value)}&grade=${encodeURIComponent(gradeFilter.value)}&subject=${encodeURIComponent(this.value)}`);
        const data = await response.json();
        
        if (data.books) {
            let firstBookId = '';
            data.books.forEach(book => {
                const option = document.createElement('option');
                option.value = book.id;
                option.textContent = book.book_name;
                bookIdSelect.appendChild(option);
                if (!firstBookId) {
                    firstBookId = String(book.id);
                }
            });
            if (firstBookId) {
                bookIdSelect.value = firstBookId;
                bookIdSelect.disabled = false;
            } else {
                bookIdSelect.disabled = true;
            }
        } else {
            bookIdSelect.disabled = true;
        }
    } catch (error) {
        console.error('Error fetching books:', error);
        bookIdSelect.disabled = true;
    }
});
</script>

<?php include 'layout/footer.php'; ?>
