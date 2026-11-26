<?php
// ==========================================
// EDUVEX ACADEMY - FORGOT PASSWORD (OTP)
// ==========================================

require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        require_once __DIR__ . '/../db_connect.php';

        $stmt = $pdo->prepare("SELECT user_id, email FROM user_accounts WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a secure 6-digit OTP code
            $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date("Y-m-d H:i:s", strtotime('+15 minutes'));

            // Store code in database
            $insertStmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $insertStmt->execute([$user['user_id'], $otpCode, $expiresAt]);

            // Save email in session so the next page knows who is resetting
            $_SESSION['reset_email'] = $email;

            $message = "Your 6-digit verification code is: <strong style='font-size: 1.4rem; letter-spacing: 2px; color: #fff; background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 4px;'>" . $otpCode . "</strong><br><br>Valid for 15 minutes. <a href='" . h(url('auth/verify_otp.php')) . "'>Proceed to Verification &rarr;</a>";
        } else {
            $message = "If that email address is registered, a verification code has been generated.";
        }
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/../includes/header.php';
?>

<section class="panel narrow">

    <h1>Forgot Password</h1>
    <p>Enter your registered email address to receive your 6-digit verification code.</p>

    <?php if (!empty($error)): ?>
        <p style="color: red; margin-bottom: 15px;"><?= h($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div style="color: green; margin-bottom: 15px; background: rgba(0, 255, 0, 0.05); padding: 15px; border-radius: 6px; line-height: 1.6;">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <form action="forgot_password.php" method="POST">
        <label for="email">
            Registered Email:
            <input type="email" id="email" name="email" required placeholder="Enter your email">
        </label>
        
        <button type="submit">Generate Code</button>
    </form>
    
    <div style="margin-top: 15px; text-align: center;">
        <p style="margin: 0;">
            <a href="<?= h(url('auth/login.php')) ?>">Back to Login</a>
        </p>
    </div>

</section>

<?php
require __DIR__ . '/../includes/footer.php';
?>