<?php
/** Read-only customer loan history scoped to the authenticated borrower id. */
require_once 'includes/header.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT la.*, l.id AS loan_id, l.amount AS funded_amount, l.status AS loan_status, l.interest_rate, l.term_months AS loan_term_months, l.created_at AS loan_created_at
          FROM loan_applications la
          LEFT JOIN loans l ON l.application_id = la.id
          WHERE la.customer_id = ?
          ORDER BY la.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="dashboard">
    <div class="container">
        <h1>Loan History</h1>

        <div class="card">
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Requested Amount</th>
                            <th>Funded Amount</th>
                            <th>Purpose</th>
                            <th>Application Status</th>
                            <th>Loan Status</th>
                            <th>Applied On</th>
                            <th>Loan Disbursed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loan = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $loan['id']; ?></td>
                            <td>Kshs <?php echo number_format($loan['amount'], 2); ?></td>
                            <td><?php echo $loan['funded_amount'] ? 'Kshs ' . number_format($loan['funded_amount'], 2) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($loan['purpose']); ?></td>
                            <td><?php echo ucfirst($loan['status']); ?></td>
                            <td><?php echo $loan['loan_id'] ? ucfirst($loan['loan_status']) : '-'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                            <td><?php echo $loan['loan_created_at'] ? date('M d, Y', strtotime($loan['loan_created_at'])) : '-'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center">No loan history found.</p>
                <?php endif; ?>
            </div>
        </div>

        <a href="borrowerDashboard.php" class="btn btn-primary">Back to Dashboard</a>
        <a href="paymentHistory.php" class="btn btn-secondary" style="margin-left: 10px;">View Payments</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>