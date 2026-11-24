<?php
require_once __DIR__ . '/../includes/calendar_functions.php';
cal_require_admin();

$errors = [];
$values = ['date' => '', 'day_type' => '', 'description' => '', 'is_tuition_day' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cal_csrf_check($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Session expired. Please try again.';
    } else {
        [$errors, $clean] = cal_validate($_POST);
        $values = $clean;

        if (!$errors) {
            try {
                cal_create($clean);
                cal_flash('success', 'Holiday added successfully.');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $errors['form'] = 'Could not save. Please try again.';
            }
        }
    }
}

$submitLabel = 'Save Holiday';
$page_title  = 'Add Holiday';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/calendar.css">

<section class="cal-page">
    <div class="cal-head">
        <div>
            <p class="cal-eyebrow">Academic calendar</p>
            <h1>Add Holiday</h1>
        </div>
    </div>

    <?php if (isset($errors['form'])): ?>
        <div class="cal-alert cal-alert-error"><?= cal_h($errors['form']) ?></div>
    <?php endif; ?>

    <div class="cal-card">
        <form method="post" class="cal-form" novalidate>
    <input type="hidden" name="csrf" value="<?= cal_h(cal_csrf_token()) ?>">

    <div class="cal-field">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= cal_h($values['date']) ?>" required>
        <?php if (isset($errors['date'])): ?>
            <small class="cal-error"><?= cal_h($errors['date']) ?></small>
        <?php endif; ?>
    </div>

    <div class="cal-field">
        <label for="day_type">Day type</label>
        <select id="day_type" name="day_type" required>
            <option value="">-- Select --</option>
            <?php foreach (CAL_DAY_TYPES as $t): ?>
                <option value="<?= cal_h($t) ?>" <?= $values['day_type'] === $t ? 'selected' : '' ?>>
                    <?= cal_h($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['day_type'])): ?>
            <small class="cal-error"><?= cal_h($errors['day_type']) ?></small>
        <?php endif; ?>
    </div>

    <div class="cal-field">
        <label for="description">Description</label>
        <input type="text" id="description" name="description" maxlength="255"
               value="<?= cal_h($values['description']) ?>" required>
        <?php if (isset($errors['description'])): ?>
            <small class="cal-error"><?= cal_h($errors['description']) ?></small>
        <?php endif; ?>
    </div>

    <div class="cal-field cal-check">
        <label>
            <input type="checkbox" name="is_tuition_day" value="1" <?= !empty($values['is_tuition_day']) ? 'checked' : '' ?>>
            Tuition classes still run on this day
        </label>
    </div>

    <div class="cal-actions">
        <button type="submit" class="cal-btn cal-btn-primary"><?= cal_h($submitLabel) ?></button>
        <a href="index.php" class="cal-btn">Cancel</a>
    </div>
</form>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>