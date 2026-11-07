
<?php
// ==========================================
// EDUVEX ACADEMY - LOGIN PAGE
// ==========================================

// Load existing functions first
require_once __DIR__ . '/../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect users who are already logged in
if (logged_in()) {

    $role = (string) (me()['role'] ?? '');

    // Handle attendance QR redirect
    if (
        $role === 'student' &&
        !empty($_SESSION['after_login_redirect'])
    ) {

        $destination = $_SESSION['after_login_redirect'];

        unset($_SESSION['after_login_redirect']);

        // Validate destination
        $path = parse_url($destination, PHP_URL_PATH);
        $allowedPath = parse_url(
            url('student/attendance.php'),
            PHP_URL_PATH
        );

        if (
            is_string($destination) &&
            is_string($path) &&
            $path === $allowedPath &&
            !str_starts_with($destination, '//') &&
            !preg_match('/[\r\n\\\\]/', $destination) &&
            parse_url($destination, PHP_URL_SCHEME) === null &&
            parse_url($destination, PHP_URL_HOST) === null
        ) {
            header('Location: ' . $destination);
            exit;
        }
    }

    // Default dashboard redirect
    $dashboard = match ($role) {
        'admin'   => 'admin/dashboard.php',
        'staff'   => 'tutor/dashboard.php',
        'student' => 'student/dashboard.php',
        default   => 'index.php'
    };

    go($dashboard);
}

// ==========================================
// PAGE SETTINGS
// ==========================================

$pageTitle = 'Login';

// Load header
require __DIR__ . '/../includes/header.php';
?>

<!-- ========================================
     LOGIN FORM
========================================= -->

<section class="panel narrow">

    <h1>Login</h1>

    <p>
        Welcome to EDUVEX Academy.
        Please sign in to continue.
    </p>

    <form
        method="post"
        action="<?= h(url('auth/login_process.php')) ?>"
    >

        <!-- CSRF Protection -->
        <input
            type="hidden"
            name="csrf"
            value="<?= h(csrf()) ?>"
        >

        <!-- Username / Email -->
        <label for="login">

            Username or Email

            <input
                type="text"
                id="login"
                name="login"
                required
                autocomplete="username"
                placeholder="Enter username or email"
            >

        </label>

        <!-- Password -->
        <label for="password">

            Password

            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Enter password"
            >

        </label>

        <!-- Login Button -->
        <button type="submit">
            Sign In
        </button>

    </form>

    <!-- Forgot Password -->
    <p>
        <a href="<?= h(url('auth/forgot_password.php')) ?>">
            Forgot Password?
        </a>
    </p>

</section>

<?php
// Load footer
require __DIR__ . '/../includes/footer.php';
?>
