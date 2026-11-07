<?php
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];
session_regenerate_id(true);

notice('Logged out successfully.');

go('auth/login.php');