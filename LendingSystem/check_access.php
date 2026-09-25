<?php
/**
 * Authentication middleware to control access to different parts of the application
 * This file should be included at the beginning of protected pages
 * Starts the session and provides reusable login and role guards that terminate
 * execution after redirecting unauthorized requests.
 */

function ensureSessionStarted() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

ensureSessionStarted();

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string|array $roles Single role or array of roles to check
 * @return bool True if user has any of the specified roles, false otherwise
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Redirect user if not logged in
 * @param string $redirect_url URL to redirect to if not logged in
 */
function requireLogin($redirect_url = 'signin.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Redirect user if not having specific role
 * @param string|array $roles Single role or array of roles required
 * @param string $redirect_url URL to redirect to if not having required role
 */
function requireRole($roles, $redirect_url = 'restricted.php') {
    requireLogin();
    
    if (!hasRole($roles)) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Check if user account is active
 * @param PDO $conn Database connection
 * @return bool True if user account is active, false otherwise
 */
function isAccountActive($conn) {
    if (!isLoggedIn() || !isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    $user_id = (int)$_SESSION['user_id'];
    $sql = "SELECT status FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        return ($user['status'] ?? '') === 'active';
    }

    return false;
}

/**
 * Redirect user if account is not active
 * @param PDO $conn Database connection
 * @param string $redirect_url URL to redirect to if account is not active
 */
function requireActiveAccount($conn, $redirect_url = 'blocked.html') {
    requireLogin();
    
    if (!isAccountActive($conn)) {
        // Destroy session
        session_destroy();
        
        // Redirect to blocked page
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Check if user owns a resource
 * @param PDO $conn Database connection
 * @param string $table Table name
 * @param string $id_column ID column name
 * @param int $resource_id Resource ID
 * @param string $user_column User ID column name
 * @return bool True if user owns the resource, false otherwise
 */
function ownsResource($conn, $table, $id_column, $resource_id, $user_column = 'customer_id') {
    if (!isLoggedIn() || !isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    $user_id = (int)$_SESSION['user_id'];
    $resource_id = (int)$resource_id;
    $sql = "SELECT 1 FROM $table WHERE $id_column = ? AND $user_column = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $resource_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result && $result->num_rows === 1;
}

/**
 * Redirect user if not owning a resource
 * @param PDO $conn Database connection
 * @param string $table Table name
 * @param string $id_column ID column name
 * @param int $resource_id Resource ID
 * @param string $user_column User ID column name
 * @param string $redirect_url URL to redirect to if not owning the resource
 */
function requireResourceOwnership($conn, $table, $id_column, $resource_id, $user_column = 'customer_id', $redirect_url = 'restricted.php') {
    requireLogin();
    
    // Admin can access any resource
    if (hasRole('admin')) {
        return;
    }
    
    if (!ownsResource($conn, $table, $id_column, $resource_id, $user_column)) {
        header("Location: $redirect_url");
        exit;
    }
}
?>