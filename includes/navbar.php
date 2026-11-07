<nav class="navbar">
    <a href="<?= h(url('index.php')) ?>">
        Tuition Attendance
    </a>

    <div>
        <?php if (logged_in()): ?>
            <a href="<?= h(url('auth/profile.php')) ?>">
                <?= h(me()['username']) ?>
            </a>

            <a href="<?= h(url('auth/logout.php')) ?>">
                Logout
            </a>
        <?php else: ?>
            <a href="<?= h(url('auth/login.php')) ?>">
                Login
            </a>
        <?php endif; ?>
    </div>
</nav>