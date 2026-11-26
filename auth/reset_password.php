<?php
// ... keep all your existing PHP token verification and POST handling logic at the top ...

$pageTitle = 'Reset Password';
require __DIR__ . '/../includes/header.php';
?>

<section class="panel narrow">

    <h1>Reset Password</h1>
    <p>Enter and confirm your new password below.</p>

    <?php if (!empty($error)): ?>
        <p style="color: red; margin-bottom: 15px;"><?= h($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div style="color: green; margin-bottom: 15px; background: rgba(0, 255, 0, 0.05); padding: 10px; border-radius: 4px;">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <!-- Only show the form if the token is valid (or if not yet successfully reset) -->
    <form method="POST">
        <label for="new_password">
            New Password:
            <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
        </label>

        <label for="confirm_password">
            Confirm New Password:
            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
        </label>

        <button type="submit">Update Password</button>
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