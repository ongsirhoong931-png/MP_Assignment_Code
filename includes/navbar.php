
<?php
require_once __DIR__ . '/functions.php';

$account = logged_in() ? me() : null;
?>

<header class="site-header">

    <div class="header-brand">

        <button
            class="menu-toggle"
            type="button"
            aria-label="Expand navigation"
            aria-expanded="false"
            aria-controls="floating-navigation"
            data-menu-toggle>

            <span aria-hidden="true">☰</span>

        </button>

        <a class="brand"
           href="<?= h(url('index.php')) ?>">

            <span class="brand-mark">🎓</span>
            EDUVEX Academy

        </a>

    </div>

    <div class="header-actions">

        <?php if ($account): ?>

            <span class="header-user">
                <?= h($account['username']) ?>
            </span>

            <a href="<?= h(url('auth/logout.php')) ?>">
                Logout
            </a>

        <?php else: ?>

            <a href="<?= h(url('auth/login.php')) ?>">
                Sign In
            </a>

        <?php endif; ?>

    </div>

</header>
