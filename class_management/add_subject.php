
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $id = trim($_POST['subject_id'] ?? '');
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $id)) {
        $error = 'Invalid Subject ID.';
    } elseif ($name === '' || strlen($name) > 180) {
        $error = 'Enter a valid subject name.';
    } elseif (strlen($code) > 30 || strlen($department) > 120) {
        $error = 'Subject code or department is too long.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO subjects
                (subject_id, subject_name, subject_code, department)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $id, $name, $code ?: null, $department ?: null
            ]);

            notice('Subject added successfully.');
            go('class_management/subjects.php');
        } catch (PDOException $e) {
            $error = 'Unable to add subject. Subject ID or code may already exist.';
        }
    }
}

$pageTitle = 'Add Subject';
require __DIR__ . '/../includes/header.php';
?>

<h1>Add Subject</h1>

<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>

<section class="panel">
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label>Subject ID
        <input name="subject_id" maxlength="12"
               placeholder="SUB011"
               value="<?= h($_POST['subject_id'] ?? '') ?>" required>
    </label>

    <label>Subject Code
        <input name="subject_code" maxlength="30"
               placeholder="MATH"
               value="<?= h($_POST['subject_code'] ?? '') ?>">
    </label>

    <label>Subject Name
        <input name="subject_name" maxlength="180"
               placeholder="Mathematics"
               value="<?= h($_POST['subject_name'] ?? '') ?>" required>
    </label>

    <label>Department
        <input name="department" maxlength="120"
               value="<?= h($_POST['department'] ?? '') ?>">
    </label>

    <button type="submit">Save Subject</button>
    <a href="<?= h(url('class_management/subjects.php')) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
