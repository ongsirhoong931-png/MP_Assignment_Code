<?php
// ==========================================
// EDUVEX ACADEMY - ACCOUNT APPROVALS
// ==========================================

require_once __DIR__ . '/../includes/role_check.php';
allow_roles('admin');

require_once __DIR__ . '/../db_connect.php';

$message = '';
$error = '';

// Handle Approval or Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = $_POST['user_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($target_user_id && $action) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE user_accounts SET is_active = 1 WHERE user_id = ?");
            $stmt->execute([$target_user_id]);
            $message = "User account has been successfully approved.";
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("DELETE FROM user_accounts WHERE user_id = ? AND is_active = 0");
            $stmt->execute([$target_user_id]);
            $message = "User account request has been rejected and removed.";
        }
    }
}

// Fetch all pending accounts (is_active = 0)
$stmt = $pdo->prepare("SELECT user_id, username, email, role, student_id, staff_id, created_at FROM user_accounts WHERE is_active = 0 ORDER BY created_at DESC");
$stmt->execute();
$pending_users = $stmt->fetchAll();

$pageTitle = 'Account Approvals';
require __DIR__ . '/../includes/header.php';
?>

<h1>Account Approvals</h1>
<p>Review and approve new student and staff registration requests.</p>

<?php if (!empty($message)): ?>
    <p style="color: #28a745; font-weight: bold; margin-bottom: 15px;"><?= h($message); ?></p>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <p style="color: #dc3545; font-weight: bold; margin-bottom: 15px;"><?= h($error); ?></p>
<?php endif; ?>

<?php if (empty($pending_users)): ?>
    <p style="margin-top: 20px; color: #aaa;">No pending account requests at this time.</p>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" border="1">
            <thead>
                <tr style="background: rgba(255,255,255,0.05);">
                    <th style="padding: 10px; text-align: left;">Username</th>
                    <th style="padding: 10px; text-align: left;">Email</th>
                    <th style="padding: 10px; text-align: left;">Role</th>
                    <th style="padding: 10px; text-align: left;">Reference ID</th>
                    <th style="padding: 10px; text-align: left;">Requested At</th>
                    <th style="padding: 10px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_users as $user): ?>
                    <tr>
                        <td style="padding: 10px;"><?= h($user['username']); ?></td>
                        <td style="padding: 10px;"><?= h($user['email']); ?></td>
                        <td style="padding: 10px; text-transform: capitalize;"><?= h($user['role']); ?></td>
                        <td style="padding: 10px;"><?= h($user['student_id'] ?? $user['staff_id'] ?? 'N/A'); ?></td>
                        <td style="padding: 10px;"><?= h($user['created_at']); ?></td>
                        <td style="padding: 10px; text-align: center;">
                            <form method="POST" style="display:inline-block; margin-right: 5px;">
                                <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" style="background: #28a745; color: white; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px;">Approve</button>
                            </form>
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to reject this request?');">
                                <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px;">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
require __DIR__ . '/../includes/footer.php';
?>