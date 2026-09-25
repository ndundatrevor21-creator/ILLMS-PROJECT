<?php
/** Lender workflow for storing a request for additional borrower information. */
require_once 'includes/header.php';

// Check if user is logged in and is lender
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lender') {
    header('Location: signin.php');
    exit;
}

// Identify the application before recording an information request.
// Check if loan ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: lenderDashboard.php');
    exit;
}

$loanId = (int)$_GET['id'];
$lenderId = $_SESSION['user_id'];

// Verify the loan application exists and is pending or needs information
$verifyQuery = "SELECT la.*, u.username as borrower_name 
                FROM loan_applications la
                JOIN users u ON la.customer_id = u.id
                WHERE la.id = ? AND la.status IN ('pending', 'needs_information')";
$stmt = $conn->prepare($verifyQuery);
$stmt->bind_param("i", $loanId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: lenderDashboard.php');
    exit;
}

$loanData = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        $error = "Please enter a message.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Update loan application status
            $updateQuery = "UPDATE loan_applications SET status = 'needs_information' WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("i", $loanId);
            $stmt->execute();
            
            // 2. Insert message
            $insertMessageQuery = "INSERT INTO loan_messages (loan_application_id, sender_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertMessageQuery);
            $stmt->bind_param("iis", $loanId, $lenderId, $message);
            $stmt->execute();
            
            $conn->commit();
            $success = "Information request sent successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get existing messages
$messagesQuery = "SELECT lm.*, u.username, u.role 
                  FROM loan_messages lm
                  JOIN users u ON lm.sender_id = u.id
                  WHERE lm.loan_application_id = ?
                  ORDER BY lm.created_at ASC";
$stmt = $conn->prepare($messagesQuery);
$stmt->bind_param("i", $loanId);
$stmt->execute();
$messagesResult = $stmt->get_result();

?>

<div class="dashboard">
    <div class="card" style="max-width: 800px; margin: 30px auto; padding: 30px;">
        <h2>Request Information from Borrower</h2>
        <p><strong>Loan ID:</strong> <?php echo $loanId; ?></p>
        <p><strong>Borrower:</strong> <?php echo htmlspecialchars($loanData['borrower_name']); ?></p>
        <p><strong>Amount:</strong> Kshs <?php echo number_format($loanData['amount'], 2); ?></p>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        
        <h3>Messages</h3>
        <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; max-height: 300px; overflow-y: auto;">
            <?php if ($messagesResult->num_rows > 0): ?>
                <?php while ($msg = $messagesResult->fetch_assoc()): ?>
                    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                        <strong><?php echo htmlspecialchars($msg['username']); ?> (<?php echo htmlspecialchars($msg['role']); ?>):</strong>
                        <p style="margin: 5px 0 0 0;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        <small style="color: #666;"><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #666;">No messages yet.</p>
            <?php endif; ?>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <h3>Send a Message</h3>
        <form method="POST" action="">
            <div class="form-group">
                <textarea name="message" class="form-control" rows="4" placeholder="Enter your request for information..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Send Request</button>
                <a href="lenderDashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
