<?php
/**
 * Application Configuration File
 * 
 * This file serves as the central configuration and bootstrap file for the entire application.
 * It includes:
 * - Database connection setup (PDO)
 * - Session management
 * - Authentication helper functions
 * - Tunisian governorates data and helpers
 * - Match read tracking support
 * 
 * @package BookShare
 * @version 1.0
 */

// =====================================================
// SECTION 1: SESSION MANAGEMENT
// =====================================================

/**
 * Start PHP session for user authentication and data persistence.
 * Session stores user login state across page requests.
 */
session_start();

// =====================================================
// SECTION 2: AUTHENTICATION HELPER FUNCTIONS
// =====================================================

/**
 * Check if user is logged in.
 * 
 * This function verifies that the current user has a valid session.
 * If not logged in, the user is redirected to the login page.
 * This is used as a protection mechanism for authenticated pages.
 * 
 * @return void Redirects to login.php if user is not authenticated
 * @uses $_SESSION['user_id'] - Session variable storing user ID
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ./login.php');
        exit();
    }
}

/**
 * Check if logged-in user has admin privileges.
 * 
 * This function first verifies the user is logged in, then checks
 * if their role is set to 'admin'. Regular users are redirected
 * back to the dashboard.
 * 
 * @return void Redirects to appropriate page based on authorization
 * @uses $_SESSION['role'] - Session variable storing user role
 * @uses checkLogin() - Validates user is logged in first
 */
function checkAdmin() {
    checkLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ./dashboard.php');
        exit();
    }
}

/**
 * Log out the current user.
 * 
 * Destroys the PHP session and redirects the user to the dashboard.
 * This is called when user clicks the logout button.
 * 
 * @return void Destroys session and redirects to dashboard
 * @uses session_destroy() - Ends the current session
 */
function logout() {
    session_destroy();
    header('Location: ./dashboard.php');
    exit();
}

/**
 * Escape HTML special characters to prevent XSS attacks.
 * 
 * This function sanitizes user input by converting special characters
 * to their HTML entities. This prevents Cross-Site Scripting (XSS) attacks
 * when displaying user-submitted content.
 * 
 * @param string $string The input string to sanitize
 * @return string Sanitized string safe for HTML output
 * @uses htmlspecialchars() - PHP function for HTML entity encoding
 * @uses ENT_QUOTES - Flag to encode both single and double quotes
 * @uses UTF-8 - Character encoding
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// SECTION 3: DATABASE CONFIGURATION
// =====================================================

/**
 * Database connection constants.
 * These define the connection parameters for MySQL database.
 */
define('DB_HOST', 'localhost');      // Database server hostname
define('DB_NAME', 'donation');       // Database name
define('DB_USER', 'root');           // Database username
define('DB_PASS', '');               // Database password (empty for XAMPP)
define('DB_CHARSET', 'utf8mb4');     // Character encoding

/**
 * Create PDO database connection.
 * 
 * PDO (PHP Data Objects) provides a consistent interface for database access.
 * This establishes connection to MySQL database using defined constants.
 * 
 * @global PDO $connexion - Global database connection object
 * @throws PDOException - If connection fails, script terminates with error
 * @uses PDO - PHP Data Objects extension
 * @uses DSN - Data Source Name format for database connection
 */
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $connexion = new PDO($dsn, DB_USER, DB_PASS);
    $connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données: ' . $e->getMessage());
}

// =====================================================
// SECTION 4: TUNISIAN GOVERNORATES HELPERS
// =====================================================

/**
 * Get list of all Tunisian governorates.
 * 
 * Tunisia is divided into 24 governorates (administrative regions).
 * This function returns an array of all governorate names used
 * for user registration and filtering.
 * 
 * @return array Array of 24 governorate names
 * @example ['Tunis', 'Ariana', 'Ben Arous', ...]
 */
function getTunisianGovernorates() {
    return [
        'Tunis',
        'Ariana',
        'Ben Arous',
        'Manouba',
        'Nabeul',
        'Zaghouan',
        'Bizerte',
        'Béja',
        'Jendouba',
        'Le Kef',
        'Siliana',
        'Sousse',
        'Monastir',
        'Mahdia',
        'Kairouan',
        'Kasserine',
        'Sidi Bouzid',
        'Sfax',
        'Gafsa',
        'Tozeur',
        'Kébili',
        'Gabès',
        'Médenine',
        'Tataouine'
    ];
}

