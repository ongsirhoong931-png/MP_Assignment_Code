<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff', 'student']);

$pageTitle = 'Study Materials';
$extraCss  = ['assets/css/material.css'];
$canCreate = in_array(mat_role(), ['admin', 'staff'], true);

[$subjects, $error] = mat_subjects_for_user();

$subjectId = $_GET['subject'] ?? '';
$current   = null;
foreach ($subjects as $s) {
    if ($s['subject_id'] === $subjectId) {
        $current = $s;
    }
}
$materials = $current ? mat_list($subjectId) : [];

require __DIR__ . '/../includes/header.php';
?>
<?php mat_css(); ?>
<?php mat_confirm_assets(); ?>
<div class="material-container">

    <div class="material-head">
        <h1>Study Materials</h1>
        <?php if ($canCreate): ?>
            <a class="button" href="<?= h(url('materials/create_material.php')) ?>">+ Add Material</a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="material-alert material-alert-error"><?= h($error) ?></div>
    <?php else: ?>

        <div class="subject-grid">
            <?php foreach ($subjects as $s): ?>
                <a class="subject-card" style="text-decoration:none;color:inherit"
                   href="<?= h(url('materials/index.php?subject=' . urlencode($s['subject_id']))) ?>">
                    <h3><?= h($s['subject_name']) ?></h3>
                    <p><?= h($s['subject_code']) ?></p>
                    <span class="material-badge"><?= (int) $s['material_count'] ?> material(s)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($current): ?>
            <h2><?= h($current['subject_code'] . ' - ' . $current['subject_name']) ?></h2>

            <?php if (!$materials): ?>
                <p>No materials yet.</p>
            <?php else: ?>
                <table class="material-table">
                    <tr>
                        <th>Title</th>
                        <th>Class</th>
                        <th>Type</th>
                        <th>Release</th>
                        <?php if ($canCreate): ?><th>Status</th><?php endif; ?>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($materials as $m): ?>
                        <?php $manage = mat_can_manage($m); ?>
                        <tr>
                            <td><?= h($m['title']) ?></td>
                            <td><?= h(($m['grade_level'] ?? '') . ' ' . ($m['section'] ?? '')) ?></td>
                            <td><?= h($m['content_type']) ?></td>
                            <td><?= h($m['release_date'] ?? '-') ?></td>
                            <?php if ($canCreate): ?>
                                <td>
                                    <span class="material-badge <?= $m['is_published'] ? 'badge-published' : 'badge-draft' ?>">
                                        <?= $m['is_published'] ? 'Published' : 'Draft' ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td class="material-row-actions">
                                <a href="<?= h(url('materials/view_material.php?id=' . urlencode($m['material_id']))) ?>">View</a>

                                <?php if ($manage): ?>
                                    <a href="<?= h(url('materials/edit_material.php?id=' . urlencode($m['material_id']))) ?>">Edit</a>
                                    <a href="<?= h(url('materials/view_material.php?id=' . urlencode($m['material_id']) . '#attachments')) ?>">Attach</a>

                                    <form method="post" style="display:inline"
                                          action="<?= h(url($m['is_published'] ? 'materials/ubpublish_material.php' : 'materials/publish_material.php')) ?>">
                                        <input type="hidden" name="id" value="<?= h($m['material_id']) ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                        <button type="submit" class="link-button"><?= $m['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                                    </form>

                                    <form method="post" style="display:inline"
                                          action="<?= h(url('materials/delete_material.php')) ?>"
                                          data-confirm="Delete this material and its attachments?">
                                        <input type="hidden" name="id" value="<?= h($m['material_id']) ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                        <button type="submit" class="link-button">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php else: ?>
            <p>Click a subject to see its materials.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>