<?php
/** Admin dashboard: loads read-only system aggregates and renders management links. */
require_once 'includes/header.php';

// The dashboard is restricted to administrators.
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit;
}

// Collect the summary numbers displayed in the dashboard cards.
// Get statistics
$users_query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as total_customers,
                    SUM(CASE WHEN role = 'lender' THEN 1 ELSE 0 END) as total_lenders,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30days
                  FROM users";
$users_result = executeQuery($users_query);
$users_data = $users_result->fetch_assoc();
$users_count = $users_data['total_users'];
$total_customers = $users_data['total_customers'];
$total_lenders = $users_data['total_lenders'];
$new_users_30days = $users_data['new_users_30days'];

$loan_applications_query = "SELECT 
                              COUNT(*) as total_applications,
                              SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                              SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                              SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                              SUM(amount) as total_requested
                            FROM loan_applications";
$loan_applications_result = executeQuery($loan_applications_query);
$loan_applications_data = $loan_applications_result->fetch_assoc();
$total_applications = $loan_applications_data['total_applications'] ?? 0;
$pending_applications = $loan_applications_data['pending_applications'] ?? 0;
$approved_applications = $loan_applications_data['approved_applications'] ?? 0;
$rejected_applications = $loan_applications_data['rejected_applications'] ?? 0;
$total_requested_amount = $loan_applications_data['total_requested'] ?? 0;

$loan_summary_query = "SELECT 
                        COUNT(*) as total_loans,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_loans,
                        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_loans,
                        SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted_loans,
                        SUM(amount) as total_loan_amount
                      FROM loans";
$loan_summary_result = executeQuery($loan_summary_query);
$loan_summary_data = $loan_summary_result->fetch_assoc();
$total_loans = $loan_summary_data['total_loans'] ?? 0;
$active_loans = $loan_summary_data['active_loans'] ?? 0;
$paid_loans = $loan_summary_data['paid_loans'] ?? 0;
$defaulted_loans = $loan_summary_data['defaulted_loans'] ?? 0;
$total_loan_amount = $loan_summary_data['total_loan_amount'] ?? 0;

$recent_users_query = "SELECT id, username, email, role, created_at 
                       FROM users 
                       ORDER BY created_at DESC 
                       LIMIT 5";
$recent_users_result = executeQuery($recent_users_query);

$recent_applications_query = "SELECT la.id, la.amount, la.status, la.created_at, u.username as borrower_name
                              FROM loan_applications la
                              JOIN users u ON la.customer_id = u.id
                              ORDER BY la.created_at DESC
                              LIMIT 5";
$recent_applications_result = executeQuery($recent_applications_query);

?>

<div class="dashboard">
    <h1>Admin Dashboard</h1>
    
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon users-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Users</h3>
                <p class="stat-number"><?php echo $users_count; ?></p>
                <small><?php echo $total_customers; ?> borrowers, <?php echo $total_lenders; ?> lenders</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon loans-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-info">
                <h3>Loan Requests</h3>
                <p class="stat-number"><?php echo $total_applications; ?></p>
                <small><?php echo $pending_applications; ?> pending</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon amount-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3>Total Loan Value</h3>
                <p class="stat-number">Kshs <?php echo number_format($total_loan_amount, 2); ?></p>
                <small><?php echo number_format($total_requested_amount, 2); ?> requested</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <h3>System Alerts</h3>
                <p class="stat-number"><?php echo $defaulted_loans; ?></p>
                <small>Defaulted loans</small>
            </div>
        </div>
    </div>
    
    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>Recent Loan Applications</h2>
                <a href="loanApplications.php" class="btn btn-primary btn-sm">Review All</a>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($loan = $recent_applications_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $loan['id']; ?></td>
                            <td><?php echo $loan['borrower_name']; ?></td>
                            <td>Kshs <?php echo number_format($loan['amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($loan['status']); ?>">
                                    <?php echo ucfirst($loan['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($recent_applications_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">No loan applications found</td>
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
                            <a href="users.php" class="btn btn-primary">Manage Users</a>
                            <a href="loanApplications.php" class="btn btn-success">Review Applications</a>
                            <a href="viewLoans.php" class="btn btn-secondary">View Loans</a>
                            <a href="loanReports.php" class="btn btn-info">Loan Reports</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h2>System Insights</h2>
                    </div>
                    <div class="card-body">
                        <div class="system-status">
                            <div class="status-item">
                                <span class="status-label">New users (30d):</span>
                                <span class="status-value"><?php echo $new_users_30days; ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">Active loans:</span>
                                <span class="status-value"><?php echo $active_loans; ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">Defaulted loans:</span>
                                <span class="status-value status-warning"><?php echo $defaulted_loans; ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">Pending applications:</span>
                                <span class="status-value status-warning"><?php echo $pending_applications; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>Recent Users</h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $recent_users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst($user['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if ($recent_users_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">No recent users found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>