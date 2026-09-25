<?php
/** Shared bootstrap that starts the session, loads helpers, and renders role-based navigation. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : '';

// Base URL for the application
$baseUrl = dirname($_SERVER['PHP_SELF']);
if ($baseUrl === '/') {
    $baseUrl = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lending Management System</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/main.css">
    <?php 
    // Include page-specific CSS if it exists
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $css_file = "/assets/css/{$current_page}.css";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $baseUrl . $css_file)) {
        echo "<link rel=\"stylesheet\" href=\"{$baseUrl}{$css_file}\">";
    }
    ?>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1>Lending Management System</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="<?php echo $baseUrl; ?>/index.php">Home</a></li>
                    <?php if (!$isLoggedIn): ?>
                        <li><a href="<?php echo $baseUrl; ?>/signin.php">LogIn</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/signup.php">Sign Up</a></li>
                    <?php else: ?>
                        <?php if ($userRole == 'admin'): ?>
                            <li><a href="<?php echo $baseUrl; ?>/adminDashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo $baseUrl; ?>/loanReports.php">Loan Reports</a></li>
                            <li><a href="<?php echo $baseUrl; ?>/users.php">Manage Users</a></li>
                        <?php elseif ($userRole == 'customer'): ?>
                            <li><a href="<?php echo $baseUrl; ?>/borrowerDashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo $baseUrl; ?>/applyLoan.php">Apply for Loan</a></li>
                            <li><a href="<?php echo $baseUrl; ?>/loanHistory.php">Loan History</a></li>
                        <?php elseif ($userRole == 'lender'): ?>
                            <li><a href="<?php echo $baseUrl; ?>/lenderDashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo $baseUrl; ?>/activeLoans.php">Active Loans</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo $baseUrl; ?>/logout.php">LogOut</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">