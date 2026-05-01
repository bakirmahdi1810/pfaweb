<?php
/**
 * User Logout Page
 * 
 * This script handles user logout by destroying the session
 * and redirecting to the dashboard.
 * 
 * How it works:
 * 1. Include the config file (starts session if not started)
 * 2. Destroy the session (clears all session data)
 * 3. Redirect to dashboard
 * 
 * @package BookShare
 * @version 1.0
 */

// Include the application configuration file
// This also ensures session is started
require_once 'config/app.php';

// =====================================================
// LOGOUT PROCESS
// =====================================================

/**
 * Destroy the current user session.
 * 
 * session_destroy() clears all session data that was stored
 * on the server. This logs the user out by removing their
 * authentication state.
 * 
 * After this, the user will need to log in again to access
 * protected pages.
 * 
 * @global Removes all session variables for the current user
 * @uses $_SESSION - PHP superglobal for session data
 */
session_destroy();

// =====================================================
// REDIRECT TO DASHBOARD
// =====================================================

/**
 * Redirect the user to the dashboard after logout.
 * 
 * After destroying the session, the user is redirected
 * back to the dashboard (which will show guest view).
 */
header('Location: ./dashboard.php');
exit();
