<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff']);

$id          = $_GET['id'] ?? '';
$m           = mat_load_for_manage($id);
$pageTitle   = 'Edit Material';
$extraCss    = ['assets/css/material.css'];
$submitLabel = 'Save Changes';
$errors      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$m, $errors] = mat_read_post();
    if (!$errors) {
        mat_update($id, $m);
        mat_back(mat_upload_summary($id, 'Material updated.'), 'materials/view_material.php?id=' . $id);
    }
}

[$subjects, $classes] = mat_form_options();

require __DIR__ . '/../includes/header.php';
?>
<?php mat_css(); ?>
<h1>Edit Study Material</h1>
<?php require __DIR__ . '/material_template.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>