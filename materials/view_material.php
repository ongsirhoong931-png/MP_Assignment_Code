<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff', 'student']);

$id          = $_GET['id'] ?? '';
$m           = mat_load_for_view($id);
[$info, $attachments] = mat_details($id);
$canManage   = mat_can_manage($m);
$pageTitle   = $m['title'];
$extraCss    = ['assets/css/material.css'];

require __DIR__ . '/../includes/header.php';
?>
<?php mat_css(); ?>
<?php mat_confirm_assets(); ?>
<div class="material-container">

    <p><a href="<?= h(url('materials/index.php')) ?>">← Back to materials</a></p>

    <h1><?= h($m['title']) ?></h1>
    <p>
        <span class="material-badge <?= $m['is_published'] ? 'badge-published' : 'badge-draft' ?>">
            <?= $m['is_published'] ? 'Published' : 'Draft' ?>
        </span>
        <span class="material-badge"><?= h($m['content_type']) ?></span>
    </p>

    <p>
        <strong>Subject:</strong> <?= h(($info['subject_code'] ?? '') . ' ' . ($info['subject_name'] ?? '')) ?><br>
        <strong>Class:</strong> <?= h(($info['grade_level'] ?? '') . ' ' . ($info['section'] ?? '')) ?><br>
        <strong>By:</strong> <?= h($info['staff_name'] ?? 'Admin') ?><br>
        <strong>Release date:</strong> <?= h($m['release_date'] ?? '-') ?>
    </p>

    <?php if (!empty($m['description'])): ?>
        <p><em><?= h($m['description']) ?></em></p>
    <?php endif; ?>

    <?php if (!empty($m['rich_text_content'])): ?>
        <div class="material-content"><?= mat_render_content($m['rich_text_content']) ?></div>
    <?php endif; ?>

    <?php mat_katex_assets(); ?>
    <p>
        <a class="button button-outline" target="_blank"
           href="<?= h(url('materials/export_pdf.php?id=' . urlencode($id))) ?>">Print / Save as PDF</a>
    </p>

    <h2 id="attachments">Attachments</h2>
    <?php if (!$attachments): ?>
        <p>No attachments yet.</p>
    <?php else: ?>
        <table class="material-table">
            <tr><th>File</th><th>Type</th><th>Uploaded</th><th></th></tr>
            <?php foreach ($attachments as $a): ?>
                <tr>
                    <td><?= h($a['file_name']) ?></td>
                    <td><?= h($a['file_type']) ?></td>
                    <td><?= h($a['uploaded_at']) ?></td>
                    <td>
                        <?php if (!empty($a['file_path'])): ?>
                            <a href="<?= h(url('materials/download_attachment.php?id=' . urlencode($a['attachment_id']))) ?>">Download</a>
                        <?php else: ?>
                            <span>(no file)</span>
                        <?php endif; ?>
                        <?php if ($canManage): ?>
                            <form method="post" style="display:inline" action="<?= h(url('materials/delete_attachment.php')) ?>"
                                  data-confirm="Remove this file?">
                                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                                <input type="hidden" name="id" value="<?= h($a['attachment_id']) ?>">
                                <button type="submit" class="link-button">Remove</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <h2>Upload attachment</h2>
        <form method="post" action="<?= h(url('materials/upload_attachment.php')) ?>" enctype="multipart/form-data" class="material-form">
            <input type="hidden" name="material_id" value="<?= h($id) ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="file" name="attachment" required>
            <button class="button" type="submit">Upload</button>
        </form>

        <div class="material-actions">
            <a class="button" href="<?= h(url('materials/edit_material.php?id=' . urlencode($id))) ?>">Edit</a>

            <form method="post" action="<?= h(url($m['is_published'] ? 'materials/ubpublish_material.php' : 'materials/publish_material.php')) ?>" style="display:inline">
                <input type="hidden" name="id" value="<?= h($id) ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <button class="button button-outline" type="submit"><?= $m['is_published'] ? 'Unpublish' : 'Publish' ?></button>
            </form>

            <form method="post" action="<?= h(url('materials/delete_material.php')) ?>" style="display:inline"
                  data-confirm="Delete this material and its attachments?">
                <input type="hidden" name="id" value="<?= h($id) ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <button class="button button-outline" type="submit">Delete</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>