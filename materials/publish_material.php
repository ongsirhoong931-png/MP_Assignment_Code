<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff']);
require_post();

$m = mat_load_for_manage($_POST['id'] ?? '');
mat_set_published($m['material_id'], 1);
mat_back('Material published.');