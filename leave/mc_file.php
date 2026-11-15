<?php
/**
 * Sends one medical certificate to the browser.
 * Only people who may see the leave application can open its file.
 */
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff', 'student');

$leave = lv_find($pdo, trim((string) ($_GET['id'] ?? '')));
$path = $leave ? lv_mc_path($leave['attachment_path']) : null;

if ($path === null) {
    http_response_code(404);
    exit('File not found');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));


// Throw away any stray output (spaces, warnings) so the file bytes stay exact.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . (LV_MC_TYPES[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="certificate_' . $leave['leave_id'] . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;