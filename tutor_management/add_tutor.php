<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$errors = [];
$staffId = '';
$staffName = '';
$staffRole = 'Lecturer';
$staffEmail = '';
$staffPhone = '';
$username = '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $staffId = trim($_POST['staff_id'] ?? '');
    $staffName = trim($_POST['staff_name'] ?? '');
    $staffRole = trim($_POST['staff_role'] ?? 'Lecturer');
    $staffEmail = trim($_POST['staff_email'] ?? '');
    $staffPhone = trim($_POST['staff_phone_number'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $staffId)) {
        $errors[] = 'Enter a valid Tutor ID (maximum 12 characters, alphanumeric/underscores).';
    }

    if ($staffName === '' || strlen($staffName) > 120) {
        $errors[] = 'Enter a valid tutor name.';
    }

    if ($staffEmail === '' || !filter_var($staffEmail, FILTER_VALIDATE_EMAIL) || strlen($staffEmail) > 180) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($staffPhone) > 30) {
        $errors[] = 'Phone number is too long.';
    }

    if ($username !== '') {
        if (strlen($username) > 50) {
            $errors[] = 'Username is too long.';
        }
        
        // Password policy: 12-16 chars, at least 1 uppercase letter, at least 1 special character
        if (strlen($password) < 12 || strlen($password) > 16) {
            $errors[] = 'Password must be between 12 and 16 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must include at least 1 capital letter.';
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must include at least 1 special character.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_id = ?");
            $checkStmt->execute([$staffId]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('The Tutor ID is already in use.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO staff (staff_id, staff_name, staff_role, staff_email, staff_phone_number)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $staffId,
                $staffName,
                $staffRole,
                $staffEmail,
                $staffPhone ?: null
            ]);

            if ($username !== '') {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtAccount = $pdo->prepare("
                    INSERT INTO user_accounts (staff_id, username, password, email, role, is_active)
                    VALUES (?, ?, ?, ?, 'staff', 1)
                ");
                $stmtAccount->execute([
                    $staffId,
                    $username,
                    $hashedPassword,
                    $staffEmail
                ]);
            }

            $pdo->commit();

            notice('Tutor added successfully.');
            go('tutor_management/view_tutor.php?id=' . urlencode($staffId));

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to add tutor: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add New Tutor';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Add New Tutor</h1>
        <p style="margin: 4px 0 0 0;">Create a new tutor profile and optional login credentials.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <p class="error" style="text-align: center; background: rgba(255,107,107,0.1); padding: 12px; border-radius: 8px; margin-bottom: 16px; color: #ff6b6b;"><?= h($error) ?></p>
    <?php endforeach; ?>

    <section class="panel" style="padding: 28px;">
    <form method="post">

        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

        <label>Tutor ID
            <input type="text" name="staff_id"
                   maxlength="12"
                   value="<?= h($staffId) ?>"
                   placeholder="e.g. STF005"
                   required>
        </label>

        <label>Full Name
            <input type="text" name="staff_name"
                   maxlength="120"
                   value="<?= h($staffName) ?>"
                   placeholder="Enter full name"
                   required>
        </label>

        <label>Role / Position
            <input type="text" name="staff_role"
                   maxlength="50"
                   value="<?= h($staffRole) ?>"
                   placeholder="e.g. Lecturer, Tutor, Supervisor"
                   required>
        </label>

        <label>Email Address
            <input type="email" name="staff_email"
                   maxlength="180"
                   value="<?= h($staffEmail) ?>"
                   placeholder="e.g. tutor@eduvex.test"
                   required>
        </label>

        <label>Phone Number
            <input type="text" name="staff_phone_number"
                   maxlength="30"
                   value="<?= h($staffPhone) ?>"
                   placeholder="e.g. 012-345-6789">
        </label>

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 24px 0;">

        <h3 style="margin-bottom: 16px;">Login Account</h3>

        <label>Login Username
            <input type="text" name="username"
                   maxlength="50"
                   value="<?= h($username) ?>"
                   placeholder="Leave blank if no login account needed">
        </label>

        <label>Password
            <input type="password" name="password"
                   minlength="12"
                   maxlength="16"
                   placeholder="12-16 chars, 1 uppercase, 1 special char">
        </label>

        <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" style="flex: 1;">Add Tutor</button>
            <a href="<?= h(url('tutor_management/index.php')) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                Cancel
            </a>
        </div>
    </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
