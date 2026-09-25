<?php
/** Admin-only POST endpoint that validates and deletes a selected loan application. */
require_once '../config/database.php';

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get application ID
$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

// Initialize response
$response = ['success' => false];

// Validate application ID
if ($application_id <= 0) {
    $response['error'] = 'Invalid application ID';
    echo json_encode($response);
    exit;
}

// Check if application exists
$query = "SELECT * FROM loan_applications WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['error'] = 'Loan application not found';
    echo json_encode($response);
    exit;
}

// Delete the loan application
$query = "DELETE FROM loan_applications WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $application_id);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Loan application deleted successfully';
} else {
    $response['error'] = 'Failed to delete loan application: ' . $conn->error;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>