<?php
/** Lender-only action that validates and transitions a pending application to rejected. */
require_once 'includes/header.php';

// Only lenders can reject pending applications.
// Check if user is logged in and is lender
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lender') {
    header('Location: signin.php');
    exit;
}

// Check if loan ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: lenderDashboard.php');
    exit;
}

$loanId = (int)$_GET['id'];

// Verify the loan application exists and is pending
$verifyQuery = "SELECT la.*, u.username as borrower_name 
                FROM loan_applications la
                JOIN users u ON la.customer_id = u.id
                WHERE la.id = ? AND la.status = 'pending'";
$stmt = $conn->prepare($verifyQuery);
$stmt->bind_param("i", $loanId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: lenderDashboard.php');
    exit;
}

$loanData = $result->fetch_assoc();

// Update loan application status to rejected
$updateQuery = "UPDATE loan_applications SET status = 'rejected' WHERE id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("i", $loanId);

if ($stmt->execute()) {
    $success = "Loan rejected successfully!";
} else {
    $error = "Error rejecting loan: " . $conn->error;
}

?>

<div class="dashboard">
    <div class="card" style="max-width: 600px; margin: 50px auto; padding: 30px;">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <h2>Loan Rejected</h2>
        <p>Loan ID: <?php echo $loanId; ?></p>
        <p>Borrower: <?php echo htmlspecialchars($loanData['borrower_name']); ?></p>
        <p>Amount: Kshs <?php echo number_format($loanData['amount'], 2); ?></p>
        
        <a href="lenderDashboard.php" class="btn btn-primary" style="margin-top: 20px;">Back to Dashboard</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
