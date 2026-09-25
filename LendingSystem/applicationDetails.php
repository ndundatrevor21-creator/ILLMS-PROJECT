<?php
/** Displays one application with role-appropriate actions and validated ownership. */
require_once 'includes/header.php';

// Require login before showing application data or role-specific actions.
// Require login for viewing application details; role-specific actions are shown below
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Validate the application id before querying its details.
// Validate loan application id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: lenderDashboard.php');
    exit;
}
$id = (int)$_GET['id'];

// Get loan application details (prepared)
$loan = null;
$loanStmt = $conn->prepare("SELECT la.* FROM loan_applications la WHERE la.id = ? LIMIT 1");
$loanStmt->bind_param('i', $id);
$loanStmt->execute();
$loanResult = $loanStmt->get_result();
if ($loanResult && $loanResult->num_rows > 0) {
    $loan = $loanResult->fetch_assoc();
}

$creditData = null;
$customer = null;
$disbursedLoan = null;
if ($loan) {
    $creditData = calculateCreditScore($loan['customer_id'], $loan['amount']);

    $customerStmt = $conn->prepare(
        "SELECT username, email, first_name, last_name, phone, city, employment_status, annual_income
         FROM users WHERE id = ? LIMIT 1"
    );
    $customerStmt->bind_param('i', $loan['customer_id']);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    if ($customerResult && $customerResult->num_rows > 0) {
        $customer = $customerResult->fetch_assoc();
    }

    // If a loan record has been created (disbursed), fetch it to show actions like payment
    $disbursedStmt = $conn->prepare("SELECT * FROM loans WHERE application_id = ? LIMIT 1");
    $disbursedStmt->bind_param('i', $id);
    $disbursedStmt->execute();
    $disbursedResult = $disbursedStmt->get_result();
    if ($disbursedResult && $disbursedResult->num_rows > 0) {
        $disbursedLoan = $disbursedResult->fetch_assoc();
    }
}


?>

    <div class="dashboard-section" style="margin-top:34px;">
        <div class="card">
            <div class="card-header">
                <h2>Loan Application Details</h2>
            </div>
            <div class="card-body">
                <?php if ($loan): ?>
                <p><strong>Loan ID:</strong> <?php echo $loan['id']; ?></p>
                <p><strong>Amount:</strong> Kshs <?php echo number_format($loan['amount'], 2); ?></p>
                <p><strong>Purpose:</strong> <?php echo $loan['purpose']; ?></p>
                <p><strong>Term:</strong> <?php echo $loan['term_months']; ?> months</p>
                <p><strong>Status:</strong> <?php echo ucfirst($loan['status']); ?></p>
                <p><strong>Date Applied:</strong> <?php echo date('M d, Y', strtotime($loan['created_at'])); ?></p>
                <?php if ($creditData): ?>
                    <p><strong>Funding Eligibility:</strong>
                        <span style="display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; background: <?php echo $creditData['score'] >= 34 ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                            <?php echo $creditData['score'] >= 34 ? 'Eligible' : 'Not Eligible'; ?>
                        </span>
                    </p>
                <?php endif; ?>

                <?php if ($creditData): ?>
                    <div style="margin-top: 20px; padding: 18px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                        <h3 style="margin-bottom: 12px;">Credit Score Assessment</h3>
                        <p style="font-size: 1.25em; font-weight: bold; margin-bottom: 5px;">
                            Score: <?php echo $creditData['score']; ?> / 100
                            <span style="padding: 3px 10px; border-radius: 4px; font-size: 0.7em; color: #fff;
                                background: <?php echo $creditData['score'] >= 67 ? '#28a745' : ($creditData['score'] >= 34 ? '#ffc107' : '#dc3545'); ?>;">
                                <?php echo $creditData['label']; ?>
                            </span>
                        </p>
                        <p style="color: #666; margin-bottom: 16px;"><?php echo htmlspecialchars($creditData['advice']); ?></p>

                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid #dee2e6;">
                                    <th style="text-align: left; padding: 6px 8px;">Factor</th>
                                    <th style="text-align: center; padding: 6px 8px;">Score</th>
                                    <th style="text-align: center; padding: 6px 8px;">Weight</th>
                                    <th style="text-align: left; padding: 6px 8px;">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 6px 8px;">Employment Status</td>
                                    <td style="text-align: center; padding: 6px 8px;"><strong><?php echo $creditData['employment_score']; ?></strong>/25</td>
                                    <td style="text-align: center; padding: 6px 8px;">25%</td>
                                    <td style="padding: 6px 8px;"><?php echo ucfirst($creditData['employment_status'] ?: 'Unknown'); ?></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 6px 8px;">Annual Income</td>
                                    <td style="text-align: center; padding: 6px 8px;"><strong><?php echo $creditData['income_score']; ?></strong>/25</td>
                                    <td style="text-align: center; padding: 6px 8px;">25%</td>
                                    <td style="padding: 6px 8px;">Kshs <?php echo number_format($creditData['annual_income'], 2); ?></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 6px 8px;">Debt-to-Income Ratio</td>
                                    <td style="text-align: center; padding: 6px 8px;"><strong><?php echo $creditData['dti_score']; ?></strong>/25</td>
                                    <td style="text-align: center; padding: 6px 8px;">25%</td>
                                    <td style="padding: 6px 8px;">DTI: <?php echo $creditData['dti_percent']; ?>% (Debt: Kshs <?php echo number_format($creditData['active_debt'], 2); ?>)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 8px;">Payment History</td>
                                    <td style="text-align: center; padding: 6px 8px;"><strong><?php echo $creditData['history_score']; ?></strong>/25</td>
                                    <td style="text-align: center; padding: 6px 8px;">25%</td>
                                    <td style="padding: 6px 8px;">Paid: <?php echo $creditData['paid_count']; ?>, Active: <?php echo $creditData['active_count']; ?>, Defaulted: <?php echo $creditData['defaulted_count']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-center">Loan application not found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>Borrower Details</h2>
            </div>
            <div class="card-body">
                <?php if ($customer): ?>
                <table>
                    <thead>
                        <tr>
                            <th>UserName</th>
                            <th>Email</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Employment status</th>
                            <th>Annual Income</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['username']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['last_name']); ?></td>
                            <td><a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" style="text-decoration: none;"><?php echo htmlspecialchars($customer['phone']); ?></a></td>
                            <td><?php echo htmlspecialchars($customer['city']); ?></td>
                            <td><?php echo htmlspecialchars($customer['employment_status']); ?></td>
                            <td>Kshs <?php echo number_format($customer['annual_income'] ?? 0, 2); ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php else: ?>
                    <p class="text-center">User details not available</p>
                <?php endif; ?>

                <div style="margin-top:18px;">
                    <?php if ($user_role === 'customer' && $disbursedLoan && $disbursedLoan['status'] === 'active'): ?>
                        <a href="makePayment.php?loan_id=<?php echo $disbursedLoan['id']; ?>" class="btn btn-success">Pay</a>
                        <a href="viewLoanDetails.php?id=<?php echo $disbursedLoan['id']; ?>" class="btn btn-secondary">View Loan</a>
                    <?php endif; ?>

                    <?php if ($user_role === 'lender'): ?>
                        <a href="requestInfo.php?id=<?php echo $loan['id']; ?>" class="btn btn-warning">Request Info</a>
                        <?php if ($loan['status'] === 'approved' && !$disbursedLoan): ?>
                            <a href="fundLoan.php?application_id=<?php echo $loan['id']; ?>" class="btn btn-primary">Fund Loan</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>