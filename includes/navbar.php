<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/site_language.php';
require_once __DIR__ . '/../config/database.php';

$account = logged_in() ? me() : null;

$role = $account['role'] ?? '';
$profileUrl = '#';
$profileImage = '';

if ($account && isset($pdo)) {
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

        <a class="brand" href="<?= h(url('index.php')) ?>">
            <span class="brand-mark">🎓</span>
            EDUVEX Academy
        </a>
    </div>

    <div class="header-actions" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">

        <!-- Language Selector Widget -->
        <?= site_language_widget() ?>

        <!-- Independent Box 1: Theme Toggle -->
        <div class="header-box-item" style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 13px; font-weight: 600; color: var(--text); line-height: 1;">Theme</span>
            <label class="switch" style="display: inline-flex; align-items: center; margin-bottom: 0;">
                <input type="checkbox" id="theme-checkbox" onclick="toggleTheme()">
                <span class="slider"></span>
            </label>
        </div>

        <?php if ($account): ?>
            <!-- Independent Box 2: User Profile -->
            <a href="<?= h($profileUrl) ?>" class="header-box-item header-btn-box-link">
                <?php if (!empty($profileImage) && file_exists(__DIR__ . '/../' . $profileImage)): ?>
                    <img src="<?= h(url($profileImage)) ?>" alt="Avatar" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <span class="user-avatar-icon" style="font-size: 1rem;" aria-hidden="true">👤</span>
                <?php endif; ?>
                <span class="header-user" style="font-weight: 500;">
                    <?= h($account['username']) ?>
                </span>
            </a>

            <!-- Independent Box 3: Logout with bright red styling -->
            <a href="<?= h(url('auth/logout.php')) ?>" class="header-box-item header-btn-box-link header-logout-btn">
                Logout
            </a>
        <?php else: ?>
            <a href="<?= h(url('auth/login.php')) ?>" class="header-box-item header-btn-box-link">
                Sign In
            </a>
        <?php endif; ?>

    </div>

</header>