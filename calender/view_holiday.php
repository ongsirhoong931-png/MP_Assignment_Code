<?php
require_once __DIR__ . '/../includes/calendar_functions.php';
cal_require_login();

$id      = $_GET['id'] ?? '';
$holiday = $id !== '' ? cal_find($id) : null;

if (!$holiday) {
    cal_flash('error', 'Holiday not found.');
    header('Location: index.php');
    exit;
}

$canEdit    = cal_can_manage();
$page_title = 'View Holiday';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/calendar.css">

<section class="cal-page">
    <div class="cal-head">
        <div>
            <p class="cal-eyebrow">Academic calendar</p>
            <h1>Holiday Details</h1>
        </div>
    </div>

    <div class="cal-card">
        <dl class="cal-details">
            <dt>ID</dt>
            <dd><?= cal_h($holiday['override_id']) ?></dd>

            <dt>Date</dt>
            <dd><?= cal_h(date('l, d F Y', strtotime($holiday['date']))) ?></dd>

            <dt>Day type</dt>
            <dd><span class="cal-badge"><?= cal_h($holiday['day_type']) ?></span></dd>

            <dt>Description</dt>
            <dd><?= cal_h($holiday['description']) ?></dd>

            <dt>Tuition day</dt>
            <dd>
                <span class="cal-badge <?= $holiday['is_tuition_day'] ? 'cal-badge-yes' : 'cal-badge-no' ?>">
                    <?= $holiday['is_tuition_day'] ? 'Yes, classes still run' : 'No, no classes' ?>
                </span>
            </dd>
        </dl>

        <div class="cal-actions">
            <a href="index.php" class="cal-btn">Back</a>
            <?php if ($canEdit): ?>
                <a href="edit_holiday.php?id=<?= urlencode($holiday['override_id']) ?>" class="cal-btn cal-btn-primary">Edit</a>
                <form method="post" action="delete_holiday.php" class="cal-inline"
                      onsubmit="return confirm('Delete this holiday?');">
                    <input type="hidden" name="csrf" value="<?= cal_h(cal_csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= cal_h($holiday['override_id']) ?>">
                    <button type="submit" class="cal-btn cal-btn-danger">Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>