<?php
/**
 * Browse Catalog - View all available books
 * Accessible to both logged-in and non-logged-in users
 */

require_once 'config/app.php';

$selected_governorate = isset($_GET['governorate']) ? trim($_GET['governorate']) : '';

// Get all books with inventory and available donor governorates
$query = '
    SELECT 
        b.id,
        b.level,
        b.grade,
        b.section,
        b.subject,
        b.book_name,
        b.language,
        COALESCE(SUM(CASE WHEN condition_state = "New" THEN stock ELSE 0 END), 0) as stock_new,
        COALESCE(SUM(CASE WHEN condition_state = "Good" THEN stock ELSE 0 END), 0) as stock_good,
        COALESCE(SUM(CASE WHEN condition_state = "Acceptable" THEN stock ELSE 0 END), 0) as stock_acceptable,
        COALESCE(SUM(CASE WHEN condition_state = "Damaged" THEN stock ELSE 0 END), 0) as stock_damaged,
        COALESCE(SUM(stock), 0) as total_stock,
        COALESCE(govs.governorates, "") as governorates
    FROM school_books b
    LEFT JOIN inventory i ON b.id = i.book_id
    LEFT JOIN (
        SELECT d.book_id, GROUP_CONCAT(DISTINCT u.governorate SEPARATOR "|") as governorates
        FROM donations d
        JOIN users u ON d.user_id = u.id
        WHERE u.governorate IS NOT NULL AND u.governorate <> ""
        GROUP BY d.book_id
    ) govs ON govs.book_id = b.id
    WHERE 1 = 1
';

$params = [];
if ($selected_governorate !== '') {
    $query .= '
    AND EXISTS (
        SELECT 1 FROM donations d2
        JOIN users u2 ON d2.user_id = u2.id
        WHERE d2.book_id = b.id AND u2.governorate = ?
    )';
    $params[] = $selected_governorate;
}

$query .= '
    GROUP BY b.id, govs.governorates
    ORDER BY b.level, b.grade, b.subject ASC
';

