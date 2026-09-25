<?php
/** Displays role-scoped active loans; this page reads data and renders escaped HTML. */
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get active loans based on role
$baseLoanQuery = "SELECT l.*, u.first_name, u.last_name, u.email,
                         COALESCE(last_payment.last_paid, l.created_at) AS last_reference_date,
                         COALESCE(paid.total_paid, 0) AS paid_amount
                  FROM loans l
                  JOIN users u ON l.customer_id = u.id
                  LEFT JOIN (
                      SELECT loan_id, MAX(paid_at) AS last_paid
                      FROM payments
                      WHERE status = 'posted'
                      GROUP BY loan_id
                  ) last_payment ON last_payment.loan_id = l.id
                  LEFT JOIN (
                      SELECT loan_id, SUM(amount) AS total_paid
                      FROM payments
                      GROUP BY loan_id
                  ) paid ON paid.loan_id = l.id
                  WHERE l.status = 'active'";

if ($role === 'admin') {
    $query = $baseLoanQuery . " ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($query);
} elseif ($role === 'lender') {
    $query = $baseLoanQuery . " AND l.lender_id = ? ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
} else {
    $query = $baseLoanQuery . " AND l.customer_id = ? ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$loans = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="view-loans">
    <div class="container">
        <div class="page-header">
            <h1>Active Loans</h1>
            <div class="action-buttons">
                <a href="<?php echo $role; ?>Dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <?php if ($role === 'admin'): ?>
                <a href="viewLoans.php" class="btn btn-primary">View All Loans</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search active loans...">
                <button type="button"><i class="fas fa-search"></i></button>
            </div>
        </div>
        
        <div class="loans-table-container">
            <table class="loans-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Interest Rate</th>
                        <th>Term</th>
                        <th>Paid</th>
                        <th>Remaining Balance</th>
                        <th>Start Date</th>
                        <th>Next Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($loans) > 0): ?>
                        <?php foreach ($loans as $loan): ?>
                            <?php $loanPaid = (float)($loan['paid_amount'] ?? 0); $totalInterest = (float)$loan['amount'] * ((float)($loan['interest_rate'] ?? 0) / 100) * ((int)($loan['term_months'] ?? 0) / 12); $totalRepayable = (float)$loan['amount'] + $totalInterest; $remainingBalance = max(0, $totalRepayable - $loanPaid); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($loan['id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>
                                    <span class="email"><?php echo htmlspecialchars($loan['email']); ?></span>
                                </td>
                                <td>Kshs <?php echo number_format($loan['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                <td><?php echo htmlspecialchars($loan['term_months']); ?> months</td>
                                <td>Kshs <?php echo number_format($loanPaid, 2); ?></td>
                                <td>Kshs <?php echo number_format($remainingBalance, 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                <td>
                                    <?php 
                                    $next_reference = $loan['last_reference_date'];
                                    $next_payment = date('M d, Y', strtotime($next_reference . ' +1 month'));
                                    echo $next_payment;
                                    ?>
                                </td>
                                <td class="actions">
                                    <a href="viewLoanDetails.php?id=<?php echo $loan['id']; ?>" class="btn-action view">View</a>
                                    <?php if ($role === 'customer'): ?>
                                        <a href="makePayment.php?loan_id=<?php echo $loan['id']; ?>" class="btn-action pay">Pay</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-records">No active loans found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>