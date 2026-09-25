<?php
/** Admin reporting page for filtered lending, repayment, and user activity metrics. */
require_once 'includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit;
}

// Read the optional date range used to filter report statistics.
// Loan tracking statistics
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';

$dateConditionLoans = '';
$dateConditionApplications = '';
// Basic validation and escaping
if ($from !== '' && $to !== '') {
        $fromEsc = $conn->real_escape_string($from);
        $toEsc = $conn->real_escape_string($to);
        $dateConditionLoans = "WHERE l.created_at BETWEEN '" . $fromEsc . " 00:00:00' AND '" . $toEsc . " 23:59:59'";
        $dateConditionApplications = "WHERE created_at BETWEEN '" . $fromEsc . " 00:00:00' AND '" . $toEsc . " 23:59:59'";
}

$loan_stats_query = "SELECT 
                                                COUNT(*) AS total_loans,
                                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_loans,
                                                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_loans,
                                                SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) AS defaulted_loans,
                                                AVG(interest_rate) AS avg_interest_rate,
                                                SUM(amount) AS total_loan_value
                                            FROM loans l " . $dateConditionLoans;
$loan_stats = executeQuery($loan_stats_query)->fetch_assoc();

$application_stats_query = "SELECT 
                                                             COUNT(*) AS total_applications,
                                                             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_applications,
                                                             SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_applications,
                                                             SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_applications,
                                                             AVG(amount) AS avg_requested_amount
                                                         FROM loan_applications " . $dateConditionApplications;
$application_stats = executeQuery($application_stats_query)->fetch_assoc();

// User counts (admins, lenders, customers)
$user_counts_query = "SELECT 
                                                COUNT(*) as total,
                                                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                                                SUM(CASE WHEN role = 'lender' THEN 1 ELSE 0 END) as lenders,
                                                SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers
                                            FROM users";
$user_counts = executeQuery($user_counts_query)->fetch_assoc();

// Load payment totals and the latest payment records.
// Payment metrics and recent payments
$payment_metrics_query = "SELECT COUNT(*) as total_payments, COALESCE(SUM(amount),0) as payments_collected FROM payments";
$payment_metrics = executeQuery($payment_metrics_query)->fetch_assoc();

$payment_logs_query = "SELECT p.*, u.username as payer_name, l.id as loan_id
                                             FROM payments p
                                             LEFT JOIN users u ON p.customer_id = u.id
                                             LEFT JOIN loans l ON p.loan_id = l.id
                                             ORDER BY p.paid_at DESC
                                             LIMIT 10";
$payment_logs = executeQuery($payment_logs_query);

// Loan breakdown by status
$loan_breakdown_query = "SELECT status, COUNT(*) as qty, COALESCE(SUM(amount),0) as val FROM loans GROUP BY status";
$loan_breakdown_result = executeQuery($loan_breakdown_query);

$top_lenders_query = "SELECT u.id, u.username, u.email, COUNT(l.id) AS fund_count, SUM(l.amount) AS total_funded
                       FROM loans l
                       JOIN users u ON l.lender_id = u.id
                       " . ($dateConditionLoans ? $dateConditionLoans . ' AND l.lender_id IS NOT NULL' : 'WHERE l.lender_id IS NOT NULL') . "
                       GROUP BY u.id
                       ORDER BY total_funded DESC
                       LIMIT 5";
$top_lenders_result = executeQuery($top_lenders_query);

$top_borrowers_query = "SELECT u.id, u.username, u.email, COUNT(l.id) AS loan_count, SUM(l.amount) AS total_borrowed
                        FROM loans l
                        JOIN users u ON l.customer_id = u.id
                        " . ($dateConditionLoans ? $dateConditionLoans : '') . "
                        GROUP BY u.id
                        ORDER BY total_borrowed DESC
                        LIMIT 5";
$top_borrowers_result = executeQuery($top_borrowers_query);

