<?php
// ==========================================
// EDUVEX ACADEMY - VERIFY OTP & RESET PASSWORD
// ==========================================

require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredCode = trim($_POST['otp_code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($enteredCode) || empty($newPassword)) {
        $error = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        require_once __DIR__ . '/../db_connect.php';

        // Find user by session email
        $stmt = $pdo->prepare("SELECT user_id FROM user_accounts WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if valid active OTP exists
            $tokenStmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $tokenStmt->execute([$user['user_id'], $enteredCode]);
            $tokenRecord = $tokenStmt->fetch();

            if ($tokenRecord) {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE user_accounts SET password_hash = ? WHERE user_id = ?");
                $updateStmt->execute([$newHash, $user['user_id']]);

                // Delete used tokens
                $delStmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                $delStmt->execute([$user['user_id']]);

                unset($_SESSION['reset_email']);
                $success = "Password successfully updated! <a href='" . h(url('auth/login.php')) . "'>Click here to login</a>.";
            } else {
                $error = "Invalid or expired verification code.";
            }
        } else {
            $error = "Invalid session. Please request a new code.";
        }
    }
}

$pageTitle = 'Verify Code';
require __DIR__ . '/../includes/header.php';
?>

<section class="panel narrow">

    <h1>Enter Verification Code</h1>
    <p>Please enter the 6-digit code shown on the previous screen and your new password.</p>

    <?php if (!empty($error)): ?>
        <p style="color: red; margin-bottom: 15px;"><?= h($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="color: green; margin-bottom: 15px; background: rgba(0, 255, 0, 0.05); padding: 15px; border-radius: 6px;">
            <?= $success; ?>
        </div>
    <?php else: ?>

    <form action="verify_otp.php" method="POST">
        <label for="otp_code">
            6-Digit Code:
            <input type="text" id="otp_code" name="otp_code" maxlength="6" required placeholder="123456" style="letter-spacing: 4px; font-weight: bold; font-size: 1.2rem; text-align: center;">
        </label>

        <label for="new_password">
            New Password:
            <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
        </label>

        <label for="confirm_password">
            Confirm Password:
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
        </label>
        
        <button type="submit">Reset Password</button>
    </form>

    <?php endif; ?>
    
    <div style="margin-top: 15px; text-align: center;">
        <p style="margin: 0;">
            <a href="<?= h(url('auth/forgot_password.php')) ?>">Request a new code</a> | 
            <a href="<?= h(url('auth/login.php')) ?>">Back to Login</a>
        </p>
    </div>

</section>

<?php
require __DIR__ . '/../includes/footer.php';
?>