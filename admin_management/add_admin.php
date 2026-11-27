
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $adminId = trim($_POST['admin_id'] ?? '');
    $name = trim($_POST['admin_name'] ?? '');
    $email = trim($_POST['admin_email'] ?? '');
    $phone = trim($_POST['admin_phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate Admin ID
    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $adminId)) {
        $errors[] = 'Enter a valid Admin ID (maximum 12 characters, alphanumeric/underscores).';
    }

    // Validate Full Name
    if ($name === '' || strlen($name) > 120) {
        $errors[] = 'Enter a valid administrator name.';
    }

    // Validate Email
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
        $errors[] = 'Enter a valid email address.';
    }

    // Validate Phone Number
    if (strlen($phone) > 30) {
        $errors[] = 'Phone number is too long.';
    }

    // Validate Username
    if ($username === '' || strlen($username) > 50) {
        $errors[] = 'Enter a valid login username.';
    }

    // Password validation
    // Minimum 12 characters, 1 uppercase letter, 1 special character
    if (strlen($password) < 12) {
        $errors[] = 'Password must contain at least 12 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one capital letter.';
    } elseif (!preg_match('/[\W_]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    // Insert data if there are no validation errors
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1. Insert into admins table
            $stmt = $pdo->prepare("
                INSERT INTO admins (
                    admin_id,
                    admin_name,
                    admin_email,
                    admin_phone_number
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $adminId,
                $name,
                $email,
                $phone ?: null
            ]);

            // 2. Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // 3. Insert into user_accounts table
            $stmtAccount = $pdo->prepare("
                INSERT INTO user_accounts (
                    admin_id,
                    username,
                    email,
                    password_hash,
                    role
                )
                VALUES (?, ?, ?, ?, 'admin')
            ");

            $stmtAccount->execute([
                $adminId,
                $username,
                $email,
                $passwordHash
            ]);

            // 4. Commit transaction
            $pdo->commit();

            notice('New administrator account created successfully.');
            go('admin_management/index.php');

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Unable to create admin. The Admin ID, email, or username may already exist in the system.';
        }
    }
}

$pageTitle = 'Add New Admin';
require __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">

    <div>
        <h1 style="margin: 0;">Add New Admin</h1>
        <p style="margin: 4px 0 0 0;">
            Create a new administrator profile and login credentials.
        </p>
    </div>

    <a href="<?= h(url('admin_management/index.php')) ?>" class="button button-outline">
        Back to Admins
    </a>

</div>

<div style="max-width: 600px; margin: 0 auto;">

    <?php foreach ($errors as $error): ?>
        <p class="error" style="text-align: center; background: rgba(255,107,107,0.1); padding: 10px; border-radius: 8px; margin-bottom: 16px;">
            <?= h($error) ?>
        </p>
    <?php endforeach; ?>

    <section class="panel" style="padding: 28px;">

        <form method="post">

            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

            <label>Admin ID
                <input
                    type="text"
                    name="admin_id"
                    maxlength="12"
                    value="<?= h($_POST['admin_id'] ?? '') ?>"
                    placeholder="ADM011"
                    required
                >
            </label>

            <label>Full Name
                <input
                    type="text"
                    name="admin_name"
                    maxlength="120"
                    value="<?= h($_POST['admin_name'] ?? '') ?>"
                    required
                >
            </label>

            <label>Email Address
                <input
                    type="email"
                    name="admin_email"
                    maxlength="180"
                    value="<?= h($_POST['admin_email'] ?? '') ?>"
                    required
                >
            </label>

            <label>Phone Number
                <input
                    type="text"
                    name="admin_phone"
                    maxlength="30"
                    value="<?= h($_POST['admin_phone'] ?? '') ?>"
                >
            </label>

            <hr style="border: 0; border-top: 1px solid var(--line); margin: 24px 0;">

            <label>Login Username
                <input
                    type="text"
                    name="username"
                    maxlength="50"
                    value="<?= h($_POST['username'] ?? '') ?>"
                    placeholder="username123"
                    required
                >
            </label>

            <label>Login Password
                <input
                    type="password"
                    name="password"
                    placeholder="Minimum 12 chars, 1 capital, 1 special character"
                    required
                >
            </label>

            <p style="font-size: 13px; color: var(--muted); margin-top: 4px;">
                Password must contain at least 12 characters, one uppercase letter and one special character. No maximum length.
            </p>

            <div style="display: flex; gap: 12px; margin-top: 24px;">

                <button type="submit" style="flex: 1;">
                    Create Admin
                </button>

                <a
                    href="<?= h(url('admin_management/index.php')) ?>"
                    class="button button-outline"
                    style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;"
                >
                    Cancel
                </a>

            </div>

        </form>

    </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
