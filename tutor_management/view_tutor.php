<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Tutor ID is required.');
}

$stmt = $pdo->prepare("
    SELECT st.*, 
           u.username, u.is_active
    FROM staff st
    LEFT JOIN user_accounts u ON u.staff_id = st.staff_id
    WHERE st.staff_id = ?
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

<h1>Tutor Profile</h1>

<section class="panel">

    <p><strong>Tutor ID:</strong>
        <?= h($tutor['staff_id']) ?></p>

    <p><strong>Name:</strong>
        <?= h($tutor['staff_name']) ?></p>

    <p><strong>Position / Role:</strong>
        <?= h($tutor['staff_role']) ?></p>

    <p><strong>Email:</strong>
        <?= h($tutor['staff_email']) ?></p>

    <p><strong>Phone:</strong>
        <?= h($tutor['staff_phone_number']) ?></p>

    <h2>Login Credentials</h2>

    <p><strong>Login Username:</strong>
        <code><?= h($tutor['username'] ?? 'No Login Account Assigned') ?></code></p>

    <p><strong>Account Status:</strong>
        <?php
        if ($tutor['is_active'] === null) {
            echo 'No Account';
        } else {
            echo $tutor['is_active'] ? 'Active' : 'Inactive';
        }
        ?>
    </p>

    <p class="att-small" style="color: #a0aec0;">
        <em>Note: Passwords are securely hashed using bcrypt in the database and cannot be viewed in plaintext for security reasons.</em>
    </p>

    <br>
    <a class="button" href="<?= h(url('tutor_management/index.php')) ?>">
        Back to Tutor List
    </a>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>