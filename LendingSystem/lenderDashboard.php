<?php
/** Lender workspace showing review queues, active loans, and links to funding actions. */
require_once 'includes/header.php';

// Only authenticated lenders may review applications and active loans.
// Check if user is logged in and is lender
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lender') {
    header('Location: signin.php');
    exit;
}

// Use the session user id to scope lender-specific results.
$user_id = $_SESSION['user_id'];

// Get lender loans
$loans_query = "SELECT l.*, u.username as borrower_name 
                FROM loans l 
                JOIN users u ON l.customer_id = u.id 
                WHERE l.lender_id = $user_id 
                ORDER BY l.created_at DESC";
$loans_result = executeQuery($loans_query);

// Get loan statistics
$stats_query = "SELECT 
                COUNT(*) as total_loans,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_loans,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_loans,
                SUM(amount) as total_lent,
                SUM(CASE WHEN status = 'active' THEN COALESCE((amount + (amount * interest_rate / 100) * (term_months / 12)) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.loan_id = loans.id), 0), 0) ELSE 0 END) as outstanding_balance
                FROM loans 
                WHERE lender_id = $user_id";
$stats_result = executeQuery($stats_query);
$stats = $stats_result->fetch_assoc();
$stats['outstanding_balance'] = (float)($stats['outstanding_balance'] ?? 0);

// Get pending, rejected, and needs_information loan applications
$pending_query = "SELECT la.*, u.username as borrower_name 
                 FROM loan_applications la 
                 JOIN users u ON la.customer_id = u.id 
                 WHERE la.status IN ('pending', 'needs_information', 'rejected')
                 AND NOT EXISTS (SELECT 1 FROM loans l2 WHERE l2.application_id = la.id)
                 ORDER BY la.created_at DESC 
                 LIMIT 4";
$pending_result = executeQuery($pending_query);

?>

<div class="dashboard lender-dashboard">
    <h1>Welcome, <?php echo $_SESSION['username']; ?>!</h1>
    
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
                <h3>Total Lent</h3>
                <p class="stat-number">Kshs <?php echo number_format($stats['total_lent'], 2); ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon balance-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-info">
                <h3>Outstanding</h3>
                <p class="stat-number">Kshs <?php echo number_format($stats['outstanding_balance'], 2); ?></p>
            </div>
        </div>
    </div>
    
    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>My Funded Loans</h2>
                <a href="activeLoans.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                            <th>Interest Rate</th>
                            <th>Term (Months)</th>
                            <th>Paid</th>
                            <th>Total Repayable</th>
                            <th>Remaining</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loan = $loans_result->fetch_assoc()): ?>
                        <?php $paidAmount = (float)($loan['paid_amount'] ?? 0); $totalInterest = (float)$loan['amount'] * ((float)($loan['interest_rate'] ?? 0) / 100) * ((int)($loan['term_months'] ?? 0) / 12); $totalRepayable = (float)$loan['amount'] + $totalInterest; $remainingBalance = max(0, $totalRepayable - $paidAmount); ?>
                        <tr>
                            <td><?php echo $loan['id']; ?></td>
                            <td><?php echo $loan['borrower_name']; ?></td>
                            <td>Kshs <?php echo number_format($loan['amount'], 2); ?></td>
                            <td><?php echo $loan['interest_rate']; ?>%</td>
                            <td><?php echo $loan['term_months']; ?></td>
                            <td>Kshs <?php echo number_format($paidAmount, 2); ?></td>
                            <td>Kshs <?php echo number_format($totalRepayable, 2); ?></td>
                            <td>Kshs <?php echo number_format($remainingBalance, 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($loan['status']); ?>">
                                    <?php echo ucfirst($loan['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                            <td>
                                <!-- No loan details page yet -->
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($loans_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No loans found</td>
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
                <h2>Pending Loan Applications</h2>
                <a href="loanApplications.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                            <th>Purpose</th>
                            <th>Term (Months)</th>
                            <th>Date</th>
                            <th>Eligibility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($application = $pending_result->fetch_assoc()): ?>
                        <?php $eligibility = calculateCreditScore($application['customer_id'], $application['amount']); ?>
                        <?php $isEligible = $eligibility['score'] >= 34; ?>
                        <tr>
                            <td><?php echo $application['id']; ?></td>
                            <td><?php echo $application['borrower_name']; ?></td>
                            <td>Kshs <?php echo number_format($application['amount'], 2); ?></td>
                            <td><?php echo $application['purpose']; ?></td>
                            <td><?php echo $application['term_months']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                            <td>
                                <?php if ($application['status'] === 'rejected'): ?>
                                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; background: #6c757d; font-size: 12px; font-weight: bold;">
                                        Rejected
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; background: <?php echo $isEligible ? '#28a745' : '#dc3545'; ?>; font-size: 12px; font-weight: bold;">
                                        <?php echo $isEligible ? 'Eligible' : 'Not Eligible'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="applicationDetails.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                <?php if ($application['status'] !== 'rejected'): ?>
                                    <a href="requestInfo.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-warning">Request Info</a>
                                    <a href="fundLoan.php?application_id=<?php echo $application['id']; ?>" class="btn btn-sm btn-success">Fund</a>
                                    <a href="rejectLoan.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($pending_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No pending or rejected applications</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>
<div class="dashboard-section">
        <div class="row">
            <div class="col-md-2">
                <div class="card">
                    <div class="card-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-action">
                            <a href="lendUpdateProfile.php" class="btn btn-secondary">Update Profile</a>
                        </div>
                    </div>
                </div>
            </div>

<?php require_once 'includes/footer.php'; ?>