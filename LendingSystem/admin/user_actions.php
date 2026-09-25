<?php
/** Admin-only POST endpoint for validated account management actions. */
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($user_id <= 0 && $action !== 'create') {
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    $_SESSION['admin_action_message'] = 'Invalid user selection.';
    header('Location: ../users.php');
    exit;
}

function adminRedirect($message, $success = true) {
    $_SESSION['admin_action_message'] = $message;
    $_SESSION['admin_action_success'] = $success ? '1' : '0';
    header('Location: ../users.php');
    exit;
}

$response = ['success' => false];

switch ($action) {
    case 'create':
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = isset($_POST['role']) ? $_POST['role'] : 'customer';

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
            $response['error'] = 'All fields are required';
            break;
        }

        $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $check->bind_param('s', $email);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows > 0) {
            $response['error'] = 'Email already exists';
            break;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, "active")');
        $insert->bind_param('sssss', $first_name, $last_name, $email, $hashed_password, $role);

        if ($insert->execute()) {
            $user_created_id = $conn->insert_id;
            logSystemActivity($conn, $_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'create_user', 'Created user account for ' . $email, 'user', $user_created_id, $_SESSION['role'] ?? 'admin');
            $response['success'] = true;
            $response['message'] = 'User created successfully';
        } else {
            $response['error'] = 'Failed to create user: ' . $conn->error;
        }
        break;

    case 'reset_password':
        $new_password = (string)($_POST['new_password'] ?? '');

        if ($new_password === '') {
            $response['error'] = 'New password is required';
            break;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = 'UPDATE users SET password_hash = ? WHERE id = ?';
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $hashed_password, $user_id);

        if ($stmt->execute()) {
            logSystemActivity($conn, $_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'reset_password', 'Reset password for user ID ' . $user_id, 'user', $user_id, $_SESSION['role'] ?? 'admin');
            $response['success'] = true;
            $response['message'] = 'Password reset successfully';
        } else {
            $response['error'] = 'Failed to reset password: ' . $conn->error;
        }
        break;

    case 'toggle_block':
        $new_status = (isset($_POST['status']) && $_POST['status'] === 'blocked') ? 'blocked' : 'active';

        $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $new_status, $user_id);

        if ($stmt->execute()) {
            logSystemActivity($conn, $_SESSION['user_id'], $_SESSION['username'] ?? 'admin', $new_status === 'blocked' ? 'block_user' : 'unblock_user', 'Changed user ID ' . $user_id . ' status to ' . $new_status, 'user', $user_id, $_SESSION['role'] ?? 'admin');
            $response['success'] = true;
            $response['message'] = $new_status === 'blocked' ? 'User blocked successfully' : 'User unblocked successfully';
        } else {
            $response['error'] = 'Failed to update user status: ' . $conn->error;
        }
        break;

    case 'delete':
        $loanCheck = $conn->prepare('SELECT COUNT(*) AS loan_count FROM loans WHERE customer_id = ? OR lender_id = ?');
        $loanCheck->bind_param('ii', $user_id, $user_id);
        $loanCheck->execute();
        $row = $loanCheck->get_result()->fetch_assoc();

        if ((int)($row['loan_count'] ?? 0) > 0) {
            $response['error'] = 'Cannot delete user with active loan records';
            break;
        }

        $delete = $conn->prepare('DELETE FROM users WHERE id = ?');
        $delete->bind_param('i', $user_id);

        if ($delete->execute()) {
            logSystemActivity($conn, $_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'delete_user', 'Deleted user ID ' . $user_id, 'user', $user_id, $_SESSION['role'] ?? 'admin');
            $response['success'] = true;
            $response['message'] = 'User deleted successfully';
        } else {
            $response['error'] = 'Failed to delete user: ' . $conn->error;
        }
        break;

    default:
        $response['error'] = 'Invalid action';
        break;
}

if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($response['success']) {
    adminRedirect($response['message'], true);
}

adminRedirect($response['error'], false);
?>