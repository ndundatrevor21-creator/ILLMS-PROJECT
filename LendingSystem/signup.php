<?php
/** Registration form and handler validating fields, hashing credentials, and creating a user. */
require_once 'config/database.php';
session_start();

$error = '';
$success = '';

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

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate the form, create the account, and sign the new user in.
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address, for example name@gmail.com';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, and a number';
    } elseif (!in_array($role, ['customer', 'lender'])) {
        $error = 'Invalid account type';
    } else {
        // Check if username or email already exists
        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $insert_query = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($insert_stmt->execute()) {
                // Automatically log in the newly registered user
                $_SESSION['user_id'] = $insert_stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;

                switch ($role) {
                    case 'customer':
                        header('Location: borrowerDashboard.php');
                        break;
                    case 'lender':
                        header('Location: lenderDashboard.php');
                        break; 
                }
                exit;
            } else {
                $error = 'Registration failed: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Lending Management System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/signup.css">
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
        <a class="brand" href="index.php" title="Lending Management System">
            <div class="brand-mark">L</div>
            <div>
                <strong>Lending Management</strong>
                <small>Simple • Transparent • Responsible</small>
            </div>
        </a>
        <div class="nav-links">
            <a href="#">Security</a>
            <a href="#">Customer Service</a>
            <a class="signin" href="#">Sign In</a>
        </div>
    </div>
</nav>

<main class="hero">
    <section>
        <span class="eyebrow">● Trusted digital lending</span>
        <h1>Access credit with confidence.</h1>
        <p style="color:var(--muted);max-width:640px;">Create your Lending Management System account and manage your borrowing journey from one secure, simple platform.</p>
    </section>

    <section class="auth-card">
        <div class="card-head">
            <h2>Create an account</h2>
            <p style="margin:0;color:var(--muted);font-size:13px">Choose your account type to get started.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="signup.php">
            <div class="form-grid">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="name@gmail.com" autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="8+ chars, upper/lowercase and number" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters with uppercase, lowercase, and a number" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group full">
                    <label for="role">Account type</label>
                    <select id="role" name="role" required>
                        <option value="customer">Borrower</option>
                        <option value="lender">Lender</option>
                    </select>
                </div>
                <div class="form-group full">
                    <button type="submit" class="btn">Create Account</button>
                    <div style="font-size:12px;color:var(--muted);margin-top:10px">By creating an account you agree to our terms.</div>
                </div>
            </div>
        </form>

        <div class="auth-footer">Already have an account? <a href="signin.php">Sign in</a></div>
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