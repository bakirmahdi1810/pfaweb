<?php
/**
 * Header Layout
 * Include bootstrap, lineicons, and custom CSS
 */
if (!isset($_SESSION)) {
    session_start();
}

$request_path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$script_path = strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$current_page = strtolower(basename($script_path));

function isNavCurrent($requestPath, $currentPage, $pages) {
    foreach ($pages as $page) {
        $target = strtolower($page);
        if ($currentPage === $target) {
            return true;
        }

        if ($requestPath !== '' && substr($requestPath, -strlen('/' . $target)) === '/' . $target) {
            return true;
        }
    }

    return false;
}

function navActiveClass($requestPath, $currentPage, $pages) {
    return isNavCurrent($requestPath, $currentPage, $pages) ? ' is-active active' : '';
}

function navCurrentAttr($requestPath, $currentPage, $pages) {
    return isNavCurrent($requestPath, $currentPage, $pages) ? ' aria-current="page"' : '';
}

$style_file = __DIR__ . '/../assets/css/style.css';
$style_version = file_exists($style_file) ? (string) filemtime($style_file) : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? escape($pageTitle) : 'Book Donation System'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Lineicons CSS -->
    <link href="https://cdn.lineicons.com/style.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="./assets/css/style.css?v=<?php echo escape($style_version); ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="./dashboard.php">
                <i class="lni lni-book"></i> BookShare
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-dashboard-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['dashboard.php']); ?>" href="./dashboard.php"<?php echo navCurrentAttr($request_path, $current_page, ['dashboard.php']); ?>>
                                <i class="lni lni-dashboard"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['browse_catalog.php']); ?>" href="./browse_catalog.php"<?php echo navCurrentAttr($request_path, $current_page, ['browse_catalog.php']); ?>>
                                <i class="lni lni-book"></i> Books in Stock
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['donate.php']); ?>" href="./donate.php"<?php echo navCurrentAttr($request_path, $current_page, ['donate.php']); ?>>
                                <i class="lni lni-gift"></i> Donate
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['request.php']); ?>" href="./request.php"<?php echo navCurrentAttr($request_path, $current_page, ['request.php']); ?>>
                                <i class="lni lni-search"></i> Request
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['my_matches.php']); ?>" href="./my_matches.php"<?php echo navCurrentAttr($request_path, $current_page, ['my_matches.php']); ?>>
                                <i class="lni lni-handshake"></i> My Matches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['account_settings.php']); ?>" href="./account_settings.php"<?php echo navCurrentAttr($request_path, $current_page, ['account_settings.php']); ?>>
                                <i class="lni lni-cog"></i> Settings
                            </a>
                        </li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['admin_dashboard.php', 'book_management.php']); ?>" href="./admin_dashboard.php"<?php echo navCurrentAttr($request_path, $current_page, ['admin_dashboard.php', 'book_management.php']); ?>>
                                    <i class="lni lni-stats-up"></i> Admin Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern" href="./logout.php">
                                <i class="lni lni-exit"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['browse_catalog.php']); ?>" href="./browse_catalog.php"<?php echo navCurrentAttr($request_path, $current_page, ['browse_catalog.php']); ?>>
                                <i class="lni lni-book"></i> Books in Stock
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['login.php']); ?>" href="./login.php"<?php echo navCurrentAttr($request_path, $current_page, ['login.php']); ?>>
                                <i class="lni lni-log-in"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-modern<?php echo navActiveClass($request_path, $current_page, ['register.php']); ?>" href="./register.php"<?php echo navCurrentAttr($request_path, $current_page, ['register.php']); ?>>
                                <i class="lni lni-user-add"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-<?php echo escape($_SESSION['message_type'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
                <?php echo escape($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="container-fluid flex-grow-1 d-flex flex-column">