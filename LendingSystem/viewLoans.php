<?php
/** Authorized read-only loan listing with role-aware records and action links. */
require_once 'includes/header.php';

// Administrators and lenders can view the loan list.
// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'lender')) {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get loans based on role
if ($role === 'admin') {
    // Admin sees all loans
    $query = "SELECT l.*, u.first_name, u.last_name, u.email 
              FROM loans l 
              JOIN users u ON l.customer_id = u.id 
              ORDER BY l.created_at DESC";
    $stmt = $conn->prepare($query);
} else {
    // Lender sees only loans they've funded
    $query = "SELECT l.*, u.first_name, u.last_name, u.email 
              FROM loans l 
              JOIN users u ON l.customer_id = u.id 
              WHERE l.lender_id = ? 
              ORDER BY l.created_at DESC";
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
            <h1>Manage Loans</h1>
            <?php if ($role === 'admin'): ?>
            <div class="action-buttons">
                <a href="createLoan.php" class="btn btn-primary">Create New Loan</a>
                <a href="loanApplications.php" class="btn btn-secondary">View Applications</a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search loans...">
                <button type="button"><i class="fas fa-search"></i></button>
            </div>
            <div class="filter-options">
                <select id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="paid">Paid</option>
                    <option value="defaulted">Defaulted</option>
                </select>
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
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($loans) > 0): ?>
                        <?php foreach ($loans as $loan): ?>
                            <tr data-status="<?php echo htmlspecialchars($loan['status']); ?>">
                                <td><?php echo htmlspecialchars($loan['id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?>
                                    <span class="email"><?php echo htmlspecialchars($loan['email']); ?></span>
                                </td>
                                <td>Kshs <?php echo number_format($loan['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                <td><?php echo htmlspecialchars($loan['term_months']); ?> months</td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($loan['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($loan['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="viewLoanDetails.php?id=<?php echo $loan['id']; ?>" class="btn-action view">View</a>
                                    <?php if ($role === 'admin'): ?>
                                        <a href="editLoan.php?id=<?php echo $loan['id']; ?>" class="btn-action edit">Edit</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-records">No loans found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>