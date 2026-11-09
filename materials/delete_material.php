<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff']);
require_post();

$m = mat_load_for_manage($_POST['id'] ?? '');
mat_delete($m['material_id']);
mat_back('Material deleted.');