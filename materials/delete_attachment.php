<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff']);
require_post();
mat_handle_delete_attachment($_POST['id'] ?? '');