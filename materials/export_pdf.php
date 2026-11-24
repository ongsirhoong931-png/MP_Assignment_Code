<?php
// materials/export_pdf.php
// Quick PDF / Print Layout Exporter.
// Applies the institution letterhead + logo + A4 layout to any material.
// The browser print window opens automatically; choose "Save as PDF".

require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff', 'student']);

$id = $_GET['id'] ?? '';
$m  = mat_load_for_view($id);            // also checks the user may see this material
[$info, $attachments] = mat_details($id);

$institution = 'EDUVEX Academy';
$tagline     = 'Study Material';

// ---- Logo: first file that exists, embedded as base64 so it always prints ----
$logoSrc = null;
foreach (['assets/images/logo.png', 'assets/img/logo.png', 'assets/logo.png',
          'assets/images/logo.jpg', 'assets/images/logo.jpeg', 'assets/images/logo.svg'] as $rel) {
    $path = __DIR__ . '/../' . $rel;
    if (is_file($path) && filesize($path) < 3 * 1024 * 1024) {
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml'][$ext] ?? 'image/png';
        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        break;
    }
}

$subject   = trim(($info['subject_code'] ?? '') . ' ' . ($info['subject_name'] ?? ''));
$class     = trim(($info['grade_level'] ?? '') . ' ' . ($info['section'] ?? ''));
$printedBy = me()['username'] ?? '';
$printedOn = date('d M Y, H:i');

