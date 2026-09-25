<?php
/** Borrower dashboard scoped to the session user and rendered as read-only summaries. */
require_once 'includes/header.php';

// Only authenticated borrowers may view their loan dashboard.
// Check if user is logged in and is borrower
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: signin.php');
    exit;
}

// Use the session user id to load only this borrower's records.
$user_id = $_SESSION['user_id'];

// Get borrower loans
$loans_stmt = $conn->prepare(
    "SELECT l.*, la.purpose, COALESCE(paid.total_paid, 0) AS paid_amount
     FROM loans l
     LEFT JOIN loan_applications la ON l.application_id = la.id
     LEFT JOIN (
         SELECT loan_id, SUM(amount) AS total_paid FROM payments GROUP BY loan_id
     ) paid ON paid.loan_id = l.id
     WHERE l.customer_id = ?
     ORDER BY l.created_at DESC"
);
$loans_stmt->bind_param("i", $user_id);
$loans_stmt->execute();
$loans_result = $loans_stmt->get_result();

$applications_stmt = $conn->prepare(
    "SELECT la.*, l.id AS loan_id, l.status AS loan_status, l.amount AS loan_amount, l.created_at AS loan_created_at
     FROM loan_applications la
     LEFT JOIN loans l ON l.application_id = la.id
     WHERE la.customer_id = ?
     ORDER BY la.created_at DESC"
);
$applications_stmt->bind_param("i", $user_id);
$applications_stmt->execute();
$applications_result = $applications_stmt->get_result();

// Get loan statistics
$stats_stmt = $conn->prepare(
    "SELECT 
        COUNT(*) AS total_loans,
        SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) AS active_loans,
        SUM(CASE WHEN l.status = 'paid' THEN 1 ELSE 0 END) AS paid_loans,
        SUM(l.amount) AS total_borrowed,
        SUM(GREATEST(0, l.amount + (l.amount * l.interest_rate / 100) * (l.term_months / 12) - COALESCE(paid.total_paid, 0))) AS outstanding_balance
     FROM loans l
     LEFT JOIN (
         SELECT loan_id, SUM(amount) AS total_paid FROM payments GROUP BY loan_id
     ) paid ON l.id = paid.loan_id
     WHERE l.customer_id = ?"
);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats['total_borrowed'] = $stats['total_borrowed'] ?: 0;
$stats['outstanding_balance'] = $stats['outstanding_balance'] ?: 0;
?>

<div class="dashboard borrower-dashboard">
    <div class="dashboard-intro" style="margin-bottom: 20px;">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p style="max-width: 760px; color: #555;">
            This page helps you manage your active loan commitments, review repayment status, and respond to lender requests. If you don't have any active loans yet, start by applying for a new loan.
        </p>
    </div>
    
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon loans-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-info">
                <h3>Total Loans</h3>
                <p class="stat-number"><?php echo $stats['total_loans']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon active-icon">
                <i class="fas fa-sync"></i>
            </div>
            <div class="stat-info">
                <h3>Active Loans</h3>
                <p class="stat-number"><?php echo $stats['active_loans']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon paid-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Paid Loans</h3>
                <p class="stat-number"><?php echo $stats['paid_loans']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon amount-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3>Total Borrowed</h3>
                <p class="stat-number">Kshs <?php echo number_format($stats['total_borrowed'], 2); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon balance-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-info">
                <h3>Outstanding Balance</h3>
                <p class="stat-number">Kshs <?php echo number_format($stats['outstanding_balance'], 2); ?></p>
            </div>
        </div>
    </div>
    
    <div class="dashboard-tip" style="margin-bottom: 30px; padding: 20px; border: 1px solid #e8e8e8; border-radius: 8px; background: #fafafa;">
        <strong>Tip:</strong> Use the <em>Pay</em> button only for active loans. If a lender asks for more information, view the messages and respond promptly to keep your application moving.
    </div>
    
    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>My Loans</h2>
                <a href="applyLoan.php" class="btn btn-primary btn-sm">Apply for Loan</a>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Purpose</th>
                            <th>Amount</th>
                            <th>Interest Rate</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Outstanding</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loan = $loans_result->fetch_assoc()): ?>
                        <?php $loanPaid = (float)($loan['paid_amount'] ?? 0); $totalInterest = (float)$loan['amount'] * ((float)($loan['interest_rate'] ?? 0) / 100) * ((int)($loan['term_months'] ?? 0) / 12); $totalRepayable = (float)$loan['amount'] + $totalInterest; $outstanding = max(0, $totalRepayable - $loanPaid); ?>
                        <tr>
                            <td><?php echo $loan['id']; ?></td>
                            <td><?php echo htmlspecialchars($loan['purpose'] ?? 'Loan'); ?></td>
                            <td>Kshs <?php echo number_format($loan['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($loan['interest_rate'] ?? 0); ?>% Annual Interest</td>
                            <td><?php echo $loan['term_months']; ?> months</td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($loan['status']); ?>">
                                    <?php echo ucfirst($loan['status']); ?>
                                </span>
                            </td>
                            <td>
                                Kshs <?php echo number_format($loan['status'] === 'paid' ? 0 : $outstanding, 2); ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                            <td>
                                <a href="borrowerUpdateProfile.php" class="btn btn-sm btn-primary">Profile</a>
                                <?php if ($loan['status'] === 'needs_information'): ?>
                                <a href="respondToRequest.php?id=<?php echo $loan['application_id']; ?>" class="btn btn-sm btn-warning">View Messages</a>
                                <?php endif; ?>
                                <?php $payable = in_array($loan['status'], ['active', 'approved'], true) && $outstanding > 0; ?>
                                <?php if ($payable): ?>
                                <a href="makePayment.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success">Pay</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($loans_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No loans found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>My Loan Applications</h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Amount</th>
                            <th>Purpose</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Loan Record</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($application = $applications_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $application['id']; ?></td>
                            <td>Kshs <?php echo number_format($application['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($application['purpose']); ?></td>
                            <td><?php echo $application['term_months']; ?> months</td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $application['status'])); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($application['loan_id']): ?>
                                    <?php echo ucfirst($application['loan_status']); ?>
                                <?php else: ?>
                                    Not funded yet
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if ($applications_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No loan applications found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="paymentHistory.php" class="btn btn-info">Payment History</a>
                            <a href="borrowerUpdateProfile.php" class="btn btn-primary">Update Profile</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>Account Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="account-info">
                            <div class="info-item">
                                <span class="info-label">Username:</span>
                                <span class="info-value"><?php echo $_SESSION['username']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Account Type:</span>
                                <span class="info-value">Borrower</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Member Since:</span>
                                <span class="info-value"><?php echo date('M d, Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
