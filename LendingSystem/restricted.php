<?php
/** Presentation-only access-denied page selected by authorization middleware. */
require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="container" style="text-align: center; padding: 50px;">
        <h1>Access Denied</h1>
        <p>You don't have permission to access this page.</p>
        <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">Go Home</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>