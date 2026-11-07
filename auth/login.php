<?php
require_once __DIR__ . '/../includes/functions.php';

if (logged_in()) {
    go('index.php');
}

$pageTitle = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<section class="panel narrow">
    <h1>Login</h1>

    <form method="post"
          action="<?= h(url('auth/login_process.php')) ?>">

        <input type="hidden"
               name="csrf"
               value="<?= h(csrf()) ?>">

        <label>Username or Email
            <input type="text"
                   name="login"
                   required
                   autocomplete="username">
        </label>

        <label>Password
            <input type="password"
                   name="password"
                   required
                   autocomplete="current-password">
        </label>

        <button type="submit">Sign In</button>
    </form>

    <p>
        <a href="<?= h(url('auth/forgot_password.php')) ?>">
            Forgot Password?
        </a>
    </p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>