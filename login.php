<?php
/**
 * User Login Page
 * 
 * This page handles user authentication. Users enter their email and password
 * to access their account. If credentials are valid, they are redirected to the
 * dashboard. Already logged-in users are redirected to dashboard automatically.
 * 
 * Features:
 * - Email and password validation
 * - Session creation on successful login
 * - Error messages for invalid credentials
 * - Redirect to registration page for new users
 * 
 * @package BookShare
 * @version 1.0
 */

// Include the application configuration file
// This provides database connection and helper functions
require_once 'config/app.php';

// =====================================================
// CHECK IF USER IS ALREADY LOGGED IN
// =====================================================

/**
 * Security Check: Prevent logged-in users from accessing login page.
 * 
 * If user already has an active session (user_id is set), redirect them
 * directly to the dashboard. This prevents unnecessary re-login.
 */
if (isset($_SESSION['user_id'])) {
    header('Location: ./dashboard.php');
    exit();
}

// Initialize error message variable
$error = '';

// =====================================================
// HANDLE LOGIN FORM SUBMISSION
// =====================================================

/**
 * Process login form when user submits the form.
 * 
 * This block executes only when the form is submitted via POST method.
 * It validates the input and checks credentials against the database.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form inputs
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate that both fields are filled
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        // Look up user by email in the database
        // Using prepared statement prevents SQL injection
        $stmt = $connexion->prepare('SELECT id, nom, prenom, email, password, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        // Check if user exists
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password (simple comparison - should use password_hash in production)
            if ($password === $user['password']) {
                // =====================================================
                // LOGIN SUCCESSFUL - CREATE SESSION
                // =====================================================
                
                // Store user information in session for persistent login
                $_SESSION['user_id'] = $user['id'];      // User's unique ID
                $_SESSION['email'] = $user['email'];    // User's email
                $_SESSION['nom'] = $user['nom'];        // User's last name
                $_SESSION['prenom'] = $user['prenom']; // User's first name
                $_SESSION['role'] = $user['role'];      // User's role (admin/user)
                
                // Set success message to display on dashboard
                $_SESSION['message'] = 'Login successful! Welcome back.';
                $_SESSION['message_type'] = 'success';
                
                // Redirect to dashboard
                header('Location: ./dashboard.php');
                exit();
            } else {
                // Password doesn't match
                $error = 'Invalid email or password.';
            }
        } else {
            // User with this email doesn't exist
            $error = 'Invalid email or password.';
        }
    }
}

// Set page title for the header
$pageTitle = 'Login - BookShare';

// Include the header layout (navigation bar, Bootstrap CSS, etc.)
include 'layout/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <i class="lni lni-log-in"></i> Login
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
                        <legend>Credentials</legend>
                        
                        <div class="row mb-3">
                            <label for="email" class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email" 
                                       required value="<?php echo isset($_POST['email']) ? escape($_POST['email']) : ''; ?>" 
                                       autofocus>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="password" class="col-sm-3 col-form-label">Password</label>
                            <div class="col-sm-9">
                                <input type="password" class="form-control" id="password" name="password" 
                                       required>
                            </div>
                        </div>
                    </fieldset>

                    <div class="row mb-3">
                        <div class="col-sm-9 offset-sm-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="lni lni-log-in"></i> Login
                            </button>
                            <a href="./register.php" class="btn btn-outline-secondary">
                                <i class="lni lni-user-add"></i> Create Account
                            </a>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
