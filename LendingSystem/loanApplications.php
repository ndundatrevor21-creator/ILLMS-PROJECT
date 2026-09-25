<?php
/** Read-only lender application list with borrower context and action links. */
require_once 'includes/header.php';

// Check if user is logged in and is lender
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lender') {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];




// Load pending applications so lenders can review and act on them.
// Get pending loan applications
$pending_query = "SELECT la.*, u.username as borrower_name 
                 FROM loan_applications la 
                 JOIN users u ON la.customer_id = u.id 
                 WHERE la.status = 'pending'
                 AND NOT EXISTS (SELECT 1 FROM loans l2 WHERE l2.application_id = la.id)
                 ORDER BY la.created_at DESC 
                 LIMIT 100";
$pending_result = executeQuery($pending_query);

?>

    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>ALL Pending Loan Applications</h2>
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
                                <span style="display:inline-block; padding:4px 8px; border-radius:4px; color:#fff; background: <?php echo $isEligible ? '#28a745' : '#dc3545'; ?>; font-size: 12px; font-weight: bold;">
                                    <?php echo $isEligible ? 'Eligible' : 'Not Eligible'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="applicationDetails.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                <a href="requestInfo.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-warning">Request Info</a>
                                <a href="fundLoan.php?application_id=<?php echo $application['id']; ?>" class="btn btn-sm btn-success">Fund</a>
                                <a href="rejectLoan.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($pending_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center">No pending applications</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>