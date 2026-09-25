<?php
/** Admin-only destructive endpoint validating a user id before account deletion. */
require_once 'includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit;
}

if (isset($_GET['id'])){
     $id = $_GET['id'];
     // Disable foreign key checks temporarily
     $conn->query("SET FOREIGN_KEY_CHECKS = 0");
     $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
     $stmt->bind_param("i", $id);
     $stmt->execute();
     // Re-enable foreign key checks
     $conn->query("SET FOREIGN_KEY_CHECKS = 1");
     header('Location: users.php');
     exit;
}
?>

<div class="dashboard-section" style="margin-top:34px;">
    <div class="card">
        <div class="card-header">
            <h2>User Deletion</h2>
        </div>
        <div class="card-body">
            <p class="text-center">User Successfully Deleted</p>
            <div class="text-center">
                <a href="users.php" class="btn btn-primary">Back to Users</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>