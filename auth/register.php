<?php
// ==========================================
// EDUVEX ACADEMY - REGISTER PAGE
// ==========================================

require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (logged_in()) {
    $role = (string) (me()['role'] ?? '');
    $dashboard = match ($role) {
        'admin'   => 'admin/dashboard.php',
        'staff'   => 'tutor/dashboard.php',
        'student' => 'student/dashboard.php',
        default   => 'index.php'
    };
    go($dashboard);
}

$pageTitle = 'Register Account';

require __DIR__ . '/../includes/header.php';
?>

<section class="panel narrow">

    <h1>Create an Account</h1>

    <p>
        Register for a Student or Staff account. Your request will be reviewed and approved by an administrator.
    </p>

    <?php if (!empty($_SESSION['error'])): ?>
        <p style="color: red; margin-bottom: 15px;"><?= h($_SESSION['error']); unset($_SESSION['error']); ?></p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <p style="color: green; margin-bottom: 15px;"><?= h($_SESSION['success']); unset($_SESSION['success']); ?></p>
    <?php endif; ?>

    <form
        method="post"
        action="<?= h(url('auth/register_process.php')) ?>"
    >

        <?php if (function_exists('csrf')): ?>
            <input
                type="hidden"
                name="csrf"
                value="<?= h(csrf()) ?>"
            >
        <?php endif; ?>

        <label for="username">
            Username
            <input
                type="text"
                id="username"
                name="username"
                required
                autocomplete="username"
                placeholder="Enter username"
            >
        </label>

        <label for="email">
            Email Address
            <input
                type="email"
                id="email"
                name="email"
                required
                autocomplete="email"
                placeholder="Enter email address"
            >
        </label>

        <label for="password">
            Password
            <input
                type="password"
                id="password"
                name="password"
                required
                minlength="12"
                autocomplete="new-password"
                placeholder="Min. 12 chars (Upper, number, special char)"
            >
        </label>

        <label for="role">
            Role Request
            <select id="role" name="role" required>
                <option value="student">Student</option>
                <option value="staff">Staff</option>
                <!-- Admin option excluded for security -->
            </select>
        </label>

        <label for="reference_id">
            Reference ID (Student/Staff ID e.g., STU011)
            <input
                type="text"
                id="reference_id"
                name="reference_id"
                placeholder="Optional or required based on rules"
            >
        </label>

        <button type="submit">
            Submit Registration Request
        </button>

    </form>

    <div style="margin-top: 15px; text-align: center;">
        <p style="margin: 0;">
            Already have an account? <a href="<?= h(url('auth/login.php')) ?>">Sign In</a>
        </p>
    </div>

</section>

<?php
require __DIR__ . '/../includes/footer.php';
?>