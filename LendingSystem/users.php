<?php
/** Admin user-management view listing accounts and linking to protected actions. */
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit;
}

$flash_message = $_SESSION['admin_action_message'] ?? '';
$flash_success = $_SESSION['admin_action_success'] ?? '1';
unset($_SESSION['admin_action_message'], $_SESSION['admin_action_success']);

$lenders_query = "SELECT id, username, email, company_name, lending_capacity, preferred_loan_types, status
                 FROM users WHERE role='lender' ORDER BY created_at DESC";
$lenders_result = executeQuery($lenders_query);

$borrowers_query = "SELECT id, username, email, first_name, last_name, phone, city, employment_status, annual_income, status
                   FROM users WHERE role='customer' ORDER BY created_at DESC";
$borrowers_result = executeQuery($borrowers_query);
?>

<div class="dashboard">
    <?php if ($flash_message !== ''): ?>
        <div class="alert alert-<?php echo $flash_success === '1' ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>Lenders</h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>UserName</th>
                            <th>Email</th>
                            <th>Company Name</th>
                            <th>Lending Capacity</th>
                            <th>Preferred Loan Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($lenders = $lenders_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lenders['username']); ?></td>
                            <td><?php echo htmlspecialchars($lenders['email']); ?></td>
                            <td><?php echo htmlspecialchars($lenders['company_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($lenders['lending_capacity'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($lenders['preferred_loan_types'] ?? ''); ?></td>
                            <td>
                                <span class="badge <?php echo ($lenders['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo htmlspecialchars(($lenders['status'] ?? 'active')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <form method="POST" action="admin/user_actions.php" onsubmit="return confirm('Are you sure you want to <?php echo (($lenders['status'] ?? 'active') === 'active') ? 'block' : 'unblock'; ?> this lender?');">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$lenders['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo (($lenders['status'] ?? 'active') === 'active') ? 'blocked' : 'active'; ?>">
                                        <button type="submit" class="btn btn-warning"><?php echo (($lenders['status'] ?? 'active') === 'active') ? 'Block' : 'Unblock'; ?></button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" onclick="resetPassword(<?php echo (int)$lenders['id']; ?>)">Reset Password</button>
                                    <form method="POST" action="admin/user_actions.php" onsubmit="return confirm('Delete this lender? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$lenders['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if ($lenders_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">No registered lenders</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <div class="card">
            <div class="card-header">
                <h2>Borrowers</h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Employment Status</th>
                            <th>Annual Income</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($borrowers = $borrowers_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($borrowers['username']); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['email']); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['first_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['last_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['city'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['employment_status'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($borrowers['annual_income'] ?? ''); ?></td>
                            <td>
                                <span class="badge <?php echo ($borrowers['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo htmlspecialchars(($borrowers['status'] ?? 'active')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <form method="POST" action="admin/user_actions.php" onsubmit="return confirm('Are you sure you want to <?php echo (($borrowers['status'] ?? 'active') === 'active') ? 'block' : 'unblock'; ?> this borrower?');">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$borrowers['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo (($borrowers['status'] ?? 'active') === 'active') ? 'blocked' : 'active'; ?>">
                                        <button type="submit" class="btn btn-warning"><?php echo (($borrowers['status'] ?? 'active') === 'active') ? 'Block' : 'Unblock'; ?></button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" onclick="resetPassword(<?php echo (int)$borrowers['id']; ?>)">Reset Password</button>
                                    <form method="POST" action="admin/user_actions.php" onsubmit="return confirm('Delete this borrower? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$borrowers['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if ($borrowers_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center">No registered borrowers</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function resetPassword(userId) {
    const password = window.prompt('Enter a new password for this user:');
    if (!password || password.trim() === '') {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin/user_actions.php';

    const fields = {
        action: 'reset_password',
        user_id: String(userId),
        new_password: password,
    };

    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>