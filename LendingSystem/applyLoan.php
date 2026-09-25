<?php
/** Customer loan application form and handler; validates fields before inserting pending data. */
require_once 'includes/header.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: signin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Loan applications require a verified numeric ID or passport number.
$identityStmt = $conn->prepare("SELECT id_card_type, id_card_number FROM users WHERE id = ?");
$identityStmt->bind_param("i", $user_id);
$identityStmt->execute();
$identity = $identityStmt->get_result()->fetch_assoc() ?: [];
$hasValidIdentity = !empty($identity['id_card_type']) && preg_match('/^\d{6,}$/', trim((string)($identity['id_card_number'] ?? '')));

// Process loan application form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate the request and save it for lender review.
    $amount = $_POST['amount'];
    $purpose = $_POST['purpose'];
    $term_months = $_POST['term_months'];
    
    // Validate input
    if (!$hasValidIdentity) {
        $error = 'Please update your profile with an ID or passport number of at least 6 digits before applying.';
    } elseif (empty($amount) || empty($purpose) || empty($term_months)) {
        $error = 'Please fill in all fields';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif (!is_numeric($term_months) || $term_months <= 0) {
        $error = 'Please enter a valid term';
    } else {
        // A borrower must clear all active loans before applying again.
        $activeLoanStmt = $conn->prepare("SELECT id FROM loans WHERE customer_id = ? AND status = 'active' LIMIT 1");
        $activeLoanStmt->bind_param("i", $user_id);
        $activeLoanStmt->execute();

        if ($activeLoanStmt->get_result()->num_rows > 0) {
            $error = 'You already have an active loan. Please complete its repayment before applying for another loan.';
        } else {
            // Insert loan application
            $query = "INSERT INTO loan_applications (customer_id, amount, purpose, term_months) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("idsi", $user_id, $amount, $purpose, $term_months);

            if ($stmt->execute()) {
                $success = 'Loan application submitted successfully!';
            } else {
                $error = 'Failed to submit loan application: ' . $conn->error;
            }
        }
    }
}
?>

<div class="loan-application">
    <div class="container">
        <h1>Apply for a Loan</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="applyLoan.php">
                    <div class="form-group">
                        <label for="amount">Loan Amount (Kshs.)</label>
                        <input type="number" id="amount" name="amount" class="form-control" min="100" step="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose">Loan Purpose</label>
                        <select id="purpose" name="purpose" class="form-control" required>
                            <option value="">Select Purpose</option>
                            <option value="Home Improvement">Home Improvement</option>
                            <option value="Debt Consolidation">Debt Consolidation</option>
                            <option value="Education">Education</option>
                            <option value="Business">Business</option>
                            <option value="Medical Expenses">Medical Expenses</option>
                            <option value="Vehicle Purchase">Vehicle Purchase</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="term_months">Loan Term (Months)</label>
                        <select id="term_months" name="term_months" class="form-control" required>
                            <option value="">Select Term</option>
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="4">4 Months</option>
                            <option value="5">5 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                            <option value="24">24 Months</option>
                            <option value="36">36 Months</option>
                            <option value="48">48 Months</option>
                            <option value="60">60 Months</option>
                        </select>
                    </div>
                    
                    <div class="loan-calculator">
                        <h3>Loan Calculator</h3>
                        <div class="calculator-result">
                            <div class="result-item">
                                <span class="result-label">Monthly Payment:</span>
                                <span class="result-value" id="monthly-payment">Kshs 0.00</span>
                            </div>
                            <div class="result-item">
                                <span class="result-label">Total Payment:</span>
                                <span class="result-value" id="total-payment">Kshs 0.00</span>
                            </div>
                            <div class="result-item">
                                <span class="result-label">Total Interest:</span>
                                <span class="result-value" id="total-interest">Kshs 0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group terms-agreement">
                        <input type="checkbox" id="agree-terms" name="agree_terms" required>
                        <label for="agree-terms">I agree to the <a href="#">terms and conditions</a></label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>