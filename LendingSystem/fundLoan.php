<?php
/** Lender funding handler that atomically validates an application and creates one active loan. */
session_start();
require_once __DIR__ . '/config/database.php';

// Funding actions are restricted to authenticated lenders.
// Check if user is logged in and is lender
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lender') {
    header('Location: signin.php');
    exit;
}

// Accept both URL parameter names used by older and newer links.
// Check if loan ID is provided (support both legacy and detail-page parameter names)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $loanId = (int)$_GET['id'];
} elseif (isset($_GET['application_id']) && is_numeric($_GET['application_id'])) {
    $loanId = (int)$_GET['application_id'];
} else {
    header('Location: lenderDashboard.php');
    exit;
}

$lenderId = $_SESSION['user_id'];

// Lock the application while checking and creating its one loan record.
$conn->begin_transaction();

// First verify the loan application exists and is in a valid funding state
$verifyQuery = "SELECT la.*, u.username as borrower_name 
                FROM loan_applications la
                JOIN users u ON la.customer_id = u.id
                WHERE la.id = ? AND la.status IN ('pending', 'needs_information', 'approved')
                FOR UPDATE";
$stmt = $conn->prepare($verifyQuery);
$stmt->bind_param("i", $loanId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->rollback();
    header('Location: lenderDashboard.php');
    exit;
}

$loanData = $result->fetch_assoc();
$result->free();
$stmt->close();
$applicationId = $loanData['id'];

// A second funding request must never create another loan for this application.
$existingLoanStmt = $conn->prepare("SELECT id FROM loans WHERE application_id = ? LIMIT 1");
$existingLoanStmt->bind_param("i", $applicationId);
$existingLoanStmt->execute();
$existingLoanResult = $existingLoanStmt->get_result();
$loanAlreadyExists = $existingLoanResult->num_rows > 0;
$existingLoanResult->free();
$existingLoanStmt->close();
if ($loanAlreadyExists) {
    $conn->rollback();
    header('Location: lenderDashboard.php');
    exit;
}

$customerId = $loanData['customer_id'];
$amount = $loanData['amount'];
$termMonths = $loanData['term_months'];

// Serialize approvals for the same borrower, even when they use different applications.
$borrowerLockStmt = $conn->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
$borrowerLockStmt->bind_param("i", $customerId);
$borrowerLockStmt->execute();
$borrowerLockResult = $borrowerLockStmt->get_result();
$borrowerLockResult->free();
$borrowerLockStmt->close();

// Do not approve another application while this borrower has an active loan.
$activeLoanStmt = $conn->prepare("SELECT id FROM loans WHERE customer_id = ? AND status = 'active' LIMIT 1");
$activeLoanStmt->bind_param("i", $customerId);
$activeLoanStmt->execute();
$activeLoanResult = $activeLoanStmt->get_result();
$hasActiveLoan = $activeLoanResult->num_rows > 0;
$activeLoanResult->free();
$activeLoanStmt->close();
if ($hasActiveLoan) {
    $conn->rollback();
    header('Location: lenderDashboard.php');
    exit;
}

$customerRecordStmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
$customerRecordStmt->bind_param("i", $customerId);
$customerRecordStmt->execute();
$customerRecordResult = $customerRecordStmt->get_result();
if ($customerRecordResult->num_rows === 0) {
    $customerInsertStmt = $conn->prepare("INSERT INTO customers (id, created_at) VALUES (?, NOW())");
    $customerInsertStmt->bind_param("i", $customerId);
    $customerInsertStmt->execute();
    $customerInsertStmt->close();
}
$customerRecordResult->free();
$customerRecordStmt->close();

$lenderRecordStmt = $conn->prepare("SELECT id FROM lenders WHERE id = ?");
$lenderRecordStmt->bind_param("i", $lenderId);
$lenderRecordStmt->execute();
$lenderRecordResult = $lenderRecordStmt->get_result();
if ($lenderRecordResult->num_rows === 0) {
    $lenderInsertStmt = $conn->prepare("INSERT INTO lenders (id, created_at) VALUES (?, NOW())");
    $lenderInsertStmt->bind_param("i", $lenderId);
    $lenderInsertStmt->execute();
    $lenderInsertStmt->close();
}
$lenderRecordResult->free();
$lenderRecordStmt->close();

$offerId = null;
$minimumFundingScore = 34;
$creditData = calculateCreditScore($customerId, $amount);

