<?php
/** Authenticated autocomplete endpoint returning bounded JSON search suggestions. */
require_once '../config/database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get search query and type
$query_term = isset($_GET['query']) ? $_GET['query'] : '';
$search_type = isset($_GET['type']) ? $_GET['type'] : 'loans';
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Initialize response array
$suggestions = [];

if (!empty($query_term)) {
    switch ($search_type) {
        case 'loans':
            // Search loans based on role
            if ($role === 'admin') {
                // Admin can search all loans
                $sql = "SELECT l.id, l.amount, l.purpose, u.first_name, u.last_name 
                        FROM loans l
                        JOIN users u ON l.customer_id = u.id
                        WHERE l.id LIKE ? 
                        OR l.purpose LIKE ? 
                        OR u.first_name LIKE ? 
                        OR u.last_name LIKE ?
                        LIMIT 10";
                $stmt = $conn->prepare($sql);
                $search_param = "%$query_term%";
                $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
            } elseif ($role === 'lender') {
                // Lender can only search loans they've funded
                $sql = "SELECT l.id, l.amount, l.purpose, u.first_name, u.last_name 
                        FROM loans l
                        JOIN users u ON l.customer_id = u.id
                        WHERE l.lender_id = ? AND (l.id LIKE ? OR l.purpose LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)
                        LIMIT 10";
                $stmt = $conn->prepare($sql);
                $search_param = "%$query_term%";
                $stmt->bind_param("issss", $user_id, $search_param, $search_param, $search_param, $search_param);
            } else {
                // Customer can only search their own loans
                $sql = "SELECT l.id, l.amount, l.purpose, u.first_name, u.last_name 
                        FROM loans l
                        JOIN users u ON l.customer_id = u.id
                        WHERE l.customer_id = ? AND (l.id LIKE ? OR l.purpose LIKE ?)
                        LIMIT 10";
                $stmt = $conn->prepare($sql);
                $search_param = "%$query_term%";
                $stmt->bind_param("iss", $user_id, $search_param, $search_param);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = [
                    'id' => $row['id'],
                    'text' => "Loan #" . $row['id'] . " - " . $row['purpose'] . " (Kshs " . number_format($row['amount'], 2) . ")",
                    'url' => "viewLoanDetails.php?id=" . $row['id']
                ];
            }
            break;
            
        case 'users':
            // Only admin can search users
            if ($role === 'admin') {
                $sql = "SELECT id, first_name, last_name, email, role 
                        FROM users
                        WHERE first_name LIKE ? 
                        OR last_name LIKE ? 
                        OR email LIKE ?
                        LIMIT 10";
                $stmt = $conn->prepare($sql);
                $search_param = "%$query_term%";
                $stmt->bind_param("sss", $search_param, $search_param, $search_param);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $suggestions[] = [
                        'id' => $row['id'],
                        'text' => $row['first_name'] . " " . $row['last_name'] . " (" . $row['email'] . ") - " . ucfirst($row['role']),
                        'url' => "viewUser.php?id=" . $row['id']
                    ];
                }
            }
            break;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['suggestions' => $suggestions]);
?>