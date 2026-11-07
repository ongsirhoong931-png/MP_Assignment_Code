<?php
require_once __DIR__ . '/functions.php';

if (!logged_in()) {
    notice('Please log in first.');
    go('auth/login.php');
}