<?php
/**
 * API Endpoint for Filter Options
 * 
 * This file provides JSON data for dynamic dropdown filters in the UI.
 * It's called via AJAX from JavaScript when users select different filter options.
 * 
 * Available Actions:
 * - get_grades: Get grades for a specific school level
 * - get_subjects: Get subjects for a specific level and grade
 * - get_books: Get books for a specific level, grade, and subject
 * - get_books_browse: Get books with stock info for browse page
 * 
 * Each action returns JSON data that populates the next dropdown in the chain.
 * This creates a cascading filter effect (Level → Grade → Subject → Book).
 * 
 * @package BookShare
 * @version 1.0
 * @endpoint /api_get_filter_options.php?action=...
 */

// Include the application configuration file
require_once 'config/app.php';

// Set response header to JSON format
header('Content-Type: application/json');

// =====================================================
// GET REQUEST PARAMETERS
// =====================================================

/**
 * Extract filter parameters from the URL query string.
 * These are passed from JavaScript AJAX calls.
 */
$action = isset($_GET['action']) ? $_GET['action'] : '';          // Which data to fetch
$level = isset($_GET['level']) ? trim($_GET['level']) : '';       // School level (Primary, College, etc.)
$grade = isset($_GET['grade']) ? trim($_GET['grade']) : '';       // Grade/Year
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : ''; // Subject
$condition = isset($_GET['condition']) ? trim($_GET['condition']) : ''; // Book condition
$governorate = isset($_GET['governorate']) ? trim($_GET['governorate']) : ''; // Governorate

// Initialize result array (will be JSON encoded at the end)
$result = [];

// =====================================================
// HANDLE DIFFERENT API ACTIONS
// =====================================================

try {
    // =====================================================
    // ACTION: get_grades
    // Get distinct grades for a specific school level
    // =====================================================
    if ($action === 'get_grades') {
        // Validate that level is provided
        if (empty($level)) {
            $result['error'] = 'Level is required';
        } else {
            // Build query to get distinct grades
            $query = 'SELECT DISTINCT b.grade
                      FROM school_books b';
            $params = [$level];

            // If governorate filter is applied, join with donations
            if (!empty($governorate)) {
                $query .= '
                    JOIN donations d ON d.book_id = b.id
                    JOIN users u ON u.id = d.user_id';
            }

            $query .= ' WHERE b.level = ?';

            // Add governorate filter if specified
            if (!empty($governorate)) {
                $query .= ' AND u.governorate = ?';
                $params[] = $governorate;
            }

            $query .= ' ORDER BY b.grade';

            // Execute query and return grades as simple array
            $stmt = $connexion->prepare($query);
            $stmt->execute($params);
            $result['grades'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } 
    // =====================================================
    // ACTION: get_subjects
    // Get distinct subjects for a specific level and grade
    // =====================================================
    elseif ($action === 'get_subjects') {
        // Get distinct subjects for a specific level and grade
        if (empty($level) || empty($grade)) {
            $result['error'] = 'Level and grade are required';
        } else {
            $query = 'SELECT DISTINCT b.subject
                      FROM school_books b';
            $params = [$level, $grade];

            if (!empty($governorate)) {
                $query .= '
                    JOIN donations d ON d.book_id = b.id
                    JOIN users u ON u.id = d.user_id';
            }

            $query .= ' WHERE b.level = ? AND b.grade = ?';

            if (!empty($governorate)) {
                $query .= ' AND u.governorate = ?';
                $params[] = $governorate;
            }

            $query .= ' ORDER BY b.subject';

            $stmt = $connexion->prepare($query);
            $stmt->execute($params);
            $result['subjects'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } 
    elseif ($action === 'get_books') {
        // Get books based on filters
        if (empty($level) || empty($grade) || empty($subject)) {
            $result['error'] = 'Level, grade, and subject are required';
        } else {
            $query = 'SELECT id, book_name FROM school_books WHERE level = ? AND grade = ? AND subject = ? ORDER BY book_name';
            $stmt = $connexion->prepare($query);
            $stmt->execute([$level, $grade, $subject]);
            $result['books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    elseif ($action === 'get_books_browse') {
        // Get books with stock info based on all filters (for browse page)
        $query = 'SELECT b.id, b.book_name, b.subject, b.grade,
                         COALESCE(SUM(CASE WHEN i.condition_state = ? THEN i.stock ELSE 0 END), 0) as stock_count
                  FROM school_books b
                  LEFT JOIN inventory i ON b.id = i.book_id';
        
        $conditions = [$condition];
        
        if (!empty($level)) {
            $query .= ' AND b.level = ?';
            $conditions[] = $level;
        }
        if (!empty($grade)) {
            $query .= ' AND b.grade = ?';
            $conditions[] = $grade;
        }
        if (!empty($subject)) {
            $query .= ' AND b.subject = ?';
            $conditions[] = $subject;
        }
        
        $query .= ' GROUP BY b.id ORDER BY b.book_name';
        
        $stmt = $connexion->prepare($query);
        $stmt->execute($conditions);
        $result['books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else {
        $result['error'] = 'Invalid action';
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
?>
