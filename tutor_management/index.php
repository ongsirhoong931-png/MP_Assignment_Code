<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$stmt = $pdo->query("
    SELECT st.*, 
           u.username, u.is_active AS account_active
    FROM staff st
    LEFT JOIN user_accounts u ON u.staff_id = st.staff_id
    ORDER BY st.staff_id
");

$tutors = $stmt->fetchAll();

$pageTitle = 'Tutor Management';
require __DIR__ . '/../includes/header.php';
?>

<h1>Tutor Management</h1>

<p>View tutor profiles and login credentials.</p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Tutor ID</th>
            <th>Name</th>
            <th>Role</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Username</th>
            <th>Login Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($tutors as $tutor): ?>
        <tr>
            <td><?= h($tutor['staff_id']) ?></td>
            <td><?= h($tutor['staff_name']) ?></td>
            <td><?= h($tutor['staff_role']) ?></td>
            <td><?= h($tutor['staff_email']) ?></td>
            <td><?= h($tutor['staff_phone_number']) ?></td>
            <td><strong><?= h($tutor['username'] ?? 'No Username') ?></strong></td>
            <td>
                <?php
                if ($tutor['account_active'] === null) {
                    echo 'No Account';
                } elseif ($tutor['account_active']) {
                    echo 'Active';
                } else {
                    echo 'Inactive';
                }
                ?>
            </td>
            <td>
                <a href="<?= h(url(
                    'tutor_management/view_tutor.php?id=' .
                    urlencode($tutor['staff_id'])
                )) ?>">View Details</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>