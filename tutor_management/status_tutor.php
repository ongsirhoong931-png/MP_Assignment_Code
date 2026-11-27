<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['tutor_id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Tutor ID is required.');
}

$stmt = $pdo->prepare("
    SELECT t.staff_id,
           t.staff_name,
           u.user_id,
           u.username,
           u.is_active
    FROM staff t
    LEFT JOIN user_accounts u
        ON u.staff_id = t.staff_id
    WHERE t.staff_id = ?
");

$stmt->execute([$id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(404);
    exit('Tutor not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $status = $_POST['status'] ?? '';

    if ($tutor['user_id'] === null) {
        $errors[] = 'This tutor does not have a login account.';
    } elseif (!in_array($status, ['0', '1'], true)) {
        $errors[] = 'Invalid account status.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            UPDATE user_accounts
            SET is_active = ?
            WHERE user_id = ?
              AND role = 'staff'
              AND staff_id = ?
        ");

        $stmt->execute([
            (int)$status,
            $tutor['user_id'],
            $id
        ]);

        notice('Tutor login status updated successfully.');

        go('tutor_management/view_tutor.php?id=' .
            urlencode($id));
    }
}

$pageTitle = 'Tutor Account Status';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Tutor Login Status</h1>
        <p style="margin: 4px 0 0 0;">Manage tutor account access and login privileges.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <p class="error" style="text-align: center; background: rgba(255,107,107,0.1); padding: 12px; border-radius: 8px; margin-bottom: 16px; color: #ff6b6b;"><?= h($error) ?></p>
    <?php endforeach; ?>

    <section class="panel" style="padding: 28px;">

        <p style="margin-bottom: 16px;"><strong>Tutor ID:</strong><br><?= h($tutor['staff_id']) ?></p>

        <p style="margin-bottom: 16px;"><strong>Tutor Name:</strong><br><?= h($tutor['staff_name']) ?></p>

        <p style="margin-bottom: 16px;"><strong>Username:</strong><br><?= h($tutor['username'] ?? 'No Account') ?></p>

        <p style="margin-bottom: 24px;"><strong>Current Login Status:</strong><br>
            <?php
            if ($tutor['user_id'] === null) {
                echo '<span style="color: #888;">No Account</span>';
            } elseif ($tutor['is_active']) {
                echo '<span style="color: #51cf66;">Active</span>';
            } else {
                echo '<span style="color: #ff6b6b;">Inactive</span>';
            }
            ?>
        </p>

        <?php if ($tutor['user_id'] !== null): ?>

            <form method="post">

                <input type="hidden"
                       name="csrf"
                       value="<?= h(csrf()) ?>">

                <input type="hidden"
                       name="tutor_id"
                       value="<?= h($id) ?>">

                <label style="margin-bottom: 20px; display: block;">Change Login Status
                    <select name="status" required style="margin-top: 6px;">
                        <option value="1"
                            <?= $tutor['is_active'] ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="0"
                            <?= !$tutor['is_active'] ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                </label>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" style="flex: 1;">
                        Update Status
                    </button>
                    <a href="<?= h(url('tutor_management/view_tutor.php?id=' . urlencode($id))) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                        Cancel
                    </a>
                </div>

            </form>

        <?php else: ?>

            <p style="margin-bottom: 24px; opacity: 0.9;">
                Create a login account for this tutor
                through User Management before changing
                the account status.
            </p>

            <a href="<?= h(url('tutor_management/view_tutor.php?id=' . urlencode($id))) ?>" class="button button-outline" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                Back to Tutor Profile
            </a>

        <?php endif; ?>

    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>