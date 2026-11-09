<?php
require_once __DIR__ . '/../includes/material_functions.php';

mat_require_role(['admin', 'staff', 'student']);
mat_handle_download($_GET['id'] ?? '');