$recent_loans_query = "SELECT l.id, u.username AS borrower_name, ul.username AS lender_name, l.amount, l.interest_rate, l.term_months, l.status, l.created_at
                       FROM loans l
                       JOIN users u ON l.customer_id = u.id
                       LEFT JOIN users ul ON l.lender_id = ul.id
                       " . ($dateConditionLoans ? $dateConditionLoans : '') . "
                       ORDER BY l.created_at DESC
                       LIMIT 100";
$recent_loans_result = executeQuery($recent_loans_query);

// CSV export for recent loans (honors date range if provided)
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="loan_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Borrower','Lender','Amount','InterestRate','TermMonths','Status','CreatedAt']);
    while ($row = $recent_loans_result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['borrower_name'],
            $row['lender_name'],
            $row['amount'],
            $row['interest_rate'],
            $row['term_months'],
            $row['status'],
            $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}

$loan_status_labels = ['Active', 'Paid', 'Defaulted'];
$loan_status_data = [
    (int)$loan_stats['active_loans'],
    (int)$loan_stats['paid_loans'],
    (int)$loan_stats['defaulted_loans']
];

$application_status_labels = ['Pending', 'Approved', 'Rejected'];
$application_status_data = [
    (int)$application_stats['pending_applications'],
    (int)$application_stats['approved_applications'],
    (int)$application_stats['rejected_applications']
];

$top_lenders_labels = [];
$top_lenders_data = [];
while ($row = $top_lenders_result->fetch_assoc()) {
    $top_lenders_labels[] = $row['username'];
    $top_lenders_data[] = (float)$row['total_funded'];
}

$recent_loan_labels = [];
$recent_loan_amounts = [];
$recent_loans_result->data_seek(0);
while ($row = $recent_loans_result->fetch_assoc()) {
    $recent_loan_labels[] = date('M d', strtotime($row['created_at']));
    $recent_loan_amounts[] = (float)$row['amount'];
}
?>

