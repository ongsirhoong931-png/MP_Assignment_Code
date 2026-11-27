<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Tutor ID is required.');
}

$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.is_active
    FROM staff t
    LEFT JOIN user_accounts u ON t.staff_id = u.staff_id
    WHERE t.staff_id = ?
");
$stmt->execute([$id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(404);
    exit('Tutor not found.');
}

$pageTitle = 'View Tutor Profile';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Tutor Profile</h1>
        <p style="margin: 4px 0 0 0;">View tutor details and login credentials.</p>
    </div>

    <section class="panel" style="padding: 28px;">
        <p style="margin-bottom: 12px;"><strong>Tutor ID:</strong><br><?= h($tutor['staff_id']) ?></p>

        <p style="margin-bottom: 12px;"><strong>Name:</strong><br><?= h($tutor['staff_name']) ?></p>

        <p style="margin-bottom: 12px;"><strong>Role:</strong><br><?= h($tutor['role_title'] ?? $tutor['staff_role'] ?? 'Lecturer') ?></p>

        <p style="margin-bottom: 12px;"><strong>Email:</strong><br><?= h($tutor['staff_email'] ?? '-') ?></p>

        <p style="margin-bottom: 20px;"><strong>Phone:</strong><br><?= h($tutor['staff_phone_number'] ?? '-') ?></p>

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">

        <h3 style="margin-bottom: 16px;">Login Credentials</h3>

        <p style="margin-bottom: 12px;"><strong>Login Username:</strong><br><?= h($tutor['username'] ?? 'No Login Account') ?></p>

        <p style="margin-bottom: 24px;"><strong>Account Status:</strong><br>
            <?php
            if ($tutor['is_active'] === null) {
                echo '<span style="color: #888;">No Account</span>';
            } elseif ($tutor['is_active']) {
                echo '<span style="color: #51cf66;">Active</span>';
            } else {
                echo '<span style="color: #ff6b6b;">Inactive</span>';
            }
            ?>
        </p>

        <div style="display: flex; gap: 12px; align-items: center; margin-top: 24px;">
            <a class="button" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;" href="<?= h(url('tutor_management/edit_tutor.php?id=' . urlencode($id))) ?>">Edit Tutor</a>
            <a href="<?= h(url('tutor_management/index.php')) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">Back</a>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>