<?php
const APP_NAME = 'EDUVEX Academy';
const BASE_URL = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kuala_Lumpur');



defined('ATTENDANCE_SECRET') ||
    define(
        'ATTENDANCE_SECRET',
        'REPLACE_WITH_A_LONG_RANDOM_SECRET'
    );
