<?php
/** Detailed lender view of one validated application or funded loan. */
require_once 'includes/header.php';

// Check if user is logged in and is lender
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];




// Get pending loan applications
$pending_query = "SELECT la.*, u.username as borrower_name 
                 FROM loan_applications la 
                 JOIN users u ON la.customer_id = u.id 
                 WHERE la.status = 'pending' 
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
                        
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($application = $pending_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $application['id']; ?></td>
                            <td><?php echo $application['borrower_name']; ?></td>
                            <td>Kshs <?php echo number_format($application['amount'], 2); ?></td>
                            <td><?php echo $application['purpose']; ?></td>
                            <td><?php echo $application['term_months']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>

                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($pending_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No pending applications</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>