// ── Risk-based funding decision ────────────────────────────────────
if ($creditData['score'] < $minimumFundingScore) {
    // HIGH RISK — block funding and reject the application
    $conn->begin_transaction();
    try {
        $rejectQuery = "UPDATE loan_applications SET status = 'rejected' WHERE id = ?";
        $rejectStmt = $conn->prepare($rejectQuery);
        $rejectStmt->bind_param("i", $loanId);
        $rejectStmt->execute();
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }

    $error = "Credit score {$creditData['score']}/100 — High Risk. Funding is blocked for this borrower.";
} else {
    // Use the shared, score-based rate policy so every lender gets the same fair result.
    $interestRate = determineLoanInterestRate($creditData['score']);

    if ($interestRate > 10.0) {
        $warning = "Credit score {$creditData['score']}/100 — Medium Risk. Loan funded at a higher interest rate of {$interestRate}%.";
    }

    $createOfferStmt = $conn->prepare("INSERT INTO loan_offers (lender_id, title, amount, interest_rate, term_months, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
    $title = "Auto-funded loan offer";
    $description = "Offer created automatically during funding";
    $createOfferStmt->bind_param("isddis", $lenderId, $title, $amount, $interestRate, $termMonths, $description);
    $createOfferStmt->execute();
    $offerId = $conn->insert_id;

    try {
        // 1. Update loan application status to approved
        $updateApplicationQuery = "UPDATE loan_applications SET status = 'approved' WHERE id = ?";
        $stmt = $conn->prepare($updateApplicationQuery);
        $stmt->bind_param("i", $loanId);
        $stmt->execute();

        // 2. Insert into loans table
        $insertLoanQuery = "INSERT INTO loans (application_id, customer_id, lender_id, offer_id, amount, interest_rate, term_months, status, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
        $stmt = $conn->prepare($insertLoanQuery);
        $stmt->bind_param("iiiiddi", $applicationId, $customerId, $lenderId, $offerId, $amount, $interestRate, $termMonths);
        $stmt->execute();

        logSystemActivity($conn, $lenderId, $_SESSION['username'] ?? 'lender', 'fund_loan', 'Funded loan application #' . $applicationId . ' for Kshs ' . number_format((float)$amount, 2), 'loan', $applicationId, 'lender');

        // Commit transaction
        $conn->commit();
        $success = "Loan funded successfully!";
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = "Error funding loan: " . $e->getMessage();
    }
}

?>

<?php require_once 'includes/header.php'; ?>

<div class="dashboard">
    <div class="card" style="max-width: 700px; margin: 50px auto; padding: 30px;">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
            <h2>Loan Funded Successfully</h2>
            <p><strong>Loan ID:</strong> <?php echo $loanId; ?></p>
            <p><strong>Borrower:</strong> <?php echo htmlspecialchars($loanData['borrower_name']); ?></p>
            <p><strong>Amount:</strong> Kshs <?php echo number_format($amount, 2); ?></p>
            <p><strong>Interest Rate:</strong> <?php echo $interestRate; ?>%</p>
            <p><strong>Term:</strong> <?php echo $termMonths; ?> months</p>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <h2>Funding Blocked</h2>
            <p>The loan application for <strong><?php echo htmlspecialchars($loanData['borrower_name']); ?></strong> requesting <strong>Kshs <?php echo number_format($amount, 2); ?></strong> could not be funded due to high credit risk.</p>
        <?php endif; ?>

        <?php if (isset($warning) && isset($success)): ?>
            <div class="alert alert-warning" style="margin-top: 15px;">
                <?php echo $warning; ?>
            </div>
        <?php endif; ?>

        <!-- Credit Score Breakdown -->
        <div style="margin-top: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
            <h3 style="margin-bottom: 15px;">Credit Score Breakdown</h3>
            <p style="font-size: 1.3em; font-weight: bold; margin-bottom: 5px;">
                Overall Score: <?php echo $creditData['score']; ?> / 100
                <span style="padding: 3px 10px; border-radius: 4px; font-size: 0.7em; color: #fff;
                    background: <?php echo $creditData['score'] >= 67 ? '#28a745' : ($creditData['score'] >= 34 ? '#ffc107' : '#dc3545'); ?>;">
                    <?php echo $creditData['label']; ?>
                </span>
            </p>
            <p style="color: #666; margin-bottom: 20px;"><?php echo htmlspecialchars($creditData['advice']); ?></p>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #dee2e6;">
                        <th style="text-align: left; padding: 8px;">Factor</th>
                        <th style="text-align: center; padding: 8px;">Score</th>
                        <th style="text-align: center; padding: 8px;">Weight</th>
                        <th style="text-align: left; padding: 8px;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;">Employment Status</td>
                        <td style="text-align: center; padding: 8px;"><strong><?php echo $creditData['employment_score']; ?></strong>/25</td>
                        <td style="text-align: center; padding: 8px;">25%</td>
                        <td style="padding: 8px;"><?php echo ucfirst($creditData['employment_status'] ?: 'Unknown'); ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;">Annual Income</td>
                        <td style="text-align: center; padding: 8px;"><strong><?php echo $creditData['income_score']; ?></strong>/25</td>
                        <td style="text-align: center; padding: 8px;">25%</td>
                        <td style="padding: 8px;">Kshs <?php echo number_format($creditData['annual_income'], 2); ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;">Debt-to-Income Ratio</td>
                        <td style="text-align: center; padding: 8px;"><strong><?php echo $creditData['dti_score']; ?></strong>/25</td>
                        <td style="text-align: center; padding: 8px;">25%</td>
                        <td style="padding: 8px;">DTI: <?php echo $creditData['dti_percent']; ?>% (Active debt: Kshs <?php echo number_format($creditData['active_debt'], 2); ?>)</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px;">Payment History</td>
                        <td style="text-align: center; padding: 8px;"><strong><?php echo $creditData['history_score']; ?></strong>/25</td>
                        <td style="text-align: center; padding: 8px;">25%</td>
                        <td style="padding: 8px;">Paid: <?php echo $creditData['paid_count']; ?>, Active: <?php echo $creditData['active_count']; ?>, Defaulted: <?php echo $creditData['defaulted_count']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <a href="lenderDashboard.php" class="btn btn-primary" style="margin-top: 20px;">Back to Dashboard</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