$stmt = $connexion->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Browse Catalog - BookShare';
include 'layout/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="section-title">
                <i class="lni lni-book"></i> Browse Books Catalog
            </h1>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="row mb-4 g-3 align-items-end">
        <div class="col-md-2">
            <label for="level_filter_browse" class="form-label">School Type</label>
            <select class="form-select" id="level_filter_browse">
                <option value="">All Types</option>
                <option value="Primary">Primary School</option>
                <option value="College">College</option>
                <option value="High School">High School</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="grade_filter_browse" class="form-label">Grade/Year</label>
            <select class="form-select" id="grade_filter_browse">
                <option value="">All Grades</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="subject_filter_browse" class="form-label">Subject</label>
            <select class="form-select" id="subject_filter_browse">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="condition_filter_browse" class="form-label">Book Condition</label>
            <select class="form-select" id="condition_filter_browse">
                <option value="">All Conditions</option>
                <option value="New">New</option>
                <option value="Good">Good</option>
                <option value="Acceptable">Acceptable</option>
                <option value="Damaged">Damaged</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="governorate_filter_browse" class="form-label">Governorate</label>
            <select class="form-select" id="governorate_filter_browse" name="governorate">
                <option value="">All Governorates</option>
                <?php
                    $governorates = getTunisianGovernorates();
                    foreach ($governorates as $gov) {
                        $selected = $selected_governorate === $gov ? ' selected' : '';
                        echo '<option value="' . escape($gov) . '"' . $selected . '>' . escape($gov) . '</option>';
                    }
                ?>
            </select>
        </div>
    </form>

    <!-- Books Table -->
    <?php if (count($books) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover" id="booksTable">
                <thead>
                    <tr>
                        <th>Book Name</th>
                        <th>Level</th>
                        <th>Grade</th>
                        <th>Subject</th>
                        <th>New</th>
                        <th>Good</th>
                        <th>Acceptable</th>
                        <th>Damaged</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr class="book-row" 
                            data-level="<?php echo escape($book['level']); ?>"
                            data-grade="<?php echo escape($book['grade']); ?>"
                            data-subject="<?php echo escape($book['subject']); ?>"
                            data-stock-new="<?php echo $book['stock_new']; ?>"
                            data-stock-good="<?php echo $book['stock_good']; ?>"
                            data-stock-acceptable="<?php echo $book['stock_acceptable']; ?>"
                            data-stock-damaged="<?php echo $book['stock_damaged']; ?>"
                            data-governorates="<?php echo escape($book['governorates']); ?>">
                            <td><?php echo escape($book['book_name']); ?></td>
                            <td><?php echo escape($book['level']); ?></td>
                            <td><?php echo escape($book['grade']); ?></td>
                            <td><?php echo escape($book['subject']); ?></td>
                            <td>
                                <?php if ($book['stock_new'] > 0): ?>
                                    <span class="condition-badge condition-new"><?php echo $book['stock_new']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($book['stock_good'] > 0): ?>
                                    <span class="condition-badge condition-good"><?php echo $book['stock_good']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($book['stock_acceptable'] > 0): ?>
                                    <span class="condition-badge condition-acceptable"><?php echo $book['stock_acceptable']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($book['stock_damaged'] > 0): ?>
                                    <span class="condition-badge condition-damaged"><?php echo $book['stock_damaged']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $book['total_stock']; ?></strong>
                            </td>
                            <td>
                                <?php if ($book['total_stock'] > 0): ?>
                                    <a href="./request.php?book_id=<?php echo $book['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="lni lni-search"></i> Request
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Out of Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="lni lni-book"></i>
            <h3>No books available yet</h3>
            <p>The catalog is currently empty. Check back soon!</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const levelFilter = document.getElementById('level_filter_browse');
    const gradeFilter = document.getElementById('grade_filter_browse');
    const subjectFilter = document.getElementById('subject_filter_browse');
    const conditionFilter = document.getElementById('condition_filter_browse');
    const governorateFilter = document.getElementById('governorate_filter_browse');
    const allBooks = document.querySelectorAll('.book-row');
    
    function getGovernorateQueryParam() {
        return governorateFilter.value
            ? `&governorate=${encodeURIComponent(governorateFilter.value)}`
            : '';
    }

    // Populate grade filter based on level selection
    levelFilter.addEventListener('change', async function() {
        gradeFilter.innerHTML = '<option value="">All Grades</option>';
        subjectFilter.innerHTML = '<option value="">All Subjects</option>';
        
        if (!this.value) {
            filterBooks();
            return;
        }
        
        try {
            const response = await fetch(`./api_get_filter_options.php?action=get_grades&level=${encodeURIComponent(this.value)}${getGovernorateQueryParam()}`);
            const data = await response.json();
            
            if (data.grades) {
                data.grades.forEach(grade => {
                    const option = document.createElement('option');
                    option.value = grade;
                    option.textContent = grade;
                    gradeFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching grades:', error);
        }
        filterBooks();
    });

    // Populate subject filter based on level and grade selection
    gradeFilter.addEventListener('change', async function() {
        subjectFilter.innerHTML = '<option value="">All Subjects</option>';
        
        if (!this.value || !levelFilter.value) {
            filterBooks();
            return;
        }
        
        try {
            const response = await fetch(`./api_get_filter_options.php?action=get_subjects&level=${encodeURIComponent(levelFilter.value)}&grade=${encodeURIComponent(this.value)}${getGovernorateQueryParam()}`);
            const data = await response.json();
            
            if (data.subjects) {
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject;
                    subjectFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching subjects:', error);
        }
        filterBooks();
    });

    // Filter books when any filter changes
    function filterBooks() {
        const selectedLevel = levelFilter.value;
        const selectedGrade = gradeFilter.value;
        const selectedSubject = subjectFilter.value;
        const selectedCondition = conditionFilter.value;
        const selectedGovernorate = governorateFilter.value;

        allBooks.forEach(row => {
            const rowLevel = row.dataset.level;
            const rowGrade = row.dataset.grade;
            const rowSubject = row.dataset.subject;
            const rowGovernorates = (row.dataset.governorates || '')
                .split('|')
                .map(item => item.trim())
                .filter(Boolean);
            
            let hasStock = false;
            if (selectedCondition === 'New') {
                hasStock = parseInt(row.dataset.stockNew) > 0;
            } else if (selectedCondition === 'Good') {
                hasStock = parseInt(row.dataset.stockGood) > 0;
            } else if (selectedCondition === 'Acceptable') {
                hasStock = parseInt(row.dataset.stockAcceptable) > 0;
            } else if (selectedCondition === 'Damaged') {
                hasStock = parseInt(row.dataset.stockDamaged) > 0;
            } else {
                hasStock = true; // Show all if no condition selected
            }

            const matchesLevel = !selectedLevel || rowLevel === selectedLevel;
            const matchesGrade = !selectedGrade || rowGrade === selectedGrade;
            const matchesSubject = !selectedSubject || rowSubject === selectedSubject;
            const matchesGovernorate = !selectedGovernorate || rowGovernorates.includes(selectedGovernorate);

            const shouldShow = matchesLevel && matchesGrade && matchesSubject && matchesGovernorate && hasStock;
            row.style.display = shouldShow ? '' : 'none';
        });
    }

    subjectFilter.addEventListener('change', filterBooks);
    conditionFilter.addEventListener('change', filterBooks);
    governorateFilter.addEventListener('change', function() {
        // Governorate impacts available grade/subject options.
        gradeFilter.value = '';
        subjectFilter.value = '';
        if (levelFilter.value) {
            levelFilter.dispatchEvent(new Event('change'));
        } else {
            filterBooks();
        }
    });
});
</script>

<?php include 'layout/footer.php'; ?>