/**
 * Generate HTML select options for governorates.
 * 
 * Creates an HTML dropdown (<select>) with all Tunisian governorates
 * as options. The currently selected governorate is marked as selected.
 * 
 * @param string $selected The governorate name to pre-select (optional)
 * @return string HTML <option> elements for select dropdown
 * @uses getTunisianGovernorates() - Gets list of governorates
 * @uses escape() - Sanitizes output for XSS prevention
 */
function getGovernorateOptions($selected = '') {
    $governorates = getTunisianGovernorates();
    $options = '<option value="">-- Select Your Governorate --</option>';
    
    foreach ($governorates as $gov) {
        $isSelected = ($selected === $gov) ? ' selected' : '';
        $options .= '<option value="' . escape($gov) . '"' . $isSelected . '>' . escape($gov) . '</option>';
    }
    
    return $options;
}

/**
 * Validate if a governorate name is valid.
 * 
 * Checks if the given governorate exists in the official list
 * of Tunisian governorates. Used for form validation.
 * 
 * @param string $governorate The governorate name to validate
 * @return bool True if valid, false otherwise
 * @uses getTunisianGovernorates() - Gets list of valid governorates
 */
function isValidGovernorate($governorate) {
    return in_array($governorate, getTunisianGovernorates());
}

// =====================================================
// SECTION 5: MATCHES READ TRACKING (PER-USER)
// =====================================================

/**
 * Ensure database supports per-user match read tracking.
 * 
 * This function checks if the 'matches' table has the required columns
 * for tracking which matches have been read by each user (donor and requester).
 * If columns are missing, it creates them automatically.
 * 
 * This enables showing "New" badges for unread matches per user.
 * 
 * @param PDO $connexion Database connection object
 * @return bool True if support is available, false on failure
 * @throws RuntimeException If columns cannot be created
 * @global Ensures donor_is_read and requester_is_read columns exist
 */
function ensureMatchesReadSupport(PDO $connexion): bool
{
    $supported = false;

    try {
        // Check if donor_is_read column exists
        $stmtHasColumn = $connexion->prepare(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'matches'
               AND COLUMN_NAME = ?"
        );

        $stmtHasColumn->execute(['donor_is_read']);
        $hasDonor = ((int)($stmtHasColumn->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0)) > 0;

        // Check if requester_is_read column exists
        $stmtHasColumn->execute(['requester_is_read']);
        $hasRequester = ((int)($stmtHasColumn->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0)) > 0;

        $addedAny = !$hasDonor || !$hasRequester;

        // Add missing columns if needed
        if (!$hasDonor) {
            $connexion->exec("ALTER TABLE matches ADD COLUMN donor_is_read BOOLEAN NOT NULL DEFAULT 0");
        }
        if (!$hasRequester) {
            $connexion->exec("ALTER TABLE matches ADD COLUMN requester_is_read BOOLEAN NOT NULL DEFAULT 0");
        }

        // Migrate data from legacy is_read column if it exists
        $stmtHasColumn->execute(['is_read']);
        $hasLegacy = ((int)($stmtHasColumn->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0)) > 0;
        if ($addedAny && $hasLegacy) {
            $connexion->exec("UPDATE matches SET donor_is_read = is_read, requester_is_read = is_read");
        } elseif ($addedAny && !$hasLegacy) {
            $connexion->exec("UPDATE matches SET donor_is_read = 1, requester_is_read = 1");
        }

        // Verify columns were created successfully
        $stmtHasColumn->execute(['donor_is_read']);
        $hasDonor = ((int)($stmtHasColumn->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0)) > 0;
        $stmtHasColumn->execute(['requester_is_read']);
        $hasRequester = ((int)($stmtHasColumn->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0)) > 0;

        $supported = $hasDonor && $hasRequester;
        if (!$supported) {
            throw new RuntimeException('Per-user read tracking columns missing after migration.');
        }
    } catch (Throwable $e) {
        // Fallback check using SHOW COLUMNS
        try {
            $fallbackDonor = $connexion->prepare("SHOW COLUMNS FROM matches LIKE 'donor_is_read'");
            $fallbackDonor->execute();
            $fallbackRequester = $connexion->prepare("SHOW COLUMNS FROM matches LIKE 'requester_is_read'");
            $fallbackRequester->execute();
            $supported = ($fallbackDonor->rowCount() > 0) && ($fallbackRequester->rowCount() > 0);
        } catch (Throwable $e2) {
            $supported = false;
        }

        error_log('ensureMatchesReadSupport failed: ' . $e->getMessage());
    }

    return $supported;
}

