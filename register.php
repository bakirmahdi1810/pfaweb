<?php
/**
 * User Registration Page
 * 
 * This page handles new user registration. Users provide their personal
 * information including name, email, password, phone, and governorate.
 * 
 * Features:
 * - Form validation (required fields, email format, password strength)
 * - Password confirmation matching
 * - Governorate selection from Tunisian regions
 * - Email uniqueness check
 * - Automatic login after successful registration
 * 
 * @package BookShare
 * @version 1.0
 */

// Include the application configuration file
require_once 'config/app.php';

// =====================================================
// CHECK IF USER IS ALREADY LOGGED IN
// =====================================================

/**
 * Security Check: Prevent logged-in users from accessing registration page.
 * Already registered users should use the dashboard.
 */
if (isset($_SESSION['user_id'])) {
    header('Location: ./dashboard.php');
    exit();
}

// Initialize error message variable
$error = '';

// =====================================================
// HANDLE REGISTRATION FORM SUBMISSION
// =====================================================

/**
 * Process registration form when user submits the form.
 * 
 * This block validates all input and creates a new user account
 * if all validation checks pass.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize all form inputs
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';              // Last name
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';   // First name
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';       // Email address
    $password = isset($_POST['password']) ? $_POST['password'] : '';   // Password
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''; // Password confirmation
    $tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';             // Phone number (optional)
    $governorate = isset($_POST['governorate']) ? trim($_POST['governorate']) : ''; // Governorate

    // =====================================================
    // VALIDATION CHECKS
    // =====================================================

    // Check 1: All required fields are filled
    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($governorate)) {
        $error = 'All fields are required.';
    } 
    // Check 2: Email format is valid
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } 
    // Check 3: Password is at least 6 characters
    elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } 
    // Check 4: Passwords match
    elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } 
    // Check 5: Governorate is valid (from predefined list)
    elseif (!isValidGovernorate($governorate)) {
        $error = 'Invalid governorate selected.';
    } 
    else {
        // Check 6: Email is not already registered
        $stmt = $connexion->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already registered.';
        } else {
            // =====================================================
            // CREATE NEW USER ACCOUNT
            // =====================================================
            
            // Insert new user into the database
            // Default role is 'user' (not admin)
            $stmt = $connexion->prepare(
                'INSERT INTO users (nom, prenom, email, password, tel, governorate, role) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            
            if ($stmt->execute([$nom, $prenom, $email, $password, $tel, $governorate, 'user'])) {
                // Get the newly created user's ID
                $user_id = $connexion->lastInsertId();
                
                // Automatically log in the new user
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['nom'] = $nom;
                $_SESSION['role'] = 'user';
                
                // Set success message
                $_SESSION['message'] = 'Registration successful! Welcome to BookShare.';
                $_SESSION['message_type'] = 'success';
                
                // Redirect to dashboard
                header('Location: ./dashboard.php');
                exit();
            } else {
                $error = 'An error occurred during registration. Please try again.';
            }
        }
    }
}

// Set page title for the header
$pageTitle = 'Register - BookShare';

// Include the header layout
include 'layout/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="lni lni-user-add"></i> Create Account
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
                        <legend>Personal Information</legend>
                        
                        <div class="row mb-3">
                            <label for="prenom" class="col-sm-2 col-form-label">First Name</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       required value="<?php echo isset($_POST['prenom']) ? escape($_POST['prenom']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="nom" class="col-sm-2 col-form-label">Last Name</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       required value="<?php echo isset($_POST['nom']) ? escape($_POST['nom']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="email" class="col-sm-2 col-form-label">Email</label>
                            <div class="col-sm-10">
                                <input type="email" class="form-control" id="email" name="email" 
                                       required value="<?php echo isset($_POST['email']) ? escape($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="tel" class="col-sm-2 col-form-label">Phone</label>
                            <div class="col-sm-10">
                                <input type="tel" class="form-control" id="tel" name="tel" 
                                       value="<?php echo isset($_POST['tel']) ? escape($_POST['tel']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="governorate" class="col-sm-2 col-form-label">Governorate</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="governorate" name="governorate" required>
                                    <?php echo getGovernorateOptions(isset($_POST['governorate']) ? $_POST['governorate'] : ''); ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Security</legend>
                        
                        <div class="row mb-3">
                            <label for="password" class="col-sm-2 col-form-label">Password</label>
                            <div class="col-sm-10">
                                <input type="password" class="form-control" id="password" name="password" 
                                       required minlength="6">
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="confirm_password" class="col-sm-2 col-form-label">Confirm Password</label>
                            <div class="col-sm-10">
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required minlength="6">
                            </div>
                        </div>
                    </fieldset>

                    <div class="row mb-3">
                        <div class="col-sm-10 offset-sm-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="lni lni-user-add"></i> Create Account
                            </button>
                            <a href="./login.php" class="btn btn-outline-secondary">
                                <i class="lni lni-log-in"></i> Already registered? Login
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
