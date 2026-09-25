<?php
/** Login form and handler verifying credentials, status, and role before redirecting. */
require_once 'config/database.php';
//
session_start();

$error = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: adminDashboard.php');
            break;
        case 'customer':
            header('Location: borrowerDashboard.php');
            break;
        case 'lender':
            header('Location: lenderDashboard.php');
            break;
    }
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate credentials, create the session, and route the user by role.
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check user credentials
        $query = "SELECT id, username, password_hash, role, status FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $error = 'This account has been blocked by the administrator';
            } elseif (password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on user role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: adminDashboard.php');
                        break;
                    case 'customer':
                        header('Location: borrowerDashboard.php');
                        break;
                    case 'lender':
                        header('Location: lenderDashboard.php');
                        break;
                }
                exit;
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Lending Management System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/signin.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<div class="topbar">
    <div class="topbar-inner">
        <span>Secure Lending Management System</span>
        <span>Support: support@lms.example &nbsp; | &nbsp; +254 700 000 000</span>
    </div>
</div>

<nav class="navbar">
    <div class="nav-inner">
        <a class="brand" href="#">
            <div class="brand-mark">L</div>
            <div>
                <strong>Lending Management</strong>
                <small>Simple • Transparent • Responsible</small>
            </div>
        </a>
        <div class="nav-links">
            <strong>Security</strong>
            <strong>Customer Service</strong>
            <a class="signin" href="#">Sign In</a>
        </div>
    </div>
</nav>

<main class="hero">
    <section>
        <span class="eyebrow">● Welcome back</span>
        <h1>Sign in to your account</h1>
        <p style="color:var(--muted);max-width:640px;">Enter your username and password to access your Lending Management dashboard.</p>
    </section>

    <section class="auth-card">
        <div class="card-head">
            <h2>Sign In</h2>
            <p style="margin:0;color:var(--muted);font-size:13px">Manage loans, repayments and applications.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="signin.php">
            <div class="form-grid">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Sign In</button>
                </div>
            </div>
        </form>

        <div class="auth-footer">Don't have an account? <a href="signup.php">Create Account</a></div>
    </section>
</main>

<footer>
    <div class="footer-inner">
        <div>
            <strong>Lending Management System</strong><br>
            <small>Responsible lending. Better financial access.</small>
        </div>
        <small>© <?php echo date('Y'); ?> LMS. All rights reserved.</small>
    </div>
</footer>

</body>
</html>