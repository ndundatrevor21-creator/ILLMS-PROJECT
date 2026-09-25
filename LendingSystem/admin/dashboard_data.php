<?php
/** Admin-only JSON endpoint for user, loan, payment, and recent-record dashboard data. */
require_once '../config/database.php';

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get requested data type
$data_type = isset($_GET['type']) ? $_GET['type'] : '';

// Initialize response array
$response = [];

switch ($data_type) {
    case 'user_stats':
        // Get user statistics
        $query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as total_customers,
                    SUM(CASE WHEN role = 'lender' THEN 1 ELSE 0 END) as total_lenders,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30days
                  FROM users";
        $result = executeQuery($query);
        $response = $result->fetch_assoc();
        break;
        
    case 'loan_stats':
        // Get loan statistics
        $query = "SELECT 
                    COUNT(*) as total_loans,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_loans,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_loans,
                    SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted_loans,
                    SUM(amount) as total_amount,
                    AVG(interest_rate) * 100 as avg_interest_rate
                  FROM loans";
        $result = executeQuery($query);
        $response = $result->fetch_assoc();
        break;
        
    case 'payment_stats':
        // Get payment statistics
        $query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(amount) as total_payment_amount,
                    SUM(CASE WHEN payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) as payment_amount_30days
                  FROM payments
                  WHERE status = 'completed'";
        $result = executeQuery($query);
        $response = $result->fetch_assoc();
        break;
        
    case 'recent_loans':
        // Get recent loans
        $query = "SELECT l.*, 
                         u.first_name, u.last_name, u.email
                  FROM loans l
                  JOIN users u ON l.customer_id = u.id
                  ORDER BY l.created_at DESC
                  LIMIT 10";
        $result = executeQuery($query);
        $loans = [];
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }
        $response['loans'] = $loans;
        break;
        
    case 'recent_users':
        // Get recent users
        $query = "SELECT id, first_name, last_name, email, role, created_at
                  FROM users
                  ORDER BY created_at DESC
                  LIMIT 10";
        $result = executeQuery($query);
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $response['users'] = $users;
        break;
        
    default:
        $response['error'] = 'Invalid data type requested';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>