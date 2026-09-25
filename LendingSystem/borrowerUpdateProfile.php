<?php
/** Customer profile editor that validates and updates only the current account. */
require_once 'includes/header.php';
require_once 'config/database.php';
require_once 'check_access.php';
if(!isset($_SESSION)){
    session_start();
}

// Profile edits are limited to the signed-in borrower account.
// Ensure only borrowers can access this page
requireRole('customer');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Load the current profile to pre-fill the form.
// Get current user data
$sql = "SELECT * FROM users WHERE id = ? AND role = 'customer'";
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
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $address_line_2 = trim($_POST['address_line_2'] ?? '');
    $county = trim($_POST['county'] ?? '');
    $country = trim($_POST['country'] ?? 'Kenya');
    $employment_status = $_POST['employment_status'] ?? 'employed';
    $annual_income = $_POST['annual_income'] ?? 0;
    $id_card_type = $_POST['id_card_type'] ?? '';
    $id_card_number = trim($_POST['id_card_number'] ?? '');

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = 'Name and email are required fields';
    } elseif (empty($id_card_type) || !preg_match('/^\d{6,}$/', $id_card_number)) {
        $error_message = 'ID or passport number must contain at least 6 digits and no letters or symbols';
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
                city = ?, 
                street_address = ?, 
                address_line_2 = ?, 
                county = ?, 
                country = ?, 
                employment_status = ?, 
                annual_income = ?, 
                id_card_type = ?, 
                id_card_number = ? 
                WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $types = str_repeat('s', 10) . 'd' . 'ss' . 'i';
            $update_stmt->bind_param($types,
                $first_name,
                $last_name,
                $email,
                $phone,
                $city,
                $street_address,
                $address_line_2,
                $county,
                $country,
                $employment_status,
                $annual_income,
                $id_card_type,
                $id_card_number,
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
?>
<div class="dashboard">
    <div class="container">
        <div class="card" style="max-width: 800px; margin: 30px auto; padding: 30px;">
            <h1>Update Your Profile</h1>
            
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
                
                <h2 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin: 30px 0 20px;">Address Information</h2>
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 100%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="street_address">Street Address</label>
                        <input type="text" id="street_address" name="street_address" class="form-control" value="<?php echo htmlspecialchars($user['street_address'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="address_line_2">Apartment / Building / P.O. Box</label>
                        <input type="text" id="address_line_2" name="address_line_2" class="form-control" value="<?php echo htmlspecialchars($user['address_line_2'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="city">City / Town</label>
                        <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="county">County</label>
                        <input type="text" id="county" name="county" class="form-control" value="<?php echo htmlspecialchars($user['county'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control" value="<?php echo htmlspecialchars($user['country'] ?? 'Kenya'); ?>">
                    </div>
                </div>

                <h2 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin: 30px 0 20px;">National ID / Identity Details</h2>
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="id_card_type">ID Type *</label>
                        <select id="id_card_type" name="id_card_type" class="form-control" required>
                            <option value="" <?php echo empty($user['id_card_type'] ?? '') ? 'selected' : ''; ?>>Select ID type</option>
                            <option value="National ID" <?php echo ($user['id_card_type'] ?? '') === 'National ID' ? 'selected' : ''; ?>>National ID</option>
                            <option value="Passport" <?php echo ($user['id_card_type'] ?? '') === 'Passport' ? 'selected' : ''; ?>>Passport</option>
                            <option value="Driving License" <?php echo ($user['id_card_type'] ?? '') === 'Driving License' ? 'selected' : ''; ?>>Driving License</option>
                            <option value="Alien ID" <?php echo ($user['id_card_type'] ?? '') === 'Alien ID' ? 'selected' : ''; ?>>Alien ID</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="id_card_number">ID / Passport Number *</label>
                        <input type="text" id="id_card_number" name="id_card_number" class="form-control" value="<?php echo htmlspecialchars($user['id_card_number'] ?? ''); ?>" minlength="6" pattern="[0-9]{6,}" inputmode="numeric" title="Enter at least 6 digits with no letters or symbols" required>
                    </div>
                </div>

                
                <h2 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin: 30px 0 20px;">Financial Information</h2>
                <div class="form-row" style="display: flex; flex-wrap: wrap; margin: 0 -15px;">
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="employment_status">Employment Status</label>
                        <select id="employment_status" name="employment_status" class="form-control">
                            <option value="employed" <?php echo ($user['employment_status'] ?? '') === 'employed' ? 'selected' : ''; ?>>Employed</option>
                            <option value="self-employed" <?php echo ($user['employment_status'] ?? '') === 'self-employed' ? 'selected' : ''; ?>>Self-Employed</option>
                            <option value="unemployed" <?php echo ($user['employment_status'] ?? '') === 'unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="retired" <?php echo ($user['employment_status'] ?? '') === 'retired' ? 'selected' : ''; ?>>Retired</option>
                            <option value="student" <?php echo ($user['employment_status'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="flex: 0 0 50%; padding: 0 15px; margin-bottom: 20px;">
                        <label for="annual_income">Annual Income (Kshs)</label>
                        <input type="number" id="annual_income" name="annual_income" class="form-control" min="0" step="1000" value="<?php echo htmlspecialchars($user['annual_income'] ?? '0'); ?>">
                    </div>
                </div>
                
                <div class="btn-container" style="display: flex; justify-content: space-between; margin-top: 30px;">
                    <a href="borrowerDashboard.php" class="btn btn-secondary">Cancel</a>
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
