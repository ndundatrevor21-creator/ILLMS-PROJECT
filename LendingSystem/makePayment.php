<?php
/** Customer repayment form and handler validating ownership, balance, and payment data. */
require_once 'includes/header.php';

// Payments are restricted to authenticated borrowers.
// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Identify the loan being paid before loading its balance.
// Check if loan ID is provided
if (!isset($_GET['loan_id']) || !is_numeric($_GET['loan_id'])) {
    header('Location: borrowerDashboard.php');
    exit;
}

$loan_id = $_GET['loan_id'];

// Verify loan belongs to borrower and is still payable
$query = "SELECT * FROM loans WHERE id = ? AND customer_id = ? AND status IN ('active', 'approved')";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $loan_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: borrowerDashboard.php');
    exit;
}

$loan = $result->fetch_assoc();
$loanSummary = syncLoanPaymentStatus($loan_id);
if ($loanSummary['remaining_balance'] <= 0) {
    header('Location: borrowerDashboard.php');
    exit;
}

// Total repayment must include principal + total interest, not a separate monthly installment.
$annualRate = (float)($loan['interest_rate'] ?? 0);
$termMonths = (int)($loan['term_months'] ?? 0);
$totalInterest = $loan['amount'] * ($annualRate / 100) * ($termMonths / 12);
$totalRepayable = $loan['amount'] + $totalInterest;
$remainingBalance = max(0, $loanSummary['remaining_balance']);

// Process payment form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $method = isset($_POST['method']) ? trim($_POST['method']) : 'manual';
    
    // Validate input
    if (empty($amount)) {
        $error = 'Please fill in all fields';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif ((float)$amount > $remainingBalance) {
        $error = 'Payment cannot exceed the remaining balance of Kshs ' . number_format($remainingBalance, 2);
    } else {
        $amount = (float)$amount;
        $paymentColumns = $conn->query("SHOW COLUMNS FROM payments");
        $paymentFields = [];
        if ($paymentColumns) {
            while ($column = $paymentColumns->fetch_assoc()) {
                $paymentFields[] = $column['Field'];
            }
        }

        if (in_array('method', $paymentFields, true) && in_array('status', $paymentFields, true) && in_array('paid_at', $paymentFields, true)) {
            $query = "INSERT INTO payments (loan_id, customer_id, amount, method, status, paid_at) VALUES (?, ?, ?, ?, 'posted', NOW())";
            $bindTypes = "iids";
        } elseif (in_array('payment_date', $paymentFields, true)) {
            $query = "INSERT INTO payments (loan_id, customer_id, amount, payment_date) VALUES (?, ?, ?, NOW())";
            $bindTypes = "iid";
        } else {
            $query = "INSERT INTO payments (loan_id, customer_id, amount) VALUES (?, ?, ?)";
            $bindTypes = "iid";
        }

        $stmt = $conn->prepare($query);
        if ($bindTypes === 'iids') {
            $stmt->bind_param($bindTypes, $loan_id, $user_id, $amount, $method);
        } else {
            $stmt->bind_param($bindTypes, $loan_id, $user_id, $amount);
        }

        if ($stmt->execute()) {
            logSystemActivity($conn, $user_id, $_SESSION['username'] ?? 'customer', 'loan_payment', 'Made payment of Kshs ' . number_format((float)$amount, 2) . ' for loan #' . $loan_id, 'loan', $loan_id, 'customer');

            // Send receipt email if possible
            $emailStmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
            $emailStmt->bind_param('i', $user_id);
            $emailStmt->execute();
            $emailRes = $emailStmt->get_result();
            if ($emailRes && $row = $emailRes->fetch_assoc()) {
                $to = $row['email'];
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $row['email'];
                $subject = "Payment receipt for Loan #{$loan_id}";
                $body = "Hello {$name},\n\nWe have received your payment of Kshs " . number_format((float)$amount,2) . " for Loan #{$loan_id}.\n\nThank you.\n";
                @mail($to, $subject, $body);
            }
            $loanSummary = syncLoanPaymentStatus($loan_id);
            $total_paid = $loanSummary['total_paid'];
            $remaining_balance = $loanSummary['remaining_balance'];
            $loan['status'] = $loanSummary['status'];

            $success = 'Payment processed successfully!';
        } else {
            $error = 'Failed to process payment: ' . $conn->error;
        }
    }
}
?>

<div class="dashboard">
    <div class="container" style="max-width: 600px; margin: 30px auto;">
        <h1>Make a Payment</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Loan #<?php echo $loan_id; ?></h2>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600;">Loan Amount:</span>
                        <span>Kshs <?php echo number_format($loan['amount'], 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600;">Total Interest:</span>
                        <span>Kshs <?php echo number_format($totalInterest, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600;">Total Repayable:</span>
                        <span>Kshs <?php echo number_format($totalRepayable, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600;">Total Paid:</span>
                        <span>Kshs <?php echo number_format((float)($loan['status'] === 'paid' ? $totalRepayable : (isset($loanSummary) ? $loanSummary['total_paid'] : 0)), 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-weight: 600;">Remaining Balance:</span>
                        <span>Kshs <?php echo number_format((isset($loanSummary) ? $loanSummary['remaining_balance'] : $totalRepayable), 2); ?></span>
                    </div>
                </div>
                
                <form method="POST" action="makePayment.php?loan_id=<?php echo $loan_id; ?>">
                    <div class="form-group">
                        <label for="amount">Payment Amount (Kshs)</label>
                        <input type="number" id="amount" name="amount" class="form-control" min="1" step="0.01" value="<?php echo number_format($remainingBalance > 0 ? $remainingBalance : 0, 2, '.', ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="method">Payment Method</label>
                        <select id="method" name="method" class="form-control">
                            <option value="manual">Manual</option>
                            <option value="paypal">PayPal</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Process Payment</button>
                        <a href="borrowerDashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
