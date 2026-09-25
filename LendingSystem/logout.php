<?php
/** Clears the current session and redirects the visitor to sign-in. */
session_start();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: signin.php');
exit;
?>