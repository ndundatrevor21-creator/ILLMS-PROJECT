<?php
/** Lender profile editor that validates and updates only the current session account. */
require_once 'includes/header.php';
require_once 'config/database.php';
require_once 'check_access.php';

// Profile edits are limited to the signed-in lender account.
// Ensure only lenders can access this page
requireRole('lender');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Load the current profile to pre-fill the form.
// Get current user data
$sql = "SELECT * FROM users WHERE id = ? AND role = 'lender'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: signin.php');
    exit;
}

$user = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $company_name = $_POST['company_name'];
    $lending_capacity = $_POST['lending_capacity'];
    $preferred_loan_types = isset($_POST['preferred_loan_types']) ? implode(',', $_POST['preferred_loan_types']) : '';
    
    // Validate input
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = 'Name and email are required fields';
    } else {
        // Check if email already exists for another user
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = 'Email already exists for another user';
        } else {
            // Update user profile
            $update_sql = "UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                phone = ?, 
                company_name = ?, 
                lending_capacity = ?, 
                preferred_loan_types = ? 
                WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssisi", 
                $first_name, 
                $last_name, 
                $email, 
                $phone, 
                $company_name, 
                $lending_capacity, 
                $preferred_loan_types, 
                $user_id
            );
            
            if ($update_stmt->execute()) {
                $success_message = 'Profile updated successfully';
                
                // Refresh user data
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error_message = 'Failed to update profile: ' . $conn->error;
            }
        }
    }
}

// Get loan types for checkboxes
$loan_types = ['Personal', 'Business', 'Mortgage', 'Auto', 'Education', 'Debt Consolidation'];
$user_preferred_types = explode(',', $user['preferred_loan_types'] ?? '');
?>

<div class="dashboard">
    <div class="container">
        <div class="card" style="max-width: 800px; margin: 30px auto; padding: 30px;">
            <h1>Update Lender Profile</h1>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <h2 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin: 30px 0 20px;">Personal Information</h2>
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <h2 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin: 30px 0 20px;">Lending Information</h2>
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="lending_capacity">Lending Capacity (Kshs)</label>
                        <input type="number" id="lending_capacity" name="lending_capacity" class="form-control" min="0" step="1000" value="<?php echo htmlspecialchars($user['lending_capacity'] ?? '0'); ?>">
                    </div>
                </div>
                
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 100%; padding: 0 15px; margin-bottom: 20px;">
                        <label>Preferred Loan Types</label>
                        <div class="checkbox-group" style="display: flex; flex-wrap: wrap;">
                            <?php foreach ($loan_types as $type): ?>
                                <div class="checkbox-item" style="flex: 0 0 33.333%; margin-bottom: 10px;">
                                    <input type="checkbox" id="loan_type_<?php echo strtolower($type); ?>" name="preferred_loan_types[]" value="<?php echo $type; ?>" <?php echo in_array($type, $user_preferred_types) ? 'checked' : ''; ?>>
                                    <label for="loan_type_<?php echo strtolower($type); ?>"><?php echo $type; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="btn-container" style="display: flex; justify-content: space-between; margin-top: 30px;">
                    <a href="lenderDashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
            
            <div style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin: 30px 0 20px;"></div>
            <div class="btn-container" style="display: flex; justify-content: center;">
                <a href="changePassword.php" class="btn btn-primary">Change Password</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>