<?php
// ==========================================
// EDUVEX ACADEMY - ADMIN DASHBOARD
// ==========================================

require_once __DIR__ . '/../includes/role_check.php';
allow_roles('admin');

require_once __DIR__ . '/../db_connect.php';

// Fetch quick statistics for the dashboard cards
try {
    $pendingApprovals = (int) $pdo->query("SELECT COUNT(*) FROM user_accounts WHERE is_active = 0")->fetchColumn();
    $totalStudents    = (int) $pdo->query("SELECT COUNT(*) FROM user_accounts WHERE role = 'student' AND is_active = 1")->fetchColumn();
    $totalTutors      = (int) $pdo->query("SELECT COUNT(*) FROM user_accounts WHERE role = 'staff' AND is_active = 1")->fetchColumn();
    $totalClasses     = (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    // Fetch up to 5 latest pending accounts for quick preview
    $recentStmt = $pdo->query("SELECT user_id, username, email, role FROM user_accounts WHERE is_active = 0 ORDER BY user_id DESC LIMIT 5");
    $recentPending = $recentStmt->fetchAll();
} catch (Exception $e) {
    $pendingApprovals = 0;
    $totalStudents    = 0;
    $totalTutors      = 0;
    $totalClasses     = 0;
    $recentPending    = [];
}

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-welcome">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <strong><?= h(me()['username']) ?></strong>. Here is your academy overview for today.</p>
</div>

<!-- Summary Metric Cards -->
<div class="dashboard-stats-grid">
    
    <div class="panel stat-card warning-card">
        <div class="stat-header">
            <span class="stat-label">Pending Approvals</span>
            <span class="stat-icon">⏳</span>
        </div>
        <span class="stat-value"><?= $pendingApprovals ?></span>
        <a href="<?= h(url('admin/approve_accounts.php')) ?>" class="stat-link">Review accounts &rarr;</a>
    </div>

    <div class="panel stat-card info-card">
        <div class="stat-header">
            <span class="stat-label">Active Students</span>
            <span class="stat-icon">🎓</span>
        </div>
        <span class="stat-value"><?= $totalStudents ?></span>
        <a href="<?= h(url('student_management/index.php')) ?>" class="stat-link">Manage students &rarr;</a>
    </div>

    <div class="panel stat-card success-card">
        <div class="stat-header">
            <span class="stat-label">Active Tutors</span>
            <span class="stat-icon">👩‍🏫</span>
        </div>
        <span class="stat-value"><?= $totalTutors ?></span>
        <a href="<?= h(url('tutor_management/index.php')) ?>" class="stat-link">Manage tutors &rarr;</a>
    </div>

    <div class="panel stat-card purple-card">
        <div class="stat-header">
            <span class="stat-label">Total Classes</span>
            <span class="stat-icon">📚</span>
        </div>
        <span class="stat-value"><?= $totalClasses ?></span>
        <a href="<?= h(url('class_management/index.php')) ?>" class="stat-link">View classes &rarr;</a>
    </div>

</div>

<!-- Quick Actions Panel -->
<div class="panel dashboard-section">
    <h2>Quick Actions</h2>
    <p class="section-desc">Jump directly to frequent administrative tasks.</p>
    
    <div class="dashboard-actions">
        <a href="<?= h(url('admin/approve_accounts.php')) ?>" class="button secondary">Approve Accounts</a>
        <a href="<?= h(url('student_management/index.php')) ?>" class="button secondary">Students</a>
        <a href="<?= h(url('tutor_management/index.php')) ?>" class="button secondary">Tutors</a>
        <a href="<?= h(url('class_management/timetable.php')) ?>" class="button secondary">Timetable</a>
    </div>
</div>

<!-- Recent Pending Approvals Table -->
<?php if (!empty($recentPending)): ?>
<div class="panel dashboard-section">
    <h2>Recent Pending Accounts</h2>
    <p class="section-desc">Newly registered accounts waiting for activation.</p>
    
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentPending as $user): ?>
                <tr>
                    <td><?= h($user['username']) ?></td>
                    <td><?= h($user['email']) ?></td>
                    <td><span class="badge"><?= h(ucfirst($user['role'])) ?></span></td>
                    <td>
                        <a href="<?= h(url('admin/approve_accounts.php')) ?>" class="button secondary" style="padding: 4px 10px; font-size: 0.8rem;">Review</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php 
require __DIR__ . '/../includes/sidebar.php'; 
require __DIR__ . '/../includes/footer.php'; 
?>