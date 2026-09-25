<?php
/** Role-protected JSON API for transactional loan disbursement and repayment. */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_access.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is allowed']);
    exit;
}

$action = $_GET['action'] ?? '';
if ($action === 'disburse') {
    if (!hasRole('lender')) {
        http_response_code(403);
        echo json_encode(['error' => 'Only lenders can disburse loans']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
    $termMonths = isset($input['term_months']) ? (int)$input['term_months'] : 6;

    if ($applicationId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'application_id is required']);
        exit;
    }

    // Lock the application so concurrent requests cannot fund it twice.
    $conn->begin_transaction();
    $statement = $conn->prepare(
        "SELECT la.*, u.username as borrower_name 
         FROM loan_applications la 
         JOIN users u ON la.customer_id = u.id 
         WHERE la.id = ? AND la.status = 'pending'
         FOR UPDATE"
    );
    $statement->bind_param('i', $applicationId);
    $statement->execute();
    $result = $statement->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['error' => 'Pending loan application not found']);
        exit;
    }

    $loanData = $result->fetch_assoc();
    $existingStmt = $conn->prepare("SELECT id FROM loans WHERE application_id = ? LIMIT 1");
    $existingStmt->bind_param('i', $applicationId);
    $existingStmt->execute();
    if ($existingStmt->get_result()->num_rows > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'This loan application has already been funded']);
        exit;
    }
    $customerId = $loanData['customer_id'];
    $amount = (float)$loanData['amount'];

    // Serialize approvals for the same borrower across separate applications.
    $borrowerLockStmt = $conn->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
    $borrowerLockStmt->bind_param('i', $customerId);
    $borrowerLockStmt->execute();

    // A borrower cannot receive another approval while an existing loan is active.
    $activeLoanStmt = $conn->prepare("SELECT id FROM loans WHERE customer_id = ? AND status = 'active' LIMIT 1");
    $activeLoanStmt->bind_param('i', $customerId);
    $activeLoanStmt->execute();
    if ($activeLoanStmt->get_result()->num_rows > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'This borrower already has an active loan']);
        exit;
    }

    $creditData = calculateCreditScore($customerId, $amount);
    if ($creditData['score'] < 34) {
        $conn->rollback();
        http_response_code(422);
        echo json_encode([
            'error' => 'Credit risk too high to disburse',
            'credit_score' => $creditData['score'],
            'risk_class' => $creditData['risk_class'],
            'credit_label' => $creditData['label'],
            'advice' => $creditData['advice'],
            'factors' => [
                'employment' => $creditData['employment_score'],
                'income' => $creditData['income_score'],
                'dti' => $creditData['dti_score'],
                'history' => $creditData['history_score'],
            ]
        ]);
        exit;
    }

    // Use the same transparent rate policy as the web funding workflow.
    $interestRate = determineLoanInterestRate($creditData['score']);

    $conn->begin_transaction();
    try {
        $updateStmt = $conn->prepare("UPDATE loan_applications SET status = 'approved' WHERE id = ?");
        $updateStmt->bind_param('i', $applicationId);
        $updateStmt->execute();

        $insertStmt = $conn->prepare(
            "INSERT INTO loans (application_id, customer_id, lender_id, amount, interest_rate, term_months, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())"
        );
        $lenderId = $_SESSION['user_id'];
        $insertStmt->bind_param('iiiddi', $applicationId, $customerId, $lenderId, $amount, $interestRate, $termMonths);
        $insertStmt->execute();

        $conn->commit();
        echo json_encode([
            'success' => true,
            'loan_id' => $conn->insert_id,
            'credit_score' => $creditData['score'],
            'risk_class' => $creditData['risk_class'],
            'credit_label' => $creditData['label'],
            'interest_rate' => $interestRate,
            'factors' => [
                'employment' => $creditData['employment_score'],
                'income' => $creditData['income_score'],
                'dti' => $creditData['dti_score'],
                'history' => $creditData['history_score'],
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to disburse loan', 'message' => $e->getMessage()]);
    }

    exit;
}

if ($action === 'repay') {
    if (!hasRole('customer')) {
        http_response_code(403);
        echo json_encode(['error' => 'Only customers can make repayments']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $loanId = isset($input['loan_id']) ? (int)$input['loan_id'] : 0;
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0;
    $method = isset($input['method']) ? trim($input['method']) : 'manual';

    if ($loanId <= 0 || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'loan_id and positive amount are required']);
        exit;
    }

    $customerId = $_SESSION['user_id'];
    $statement = $conn->prepare("SELECT * FROM loans WHERE id = ? AND customer_id = ? AND status = 'active'");
    $statement->bind_param('ii', $loanId, $customerId);
    $statement->execute();
    $result = $statement->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Active loan not found for this customer']);
        exit;
    }

    $loan = $result->fetch_assoc();
    // Calculate the full balance, including simple interest, before accepting payment.
    $loanSummary = calculateLoanPaymentSummary($loanId);
    if ($loanSummary['remaining_balance'] <= 0) {
        http_response_code(409);
        echo json_encode(['error' => 'This loan has already been fully repaid']);
        exit;
    }
    if ($amount > $loanSummary['remaining_balance']) {
        http_response_code(422);
        echo json_encode([
            'error' => 'Payment exceeds the remaining balance',
            'remaining_balance' => $loanSummary['remaining_balance']
        ]);
        exit;
    }

    $monthlyRate = $loan['interest_rate'] / 100 / 12;
    if ($monthlyRate > 0 && $loan['term_months'] > 0) {
        $monthlyPayment = $loan['amount'] * $monthlyRate * pow(1 + $monthlyRate, $loan['term_months']) / (pow(1 + $monthlyRate, $loan['term_months']) - 1);
    } else {
        $monthlyPayment = $loan['amount'] / max(1, $loan['term_months']);
    }

    $paymentColumns = $conn->query("SHOW COLUMNS FROM payments");
    $paymentFields = [];
    if ($paymentColumns) {
        while ($column = $paymentColumns->fetch_assoc()) {
            $paymentFields[] = $column['Field'];
        }
    }

    if (in_array('method', $paymentFields, true) && in_array('status', $paymentFields, true) && in_array('paid_at', $paymentFields, true)) {
        $insertStmt = $conn->prepare("INSERT INTO payments (loan_id, customer_id, amount, method, status, paid_at) VALUES (?, ?, ?, ?, 'posted', NOW())");
        $insertStmt->bind_param('iids', $loanId, $customerId, $amount, $method);
    } elseif (in_array('payment_date', $paymentFields, true)) {
        $insertStmt = $conn->prepare("INSERT INTO payments (loan_id, customer_id, amount, payment_date) VALUES (?, ?, ?, NOW())");
        $insertStmt->bind_param('iid', $loanId, $customerId, $amount);
    } else {
        $insertStmt = $conn->prepare("INSERT INTO payments (loan_id, customer_id, amount) VALUES (?, ?, ?)");
        $insertStmt->bind_param('iid', $loanId, $customerId, $amount);
    }

    if (!$insertStmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create payment', 'message' => $conn->error]);
        exit;
    }

    $loanSummary = syncLoanPaymentStatus($loanId);
    $totalPaid = $loanSummary['total_paid'];
    $remainingBalance = $loanSummary['remaining_balance'];

    echo json_encode(['success' => true, 'payment_id' => $conn->insert_id, 'total_paid' => $totalPaid, 'remaining_balance' => $remainingBalance, 'loan_status' => $loanSummary['status']]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
