
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['class_id'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM classes WHERE class_id = ?");
$stmt->execute([$id]);
$class = $stmt->fetch();

if (!$class) {
    http_response_code(404);
    exit('Class not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $grade = trim($_POST['grade_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $year = (int)($_POST['academic_year'] ?? 0);

    if ($grade === '' || strlen($grade) > 60) {
        $errors[] = 'Invalid grade level.';
    }
    if (strlen($section) > 30) {
        $errors[] = 'Section is too long.';
    }
    if ($year < 2000 || $year > 2100) {
        $errors[] = 'Invalid academic year.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            UPDATE classes
            SET grade_level = ?, section = ?, academic_year = ?
            WHERE class_id = ?
        ");
        $stmt->execute([$grade, $section ?: null, $year, $id]);

        notice('Class updated successfully.');
        go('class_management/view_class.php?id=' . urlencode($id));
    }

    $class['grade_level'] = $grade;
    $class['section'] = $section;
    $class['academic_year'] = $year;
}

$pageTitle = 'Edit Class';
require __DIR__ . '/../includes/header.php';
?>

<h1>Edit Class <?= h($id) ?></h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="class_id" value="<?= h($id) ?>">

    <label>Grade Level
        <input name="grade_level" maxlength="60"
               value="<?= h($class['grade_level']) ?>" required>
    </label>

    <label>Section
        <input name="section" maxlength="30"
               value="<?= h($class['section']) ?>">
    </label>

    <label>Academic Year
        <input type="number" name="academic_year"
               min="2000" max="2100"
               value="<?= h($class['academic_year']) ?>" required>
    </label>

    <button type="submit">Update Class</button>
    <a href="<?= h(url('class_management/index.php')) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
