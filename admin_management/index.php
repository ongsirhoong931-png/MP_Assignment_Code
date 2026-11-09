<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$stmt = $pdo->query("
    SELECT a.*, 
           u.username, u.is_active AS account_active
    FROM admins a
    LEFT JOIN user_accounts u ON u.admin_id = a.admin_id
    ORDER BY a.admin_id
");

$admins = $stmt->fetchAll();

$pageTitle = 'Admin Management';
require __DIR__ . '/../includes/header.php';
?>

<h1>Admin Management</h1>

<p>View administrator profiles and login credentials.</p>

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
    <?php foreach ($admins as $adm): ?>
        <tr>
            <td><?= h($adm['admin_id']) ?></td>
            <td><?= h($adm['admin_name']) ?></td>
            <td><?= h($adm['admin_email']) ?></td>
            <td><?= h($adm['admin_phone_number']) ?></td>
            <td><strong><?= h($adm['username'] ?? 'No Username') ?></strong></td>
            <td>
                <?php
                if ($adm['account_active'] === null) {
                    echo 'No Account';
                } elseif ($adm['account_active']) {
                    echo 'Active';
                } else {
                    echo 'Inactive';
                }
                ?>
            </td>
            <td>
                <a href="<?= h(url(
                    'admin_management/view_admin.php?id=' .
                    urlencode($adm['admin_id'])
                )) ?>">View Details</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>