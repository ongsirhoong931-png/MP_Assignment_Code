<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$keyword = trim($_GET['keyword'] ?? '');

$query = "
    SELECT st.*, 
           u.username, u.is_active AS account_active
    FROM staff st
    LEFT JOIN user_accounts u ON u.staff_id = st.staff_id
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$rawTutors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tutors = [];
foreach ($rawTutors as $tutor) {
    $tutorId = $tutor['staff_id'];
    $tutorName = $tutor['staff_name'] ?? 'Unknown Tutor';
    $tutorRole = $tutor['staff_role'] ?? 'Lecturer';
    $tutorEmail = $tutor['staff_email'] ?? '-';
    $tutorPhone = $tutor['staff_phone_number'] ?? '-';
    $username = $tutor['username'] ?? null;
    $accountActive = $tutor['account_active'];

    if ($keyword !== '') {
        $kw = strtolower($keyword);
        $matches = str_contains(strtolower($tutorId), $kw) ||
                   str_contains(strtolower($tutorName), $kw) ||
                   str_contains(strtolower($tutorRole), $kw) ||
                   str_contains(strtolower($tutorEmail), $kw) ||
                   str_contains(strtolower($tutorPhone), $kw) ||
                   str_contains(strtolower($username ?? ''), $kw);
        if (!$matches) {
            continue;
        }
    }

    $tutors[] = [
        'staff_id' => $tutorId,
        'staff_name' => $tutorName,
        'staff_role' => $tutorRole,
        'staff_email' => $tutorEmail,
        'staff_phone_number' => $tutorPhone,
        'username' => $username,
        'account_active' => $accountActive
    ];
}

$pageTitle = 'Tutor Management';
require __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0;">Tutor Management</h1>
        <p style="margin: 4px 0 0 0;">View tutor profiles and login credentials.</p>
    </div>

    <?php if (me()['role'] === 'admin'): ?>
        <a href="<?= h(url('tutor_management/add_tutor.php')) ?>" class="button">
            + Add New Tutor
        </a>
    <?php endif; ?>
</div>

<section class="panel" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <label style="margin-bottom: 4px; font-size: 14px;">Search Tutor</label>
            <input type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="Search by ID, Name, Role, Email, Phone, or Username">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit">Search</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= h(url('tutor_management/index.php')) ?>" class="button button-outline" style="display: inline-flex; align-items: center; justify-content: center;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
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
        <?php if (empty($tutors)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 20px;">No tutors found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($tutors as $tutor): ?>
                <tr>
                    <td><?= h($tutor['staff_id']) ?></td>
                    <td><?= h($tutor['staff_name']) ?></td>
                    <td><?= h($tutor['staff_role']) ?></td>
                    <td><?= h($tutor['staff_email']) ?></td>
                    <td><?= h($tutor['staff_phone_number']) ?></td>
                    <td><?= h($tutor['username'] ?? 'No Username') ?></td>
                    <td>
                        <?php
                        if ($tutor['account_active'] === null) {
                            echo '<span style="color: #888;">No Account</span>';
                        } elseif ($tutor['account_active']) {
                            echo '<span style="color: #51cf66;">Active</span>';
                        } else {
                            echo '<span style="color: #ff6b6b;">Inactive</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="<?= h(url('tutor_management/view_tutor.php?id=' . urlencode($tutor['staff_id']))) ?>">View</a> |
                        <a href="<?= h(url('tutor_management/edit_tutor.php?id=' . urlencode($tutor['staff_id']))) ?>">Edit</a> |
                        <a href="<?= h(url('tutor_management/status_tutor.php?id=' . urlencode($tutor['staff_id']))) ?>">Status</a> |
                        <a href="<?= h(url('tutor_management/delete_tutor.php?id=' . urlencode($tutor['staff_id']))) ?>" style="color: #ff6b6b;" onclick="return confirm('Are you sure you want to delete this tutor?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>