// Attachments that can be shown inside the printout: images (embedded) and TXT (text).
// PDF / Word / Excel / ZIP cannot be merged by the browser, so they stay as a list.
function mat_print_preview(array $a): ?array
{
    $rel  = $a['file_path'] ?? '';
    $full = realpath(__DIR__ . '/../' . $rel);
    $root = realpath(__DIR__ . '/../uploads/materials');
    if (!$full || !$root || strpos($full, $root) !== 0 || !is_file($full) || filesize($full) > 4 * 1024 * 1024) {
        return null;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if (in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        return ['type' => 'image', 'data' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($full))];
    }
    if ($ext === 'txt') {
        return ['type' => 'text', 'data' => mb_substr((string) file_get_contents($full), 0, 20000)];
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($m['title']) ?> - <?= h($institution) ?></title>
    <style>
        /* ---------- A4 page layout ---------- */
        @page {
            size: A4;
            margin: 14mm 14mm 16mm 14mm;
            @bottom-center { content: "Page " counter(page) " of " counter(pages); font: 10px Arial, sans-serif; color: #666; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9e9ee; color: #111; font: 14px/1.6 Arial, Helvetica, sans-serif; }

        /* ---------- On-screen toolbar (never printed) ---------- */
        .toolbar { position: sticky; top: 0; z-index: 5; background: #2b2540; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 8px; background: #8b6cf0; color: #fff; font: inherit; cursor: pointer; }
        .toolbar button.secondary { background: #44395f; }
        .toolbar span { font-size: 12px; opacity: .85; }

        /* ---------- Sheet: thead/tfoot repeat on EVERY printed page ---------- */
        .sheet { width: 100%; max-width: 794px; margin: 16px auto; background: #fff; padding: 0 30px; border-collapse: collapse; }
        .sheet > thead { display: table-header-group; }
        .sheet > tfoot { display: table-footer-group; }
        .sheet td { padding: 0; vertical-align: top; }

        .letterhead { display: flex; align-items: center; gap: 16px; padding: 22px 0 10px; border-bottom: 3px solid #5b3fd1; margin-bottom: 18px; }
        .logo { height: 60px; width: auto; max-width: 160px; object-fit: contain; }
        .logo-fallback { width: 60px; height: 60px; border-radius: 12px; background: #5b3fd1; color: #fff; font: 700 28px/60px Arial, sans-serif; text-align: center; }
        .inst { font-size: 22px; font-weight: 700; color: #3b2a8c; line-height: 1.2; }
        .inst-sub { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: .12em; }
        .doc-ref { margin-left: auto; text-align: right; font-size: 11px; color: #555; }

        .footer { border-top: 1px solid #ccc; margin-top: 16px; padding: 6px 0 14px; font-size: 10.5px; color: #666; display: flex; justify-content: space-between; gap: 12px; }

        /* ---------- Material body ---------- */
        h1 { margin: 0 0 8px; font-size: 24px; }
        .meta { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
        .meta td { padding: 4px 8px; border: 1px solid #ccc; vertical-align: top; }
        .meta td:first-child { width: 28%; background: #f5f3ff; font-weight: 700; }
        .desc { font-style: italic; color: #444; margin: 0 0 16px; }

        .material-content { overflow-wrap: anywhere; }
        .material-content h2 { font-size: 19px; margin: 18px 0 6px; color: #3b2a8c; }
        .material-content h3 { font-size: 16px; margin: 14px 0 4px; }
        .material-content img { max-width: 100%; height: auto; }
        .material-content table { border-collapse: collapse; width: 100%; }
        .material-content th, .material-content td { border: 1px solid #999; padding: 4px 8px; }
        .material-content pre { background: #f4f4f4; padding: 10px; white-space: pre-wrap; }
        .material-content blockquote { margin: 10px 0; padding-left: 12px; border-left: 4px solid #ccc; color: #444; }
        .material-content iframe, .material-content video, .material-content audio { display: none; }

        h2.section { font-size: 17px; margin: 24px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .files { margin: 0; padding-left: 20px; }
        figure.att { margin: 16px 0; text-align: center; page-break-inside: avoid; }
        figure.att img { max-width: 100%; max-height: 220mm; height: auto; border: 1px solid #ddd; }
        figure.att figcaption { font-size: 11px; color: #666; margin-top: 4px; }
        .att-text { background: #f4f4f4; padding: 10px; white-space: pre-wrap; font-size: 12px; }

        /* ---------- Print ---------- */
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { margin: 0; max-width: none; padding: 0; }
            h1, h2, h3 { page-break-after: avoid; }
            img, table, pre, blockquote { page-break-inside: avoid; }
            tr { page-break-inside: avoid; }
            a { color: #111; text-decoration: none; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
    <?php mat_katex_assets(); ?>
</head>
<body>

<div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
    <button type="button" class="secondary" onclick="if (history.length > 1) { history.back(); } else { window.close(); }">Back</button>
    <span>In the print window: Destination &rarr; "Save as PDF". Untick "Headers and footers" for a clean page.</span>
</div>

<table class="sheet">
    <!-- Repeats at the top of every page -->
    <thead>
    <tr><td>
        <div class="letterhead">
            <?php if ($logoSrc): ?>
                <img class="logo" src="<?= h($logoSrc) ?>" alt="<?= h($institution) ?> logo">
            <?php else: ?>
                <div class="logo-fallback"><?= h(mb_substr($institution, 0, 1)) ?></div>
            <?php endif; ?>
            <div>
                <div class="inst"><?= h($institution) ?></div>
                <div class="inst-sub"><?= h($tagline) ?></div>
            </div>
            <div class="doc-ref">
                <?= h($subject !== '' ? $subject : '') ?><br>
                <?= h($class !== '' ? $class : '') ?>
            </div>
        </div>
    </td></tr>
    </thead>

    <!-- Repeats at the bottom of every page -->
    <tfoot>
    <tr><td>
        <div class="footer">
            <span><?= h($institution) ?> &middot; <?= h($m['title']) ?></span>
            <span>Printed <?= h($printedOn) ?><?php if ($printedBy !== ''): ?> by <?= h($printedBy) ?><?php endif; ?></span>
        </div>
    </td></tr>
    </tfoot>

    <tbody>
    <tr><td>
        <h1><?= h($m['title']) ?></h1>

        <table class="meta">
            <tr><td>Subject</td><td><?= h($subject !== '' ? $subject : '-') ?></td></tr>
            <tr><td>Class</td><td><?= h($class !== '' ? $class : '-') ?></td></tr>
            <tr><td>Material type</td><td><?= h($m['content_type']) ?></td></tr>
            <tr><td>Tutor</td><td><?= h($info['staff_name'] ?? 'Admin') ?></td></tr>
            <tr><td>Release date</td><td><?= h($m['release_date'] ?? '-') ?></td></tr>
        </table>

        <?php if (!empty($m['description'])): ?>
            <p class="desc"><?= h($m['description']) ?></p>
        <?php endif; ?>

        <?php if (!empty($m['rich_text_content'])): ?>
            <div class="material-content"><?= mat_render_content($m['rich_text_content']) ?></div>
        <?php else: ?>
            <p>(No written content. See the attachments below.)</p>
        <?php endif; ?>

        <?php if ($attachments): ?>
            <h2 class="section">Attachments</h2>
            <ul class="files">
                <?php foreach ($attachments as $a): ?>
                    <li><?= h($a['file_name']) ?> (<?= h($a['file_type']) ?>)</li>
                <?php endforeach; ?>
            </ul>

            <?php foreach ($attachments as $a): ?>
                <?php $p = mat_print_preview($a); ?>
                <?php if ($p && $p['type'] === 'image'): ?>
                    <figure class="att">
                        <img src="<?= h($p['data']) ?>" alt="<?= h($a['file_name']) ?>">
                        <figcaption><?= h($a['file_name']) ?></figcaption>
                    </figure>
                <?php elseif ($p && $p['type'] === 'text'): ?>
                    <h3><?= h($a['file_name']) ?></h3>
                    <pre class="att-text"><?= h($p['data']) ?></pre>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </td></tr>
    </tbody>
</table>

<script>
// Open the print window once fonts, images and KaTeX formulas are ready.
window.addEventListener('load', function () {
    var ready = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    ready.then(function () { setTimeout(function () { window.print(); }, 600); });
});
</script>
</body>
</html>