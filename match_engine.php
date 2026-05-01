<?php
/**
 * Matching Engine
 * 
 * This file contains the core logic for automatically matching book requests
 * with available book donations. The matching algorithm considers:
 * - Book availability in inventory
 * - Requested condition vs available condition
 * - Geographic proximity (same governorate)
 * - Donor availability (not the same as requester)
 * 
 * The system uses a condition hierarchy: New > Good > Acceptable > Damaged
 * A request can be fulfilled with a book of equal or better condition.
 * 
 * @package BookShare
 * @version 1.0
 * @author BookShare Team
 */

// =====================================================
// MAIN MATCHING FUNCTION
// =====================================================

/**
 * Match pending requests with available inventory.
 * 
 * This is the core function of the matching engine. It runs automatically
 * whenever a new donation or request is made. The algorithm works as follows:
 * 
 * 1. Fetch all pending requests with their requester's governorate
 * 2. For each request, check the condition hierarchy (New > Good > Acceptable > Damaged)
 * 3. Find available inventory matching or exceeding the target condition
 * 4. Find a donor in the same governorate who donated that condition
 * 5. Create a match record linking donor, requester, and book
 * 6. Update the request status to 'matched'
 * 7. Decrease the inventory stock
 * 
 * The matching is done in real-time when users donate or request books.
 * 
 * @param PDO $connexion Database connection object
 * @return void This function executes database operations but returns no value
 * @uses $connexion->prepare() - Prepared statements for SQL injection prevention
 * @uses $connexion->execute() - Execute prepared statements
 * @global Processes all pending requests in the database
 * 
 * @example
 * // Call this function after a donation or request is made
 * require_once 'match_engine.php';
 * matchPendingRequests($connexion);
 */
function matchPendingRequests($connexion) {
    try {
        // Step 1: Get all pending requests with requester's governorate
        // Only pending requests can be matched
        $stmtRequests = $connexion->prepare(
            'SELECT r.id, r.user_id, r.book_id, r.target_state, u.governorate 
             FROM requests r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.status = ?'
        );
        $stmtRequests->execute(['pending']);
        $pending_requests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

        // Step 2: Process each pending request
        foreach ($pending_requests as $request) {
            // Condition hierarchy: New > Good > Acceptable > Damaged
            // A request for "Good" can be fulfilled with "Good" or "New"
            $condition_order = ['New', 'Good', 'Acceptable', 'Damaged'];
            $target_index = array_search($request['target_state'], $condition_order);

            if ($target_index === false) {
                continue; // Invalid condition, skip this request
            }

            // Step 3: Search for available inventory starting from target condition
            // and work up to better conditions (New is best)
            for ($i = $target_index; $i >= 0; $i--) {
                $condition = $condition_order[$i];

                // Check if this book is available in this condition
                $stmtInv = $connexion->prepare(
                    'SELECT id, stock FROM inventory 
                     WHERE book_id = ? AND condition_state = ? AND stock > 0'
                );
                $stmtInv->execute([$request['book_id'], $condition]);
                $inventory = $stmtInv->fetch(PDO::FETCH_ASSOC);

                // Step 4: If inventory found, find a donor in the same governorate
                if ($inventory && $inventory['stock'] > 0) {
                    // Find a donor who donated this book in this condition
                    // and is in the same governorate as the requester
                    $stmtDonor = $connexion->prepare(
                        'SELECT DISTINCT d.user_id FROM donations d
                         JOIN users u ON d.user_id = u.id
                         WHERE d.book_id = ? AND d.condition_state = ? AND u.governorate = ?
                         ORDER BY d.donated_at DESC LIMIT 1'
                    );
                    $stmtDonor->execute([$request['book_id'], $condition, $request['governorate']]);
                    $donor = $stmtDonor->fetch(PDO::FETCH_ASSOC);

                    // Step 5: Create match if donor is different from requester
                    if ($donor && $donor['user_id'] != $request['user_id']) {
                        // Insert match record
                        $stmtMatch = $connexion->prepare(
                            'INSERT INTO matches (request_id, donor_id, requester_id, book_id, condition_given, status, donor_is_read, requester_is_read) 
                             VALUES (?, ?, ?, ?, ?, ?, 0, 0)'
                        );
                        $stmtMatch->execute([
                            $request['id'],
                            $donor['user_id'],
                            $request['user_id'],
                            $request['book_id'],
                            $condition,
                            'pending'
                        ]);

                        // Step 6: Update request status to matched
                        $stmtUpdateReq = $connexion->prepare(
                            'UPDATE requests SET status = ? WHERE id = ?'
                        );
                        $stmtUpdateReq->execute(['matched', $request['id']]);

                        // Step 7: Decrease inventory stock
                        $stmtUpdateInv = $connexion->prepare(
                            'UPDATE inventory SET stock = stock - 1 WHERE id = ?'
                        );
                        $stmtUpdateInv->execute([$inventory['id']]);

                        // Match found, stop searching for this request
                        break;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        // Log error but don't expose details to users
        error_log('Matching Engine Error: ' . $e->getMessage());
    }
}

// =====================================================
// HELPER FUNCTION: GET MATCH DETAILS
// =====================================================

/**
 * Get detailed information about a specific match.
 * 
 * This function retrieves complete information about a match including:
 * - Book details (title, author/subject)
 * - Donor information (name, email, phone)
 * - Requester information (name, email, phone)
 * - Match status and condition
 * 
 * Used when displaying match details to users.
 * 
 * @param PDO $connexion Database connection object
 * @param int $match_id The unique identifier of the match
 * @return array|false Match details as associative array, or false if not found
 * @uses Joins: matches, school_books, users (twice for donor and requester)
 * 
 * @example
 * $matchDetails = getMatchDetails($connexion, 5);
 * echo $matchDetails['title']; // Book title
 * echo $matchDetails['donor_nom']; // Donor last name
 */
function getMatchDetails($connexion, $match_id) {
    $stmt = $connexion->prepare(
        'SELECT m.*, b.book_name as title, b.subject as author, 
                d.id as donor_id, d.nom as donor_nom, d.prenom as donor_prenom, d.email as donor_email, d.tel as donor_tel,
                r.id as requester_id, r.nom as requester_nom, r.prenom as requester_prenom, r.email as requester_email, r.tel as requester_tel
         FROM matches m
         JOIN school_books b ON m.book_id = b.id
         JOIN users d ON m.donor_id = d.id
         JOIN users r ON m.requester_id = r.id
         WHERE m.id = ?'
    );
    $stmt->execute([$match_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
