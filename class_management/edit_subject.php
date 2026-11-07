
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['subject_id'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM subjects WHERE subject_id = ?");
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    http_response_code(404);
    exit('Subject not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['subject_name'] ?? '');
    $code = trim($_POST['subject_code'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if ($name === '' || strlen($name) > 180) {
        $error = 'Invalid subject name.';
    } elseif (strlen($code) > 30 || strlen($department) > 120) {
        $error = 'Subject code or department is too long.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE subjects
                SET subject_name = ?, subject_code = ?, department = ?
                WHERE subject_id = ?
            ");
            $stmt->execute([
                $name, $code ?: null, $department ?: null, $id
            ]);

            notice('Subject updated successfully.');
            go('class_management/subjects.php');
        } catch (PDOException $e) {
            $error = 'Unable to update subject. Subject code may already exist.';
        }
    }

    $subject['subject_name'] = $name;
    $subject['subject_code'] = $code;
    $subject['department'] = $department;
}

$pageTitle = 'Edit Subject';
require __DIR__ . '/../includes/header.php';
?>

<h1>Edit Subject — <?= h($id) ?></h1>

<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>

<section class="panel">
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="subject_id" value="<?= h($id) ?>">

    <label>Subject Name
        <input name="subject_name" maxlength="180"
               value="<?= h($subject['subject_name']) ?>" required>
    </label>

    <label>Subject Code
        <input name="subject_code" maxlength="30"
               value="<?= h($subject['subject_code']) ?>">
    </label>

    <label>Department
        <input name="department" maxlength="120"
               value="<?= h($subject['department']) ?>">
    </label>

    <button type="submit">Update Subject</button>
    <a href="<?= h(url('class_management/subjects.php')) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
