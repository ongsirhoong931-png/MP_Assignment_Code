<?php
require_once __DIR__ . '/../includes/calendar_functions.php';
cal_require_login();

$filters = [
    'year' => $_GET['year'] ?? '',
    'type' => $_GET['type'] ?? '',
    'q'    => trim($_GET['q'] ?? ''),
];

$holidays = cal_list($filters);
$years    = cal_years();
$flash    = cal_flash_get();
$today    = date('Y-m-d');
$canEdit  = cal_can_manage();

$page_title = 'Holiday List';
include __DIR__ . '/../includes/header.php';   // your shared header + sidebar
?>
<link rel="stylesheet" href="../assets/css/calendar.css">

<section class="cal-page">
    <div class="cal-head">
        <div>
            <p class="cal-eyebrow">Academic calendar</p>
            <h1>Holiday List</h1>
        </div>
        <?php if ($canEdit): ?>
            <a href="add_holiday.php" class="cal-btn cal-btn-primary">+ Add Holiday</a>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="cal-alert cal-alert-<?= cal_h($flash['type']) ?>"><?= cal_h($flash['message']) ?></div>
    <?php endif; ?>

    <form method="get" class="cal-filters">
        <select name="year">
            <option value="">All years</option>
            <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y ?>" <?= (string)$filters['year'] === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
            <?php endforeach; ?>
        </select>

        <select name="type">
            <option value="">All types</option>
            <?php foreach (CAL_DAY_TYPES as $t): ?>
                <option value="<?= cal_h($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= cal_h($t) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="q" placeholder="Search description..." value="<?= cal_h($filters['q']) ?>">

        <button type="submit" class="cal-btn cal-btn-primary">Filter</button>
        <a href="index.php" class="cal-btn">Reset</a>
    </form>

    <div class="cal-table-wrap">
        <table class="cal-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Tuition day</th>
                    <th class="cal-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$holidays): ?>
                <tr>
                    <td colspan="6" class="cal-empty">
                        No holidays found.
                        <?php if ($canEdit): ?>Click <strong>Add Holiday</strong> to create one.<?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($holidays as $h): ?>
                <tr class="<?= $h['date'] < $today ? 'cal-past' : '' ?>">
                    <td><?= cal_h(date('d M Y', strtotime($h['date']))) ?></td>
                    <td><?= cal_h(date('l', strtotime($h['date']))) ?></td>
                    <td><span class="cal-badge"><?= cal_h($h['day_type']) ?></span></td>
                    <td><?= cal_h($h['description']) ?></td>
                    <td>
                        <span class="cal-badge <?= $h['is_tuition_day'] ? 'cal-badge-yes' : 'cal-badge-no' ?>">
                            <?= $h['is_tuition_day'] ? 'Yes' : 'No' ?>
                        </span>
                    </td>
                    <td class="cal-col-actions">
                        <a class="cal-btn cal-btn-sm" href="view_holiday.php?id=<?= urlencode($h['override_id']) ?>">View</a>
                        <?php if ($canEdit): ?>
                            <a class="cal-btn cal-btn-sm" href="edit_holiday.php?id=<?= urlencode($h['override_id']) ?>">Edit</a>
                            <form method="post" action="delete_holiday.php" class="cal-inline"
                                  onsubmit="return confirm('Delete this holiday?');">
                                <input type="hidden" name="csrf" value="<?= cal_h(cal_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= cal_h($h['override_id']) ?>">
                                <button type="submit" class="cal-btn cal-btn-sm cal-btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>