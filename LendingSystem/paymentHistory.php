<?php
/** Read-only payment history scoped to the authenticated borrower. */
require_once 'includes/header.php';

// Payment history is private to the authenticated borrower.
// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all payments for the user
$query = "SELECT p.*, l.amount as loan_amount 
          FROM payments p 
          JOIN loans l ON p.loan_id = l.id 
          WHERE p.customer_id = ? 
          ORDER BY p.paid_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="dashboard">
    <div class="container">
        <h1>Payment History</h1>
        
        <div class="card">
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Loan ID</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo $payment['loan_id']; ?></td>
                            <td>Kshs <?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($payment['method'] ?? 'manual'); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($payment['paid_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center">No payments found</p>
                <?php endif; ?>
            </div>
        </div>
        
        <a href="borrowerDashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
