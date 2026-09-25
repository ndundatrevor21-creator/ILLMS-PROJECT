<?php
/** Admin-only manual loan creation form with validated borrower, lender, amount, and term inputs. */
require_once 'includes/header.php';

// Manual loan creation is an administrative action.
// Only admins may create loans manually
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit;
}

$error = '';
$success = '';

// Populate the borrower and lender selectors used by the form.
// Load customers and lenders for form selects
$customers = [];
$lenders = [];
$custRes = $conn->query("SELECT id, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS name, email FROM users WHERE role = 'customer' ORDER BY first_name, last_name");
if ($custRes) {
    while ($r = $custRes->fetch_assoc()) {
        $customers[] = $r;
    }
}

$lenderRes = $conn->query("SELECT id, username, email FROM users WHERE role = 'lender' ORDER BY username");
if ($lenderRes) {
    while ($r = $lenderRes->fetch_assoc()) {
        $lenders[] = $r;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $lender_id = isset($_POST['lender_id']) && is_numeric($_POST['lender_id']) ? (int)$_POST['lender_id'] : null;
    $application_id = isset($_POST['application_id']) && is_numeric($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $interest_rate = isset($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : 0;
    $term_months = isset($_POST['term_months']) ? (int)$_POST['term_months'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';

    if ($customer_id <= 0 || $amount <= 0 || $term_months <= 0) {
        $error = 'Please select a customer and provide a valid amount and term.';
    } else {
        // Prevent administrators from approving a second loan while one is active.
        $activeLoanStmt = $conn->prepare("SELECT id FROM loans WHERE customer_id = ? AND status = 'active' LIMIT 1");
        $activeLoanStmt->bind_param('i', $customer_id);
        $activeLoanStmt->execute();
        if ($activeLoanStmt->get_result()->num_rows > 0) {
            $error = 'This borrower already has an active loan.';
        }
    }

    if ($error === '' && $application_id > 0) {
        // An application may be linked to only one funded loan.
        $existingLoanStmt = $conn->prepare("SELECT id FROM loans WHERE application_id = ? LIMIT 1");
        $existingLoanStmt->bind_param('i', $application_id);
        $existingLoanStmt->execute();
        if ($existingLoanStmt->get_result()->num_rows > 0) {
            $error = 'This loan application has already been funded.';
        } else {
            // Linked applications use the same score-based rate policy as lender funding.
            $creditData = calculateCreditScore($customer_id, $amount);
            $systemRate = determineLoanInterestRate($creditData['score']);
            if ($systemRate === null) {
                $error = 'This borrower does not meet the minimum credit score for funding.';
            } else {
                $interest_rate = $systemRate;
            }
        }
    }

    if ($error === '') {
        $stmt = $conn->prepare("INSERT INTO loans (application_id, customer_id, lender_id, offer_id, amount, interest_rate, term_months, status, created_at) VALUES (?, ?, ?, 0, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            // allow null lender
            $lenderBind = $lender_id !== null ? $lender_id : 0;
            $stmt->bind_param('iiidiss', $application_id, $customer_id, $lenderBind, $amount, $interest_rate, $term_months, $status);
            if ($stmt->execute()) {
                $newLoanId = $conn->insert_id;

                // If linked to an application, mark it approved
                if ($application_id > 0) {
                    $u = $conn->prepare("UPDATE loan_applications SET status = 'approved' WHERE id = ?");
                    if ($u) {
                        $u->bind_param('i', $application_id);
                        $u->execute();
                        $u->close();
                    }
                }

                // Log system activity if logger exists
                if (function_exists('logSystemActivity')) {
                    logSystemActivity($conn, $_SESSION['user_id'], $_SESSION['username'] ?? 'admin', 'create_loan', 'Created loan #' . $newLoanId . ' for customer ID ' . $customer_id, 'loan', $newLoanId, 'admin');
                }

                $success = 'Loan created successfully (ID: ' . $newLoanId . ')';
            } else {
                $error = 'Failed to create loan: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $error = 'Failed to prepare statement: ' . $conn->error;
        }
    }
}
?>

<div class="container" style="max-width:800px;margin:30px auto;">
    <h1>Create Loan</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="createLoan.php">
        <div class="form-group">
            <label for="customer_id">Customer</label>
            <select id="customer_id" name="customer_id" class="form-control" required>
                <option value="">Select customer</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars(trim($c['name']) ?: $c['email']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="application_id">Linked Application ID (optional)</label>
            <input type="number" id="application_id" name="application_id" class="form-control" min="0">
        </div>

        <div class="form-group">
            <label for="lender_id">Lender (optional)</label>
            <select id="lender_id" name="lender_id" class="form-control">
                <option value="">No lender</option>
                <?php foreach ($lenders as $l): ?>
                    <option value="<?php echo (int)$l['id']; ?>"><?php echo htmlspecialchars($l['username'] ?: $l['email']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="amount">Amount (Kshs)</label>
            <input type="number" id="amount" name="amount" class="form-control" min="1" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="interest_rate">Interest Rate (percent; auto-calculated for linked applications)</label>
            <input type="number" id="interest_rate" name="interest_rate" class="form-control" min="0" step="0.01" value="8.00" required>
        </div>

        <div class="form-group">
            <label for="term_months">Term (months)</label>
            <input type="number" id="term_months" name="term_months" class="form-control" min="1" required>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="active">active</option>
                <option value="approved">approved</option>
                <option value="paid">paid</option>
                <option value="defaulted">defaulted</option>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">Create Loan</button>
            <a href="viewLoans.php" class="btn btn-secondary">Back</a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
