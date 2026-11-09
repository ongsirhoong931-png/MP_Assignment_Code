<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php'; // <-- Added database connection

$account = logged_in() ? me() : null;

$role = $account['role'] ?? '';
$profileUrl = '#';
$profileImage = '';

if ($account && isset($pdo)) {
    // Fetch latest profile image from database safely
    $stmt = $pdo->prepare("SELECT profile_image FROM user_accounts WHERE user_id = ?");
    $stmt->execute([$account['id']]);
    $profileImage = $stmt->fetchColumn();
}

if ($role === 'admin') {
    $profileUrl = url('admin/profile.php');
} elseif ($role === 'staff') {
    $profileUrl = url('tutor/profile.php');
} elseif ($role === 'student') {
    $studentId = $account['student_id'] ?? '';
    $profileUrl = url('student_management/view_student.php?id=' . urlencode($studentId));
}
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

    <div class="header-actions" style="display: flex; align-items: center; gap: 12px;">

        <?php if ($account): ?>

            <!-- User Profile Button Card -->
            <a href="<?= h($profileUrl) ?>" class="header-btn-effect">
                <?php if (!empty($profileImage) && file_exists(__DIR__ . '/../' . $profileImage)): ?>
                    <img src="<?= h(url($profileImage)) ?>" alt="Avatar" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <span class="user-avatar-icon" style="font-size: 1rem;" aria-hidden="true">👤</span>
                <?php endif; ?>
                <span class="header-user" style="font-weight: 500;">
                    <?= h($account['username']) ?>
                </span>
            </a>

            <!-- Logout Button Card -->
            <a href="<?= h(url('auth/logout.php')) ?>" class="header-btn-effect">
                Logout
            </a>

        <?php else: ?>

            <a href="<?= h(url('auth/login.php')) ?>" class="header-btn-effect">
                Sign In
            </a>

        <?php endif; ?>

    </div>

</header>