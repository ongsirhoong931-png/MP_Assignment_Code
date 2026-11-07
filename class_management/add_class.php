
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $id = trim($_POST['class_id'] ?? '');
    $grade = trim($_POST['grade_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $year = (int)($_POST['academic_year'] ?? 0);

    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $id)) {
        $errors[] = 'Invalid Class ID.';
    }
    if ($grade === '' || strlen($grade) > 60) {
        $errors[] = 'Enter a valid grade level.';
    }
    if (strlen($section) > 30) {
        $errors[] = 'Section is too long.';
    }
    if ($year < 2000 || $year > 2100) {
        $errors[] = 'Enter a valid academic year.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO classes
                (class_id, grade_level, section, academic_year)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id, $grade, $section ?: null, $year]);

            notice('Class added successfully.');
            go('class_management/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Unable to add class. The Class ID may already exist.';
        }
    }
}

$pageTitle = 'Add Class';
require __DIR__ . '/../includes/header.php';
?>

<h1>Add Tuition Class</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label>Class ID
        <input type="text" name="class_id" maxlength="12"
               placeholder="CLS011"
               value="<?= h($_POST['class_id'] ?? '') ?>" required>
    </label>

    <label>Grade Level
        <input type="text" name="grade_level" maxlength="60"
               placeholder="Form 4"
               value="<?= h($_POST['grade_level'] ?? '') ?>" required>
    </label>

    <label>Section
        <input type="text" name="section" maxlength="30"
               placeholder="A"
               value="<?= h($_POST['section'] ?? '') ?>">
    </label>

    <label>Academic Year
        <input type="number" name="academic_year"
               min="2000" max="2100"
               value="<?= h($_POST['academic_year'] ?? date('Y')) ?>" required>
    </label>

    <button type="submit">Save Class</button>
    <a href="<?= h(url('class_management/index.php')) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
