<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff']);

$pageTitle   = 'Create Material';
$extraCss    = ['assets/css/material.css'];
$submitLabel = 'Create Material';
$m           = mat_default();
$errors      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$m, $errors] = mat_read_post();
    if (!$errors) {
        $id = mat_create($m);
        mat_back(mat_upload_summary($id, 'Material created.'), 'materials/view_material.php?id=' . $id);
    }
}

[$subjects, $classes] = mat_form_options();

require __DIR__ . '/../includes/header.php';
?>
<?php mat_css(); ?>
<h1>Create Study Material</h1>
<?php require __DIR__ . '/material_template.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>