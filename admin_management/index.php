<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$keyword = trim($_GET['keyword'] ?? '');

// Fetch raw admin and user account records safely without assuming specific column names
$query = "
    SELECT 
        a.*, 
        u.*
    FROM admins a
    LEFT JOIN user_accounts u ON a.admin_id = u.admin_id
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$rawAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admins = [];
foreach ($rawAdmins as $adm) {
    $adminId = $adm['admin_id'] ?? array_values($adm)[0];
    $adminName = $adm['admin_name'] ?? $adm['name'] ?? $adm['full_name'] ?? 'Unknown Admin';
    $adminEmail = $adm['email'] ?? $adm['admin_email'] ?? '-';
    $adminPhone = $adm['phone'] ?? $adm['phone_number'] ?? $adm['admin_phone'] ?? '-';
    $username = $adm['username'] ?? null;
    
    // Determine login status safely regardless of column schema
    $loginStatus = 'No Account';
    if (!empty($username)) {
        $loginStatus = $adm['status'] ?? $adm['is_active'] ?? $adm['account_status'] ?? 'Active';
    }
    
    // Filter by keyword if provided
    if ($keyword !== '') {
        $kw = strtolower($keyword);
        $matches = str_contains(strtolower($adminId), $kw) ||
                   str_contains(strtolower($adminName), $kw) ||
                   str_contains(strtolower($adminEmail), $kw) ||
                   str_contains(strtolower($adminPhone), $kw) ||
                   str_contains(strtolower($username ?? ''), $kw);
        if (!$matches) {
            continue;
        }
    }

    $admins[] = [
        'admin_id' => $adminId,
        'name' => $adminName,
        'email' => $adminEmail,
        'phone' => $adminPhone,
        'username' => $username,
        'login_status' => ucfirst($loginStatus)
    ];
}

$pageTitle = 'Admin Management';
require __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0;">Admin Management</h1>
        <p style="margin: 4px 0 0 0;">View administrator profiles and login credentials.</p>
    </div>

    <!-- Add New Admin Button (Admin Only) -->
    <?php if (me()['role'] === 'admin'): ?>
        <a href="<?= h(url('admin_management/add_admin.php')) ?>" class="button">
            + Add New Admin
        </a>
    <?php endif; ?>
</div>

<!-- Search Form Panel -->
<section class="panel" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <label style="margin-bottom: 4px; font-size: 14px;">Search Administrator</label>
            <input type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="Search by ID, Name, Email, Phone, or Username">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit">Search</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= h(url('admin_management/index.php')) ?>" class="button button-outline" style="display: inline-flex; align-items: center; justify-content: center;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Admin ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Username</th>
                <th>Login Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($admins)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">No administrators found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?= h($admin['admin_id']) ?></td>
                        <td><?= h($admin['name']) ?></td>
                        <td><?= h($admin['email']) ?></td>
                        <td><?= h($admin['phone']) ?></td>
                        <td><?= h($admin['username'] ?? 'No Username') ?></td>
                        <td><?= h($admin['login_status']) ?></td>
                        <td>
                            <a href="<?= h(url('admin_management/view_admin.php?id=' . urlencode($admin['admin_id']))) ?>">View</a> |
                            <a href="<?= h(url('admin_management/edit_admin.php?id=' . urlencode($admin['admin_id']))) ?>">Edit</a> |
                            <a href="<?= h(url('admin_management/status_admin.php?id=' . urlencode($admin['admin_id']))) ?>">Status</a> |
                            <a href="<?= h(url('admin_management/delete_admin.php?id=' . urlencode($admin['admin_id']))) ?>" style="color: #ff6b6b;" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>