<div class="dashboard">
    <h1>Loan Tracking & Reporting</h1>

    <div class="report-filters" style="margin-bottom:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <form method="GET" action="loanReports.php" style="display:flex;gap:8px;align-items:center;">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>

        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
            <a class="btn btn-secondary" href="loanReports.php<?php echo ($from || $to) ? '?from=' . urlencode($from) . '&to=' . urlencode($to) . '&action=export' : '?action=export'; ?>">Export CSV</a>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon loans-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3>Total Loans</h3>
                <p class="stat-number"><?php echo $loan_stats['total_loans']; ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon users-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Registrations</h3>
                <p class="stat-number"><?php echo $user_counts['total'] ?? 0; ?></p>
                <small style="color:#6c757d;"><?php echo ($user_counts['lenders'] ?? 0); ?> Lenders / <?php echo ($user_counts['customers'] ?? 0); ?> Borrowers</small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon active-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Active Loans</h3>
                <p class="stat-number"><?php echo $loan_stats['active_loans']; ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon paid-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3>Paid Loans</h3>
                <p class="stat-number"><?php echo $loan_stats['paid_loans']; ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amount-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-info">
                <h3>Payments Collected</h3>
                <p class="stat-number">Kshs <?php echo number_format($payment_metrics['payments_collected'] ?? 0, 2); ?></p>
                <small style="color:#6c757d;"><?php echo $payment_metrics['total_payments'] ?? 0; ?> payments</small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3>Defaulted Loans</h3>
                <p class="stat-number"><?php echo $loan_stats['defaulted_loans']; ?></p>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>Application Overview</h2>
            </div>
            <div class="card-body">
                <div class="report-grid">
                    <div>
                        <h4>Total Applications</h4>
                        <p><?php echo $application_stats['total_applications']; ?></p>
                    </div>
                    <div>
                        <h4>Pending</h4>
                        <p><?php echo $application_stats['pending_applications']; ?></p>
                    </div>
                    <div>
                        <h4>Approved</h4>
                        <p><?php echo $application_stats['approved_applications']; ?></p>
                    </div>
                    <div>
                        <h4>Rejected</h4>
                        <p><?php echo $application_stats['rejected_applications']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="charts-grid">
            <div class="card">
                <div class="card-header">
                    <h2>Loan Status Distribution</h2>
                </div>
                <div class="card-body">
                    <canvas id="loanStatusChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Application Status Distribution</h2>
                </div>
                <div class="card-body">
                    <canvas id="applicationStatusChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Top Lenders by Funding</h2>
                </div>
                <div class="card-body">
                    <canvas id="topLendersChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Recent Loan Funding</h2>
                </div>
                <div class="card-body">
                    <canvas id="recentLoansChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px;">
            <div class="card">
                <div class="card-header"><h2>Loans Portfolio Breakdown</h2></div>
                <div class="card-body">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #eee;">
                                <th style="padding:10px;text-align:left;">Status</th>
                                <th style="padding:10px;text-align:right;">Count</th>
                                <th style="padding:10px;text-align:right;">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $loan_breakdown_result->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px; font-weight:500;"><?php echo ucfirst($row['status']); ?></td>
                                <td style="padding:10px; text-align:right;"><?php echo $row['qty']; ?></td>
                                <td style="padding:10px; text-align:right; font-weight:bold;">Kshs <?php echo number_format($row['val'],2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Recent Payments Stream</h2></div>
                <div class="card-body">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #eee;">
                                <th style="padding:10px;text-align:left;">Receipt</th>
                                <th style="padding:10px;text-align:left;">Payer</th>
                                <th style="padding:10px;text-align:left;">Loan #</th>
                                <th style="padding:10px;text-align:right;">Amount</th>
                                <th style="padding:10px;text-align:left;">Method</th>
                                <th style="padding:10px;text-align:left;">Paid At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $payment_logs->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px;">#<?php echo $p['id']; ?></td>
                                <td style="padding:10px; font-weight:600;"><?php echo htmlspecialchars($p['payer_name']); ?></td>
                                <td style="padding:10px; color:#6c757d;"><?php echo $p['loan_id'] ? 'Loan #' . $p['loan_id'] : '-'; ?></td>
                                <td style="padding:10px; text-align:right; font-weight:bold;">Kshs <?php echo number_format($p['amount'],2); ?></td>
                                <td style="padding:10px;"><?php echo htmlspecialchars($p['method'] ?? 'manual'); ?></td>
                                <td style="padding:10px; color:#6c757d;"><?php echo date('M d, Y H:i', strtotime($p['paid_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const loanStatusCtx = document.getElementById('loanStatusChart').getContext('2d');
new Chart(loanStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($loan_status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($loan_status_data); ?>,
            backgroundColor: ['#007bff', '#28a745', '#dc3545'],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

const applicationStatusCtx = document.getElementById('applicationStatusChart').getContext('2d');
new Chart(applicationStatusCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($application_status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($application_status_data); ?>,
            backgroundColor: ['#ffc107', '#17a2b8', '#6c757d'],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

const topLendersCtx = document.getElementById('topLendersChart').getContext('2d');
new Chart(topLendersCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($top_lenders_labels); ?>,
        datasets: [{
            label: 'Total Funded (Kshs)',
            data: <?php echo json_encode($top_lenders_data); ?>,
            backgroundColor: '#17a2b8'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

const recentLoansCtx = document.getElementById('recentLoansChart').getContext('2d');
new Chart(recentLoansCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($recent_loan_labels); ?>,
        datasets: [{
            label: 'Loan Amount',
            data: <?php echo json_encode($recent_loan_amounts); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.3)',
            borderColor: '#28a745',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<script>
// Simple client-side search for recent loans table
document.getElementById('searchLoans').addEventListener('input', function (e) {
    const q = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.indexOf(q) > -1 ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>