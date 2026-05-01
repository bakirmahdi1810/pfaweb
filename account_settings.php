<?php
/**
 * Account Settings Page
 * 
 * This page allows logged-in users to update their profile information.
 * Users can modify their name, email, phone number, and governorate.
 * 
 * Features:
 * - Display current profile information
 * - Form validation for all fields
 * - Email uniqueness check (excluding current user)
 * - Partial updates (only changed fields are saved)
 * - Session update when name or email changes
 * 
 * Security:
 * - Requires login (checkLogin)
 * - Validates user still exists in database
 * - Prevents email collision with other users
 * 
 * @package BookShare
 * @version 1.0
 * @requires login User must be logged in to access
 */

// Include the application configuration file
require_once 'config/app.php';

/**
 * Security Check: Ensure user is logged in.
 */
checkLogin();

// Initialize message variables
$error = '';     // Error message to display
$success = '';   // Success message to display

// =====================================================
// GET CURRENT USER INFORMATION
// =====================================================

/**
 * Fetch the current user's profile data from the database.
 * This is used to pre-fill the form with existing values.
 */
$stmtUser = $connexion->prepare('SELECT nom, prenom, email, tel, governorate FROM users WHERE id = ?');
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

// If user not found (deleted from DB), redirect to login
if (!$user) {
    header('Location: ./login.php');
    exit();
}

// =====================================================
// HANDLE PROFILE UPDATE FORM SUBMISSION
// =====================================================

/**
 * Process profile update when user submits the form.
 * 
 * This block handles updating user profile information.
 * It validates all input and only updates fields that have changed.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form inputs
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';              // Last name
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';   // First name
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';       // Email address
    $tel = isset($_POST['tel']) ? trim($_POST['tel']) : '';             // Phone number
    $governorate = isset($_POST['governorate']) ? trim($_POST['governorate']) : ''; // Governorate

    // =====================================================
    // VALIDATION
    // =====================================================
    
    // Check 1: All required fields are filled
    if (empty($nom) || empty($prenom) || empty($email) || empty($governorate)) {
        $error = 'All fields are required.';
    } 
    // Check 2: Email format is valid
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } 
    // Check 3: Governorate is valid
    elseif (!isValidGovernorate($governorate)) {
        $error = 'Invalid governorate selected.';
    } 
    else {
        // =====================================================
        // CHECK FOR EMAIL CONFLICT
        // =====================================================
        
        // If email is changed, check it's not used by another user
        if ($email !== $user['email']) {
            $stmtCheckEmail = $connexion->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmtCheckEmail->execute([$email, $_SESSION['user_id']]);
            if ($stmtCheckEmail->rowCount() > 0) {
                $error = 'Email already registered by another user.';
            }
        }

        // If no error, proceed with update
        if (empty($error)) {
                // Update only changed fields
                $updates = [];
                $values = [];

                if ($nom !== $user['nom']) {
                    $updates[] = 'nom = ?';
                    $values[] = $nom;
                }
                if ($prenom !== $user['prenom']) {
                    $updates[] = 'prenom = ?';
                    $values[] = $prenom;
                }
                if ($email !== $user['email']) {
                    $updates[] = 'email = ?';
                    $values[] = $email;
                }
                if ($tel !== $user['tel']) {
                    $updates[] = 'tel = ?';
                    $values[] = $tel;
                }
                if ($governorate !== $user['governorate']) {
                    $updates[] = 'governorate = ?';
                    $values[] = $governorate;
                }

                // Only execute if there are changes
                if (count($updates) > 0) {
                    $values[] = $_SESSION['user_id'];
                    $query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
                    $stmt = $connexion->prepare($query);
                    $stmt->execute($values);

                    // Update session data if name or email changed
                    if ($nom !== $user['nom']) $_SESSION['nom'] = $nom;
                    if ($email !== $user['email']) $_SESSION['email'] = $email;

                    $success = 'Your profile has been updated successfully!';
                    
                    // Refresh user data
                    $stmtUser = $connexion->prepare('SELECT nom, prenom, email, tel, governorate FROM users WHERE id = ?');
                    $stmtUser->execute([$_SESSION['user_id']]);
                    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
                } else {
                    $success = 'No changes were made to your profile.';
                }
            }
        } catch (PDOException $e) {
            $error = 'An error occurred while updating your profile: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Account Settings - BookShare';
include 'layout/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="lni lni-cog"></i> Account Settings
            </div>
            <div class="card-body">
                <!-- Information Message -->
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="lni lni-info-circle"></i>
                    <strong>Update Your Information:</strong> Feel free to modify any fields you'd like to change. Fields you leave unchanged will remain the same in your profile.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="lni lni-alert-circle"></i> <?php echo escape($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="lni lni-checkmark-circle"></i> <?php echo escape($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" data-validate="true">
                    <fieldset>
                        <legend>Personal Information</legend>
                        
                        <div class="row mb-3">
                            <label for="prenom" class="col-sm-3 col-form-label">First Name</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       required placeholder="<?php echo escape($user['prenom']); ?>"
                                       value="<?php echo isset($_POST['prenom']) ? escape($_POST['prenom']) : escape($user['prenom']); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="nom" class="col-sm-3 col-form-label">Last Name</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       required placeholder="<?php echo escape($user['nom']); ?>"
                                       value="<?php echo isset($_POST['nom']) ? escape($_POST['nom']) : escape($user['nom']); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="email" class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email" 
                                       required placeholder="<?php echo escape($user['email']); ?>"
                                       value="<?php echo isset($_POST['email']) ? escape($_POST['email']) : escape($user['email']); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="tel" class="col-sm-3 col-form-label">Phone</label>
                            <div class="col-sm-9">
                                <input type="tel" class="form-control" id="tel" name="tel" 
                                       placeholder="<?php echo escape($user['tel'] ?: 'No phone number'); ?>"
                                       value="<?php echo isset($_POST['tel']) ? escape($_POST['tel']) : escape($user['tel']); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="governorate" class="col-sm-3 col-form-label">Governorate</label>
                            <div class="col-sm-9">
                                <select class="form-control" id="governorate" name="governorate" required>
                                    <?php echo getGovernorateOptions(isset($_POST['governorate']) ? $_POST['governorate'] : $user['governorate']); ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <div class="row mb-3">
                        <div class="col-sm-9 offset-sm-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="lni lni-save"></i> Save Changes
                            </button>
                            <a href="./dashboard.php" class="btn btn-outline-secondary">
                                <i class="lni lni-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
