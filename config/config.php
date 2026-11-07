<?php
const APP_NAME = 'Tuition Attendance Management';
const BASE_URL = '/MP_Assignment_Code';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}

date_default_timezone_set('Asia/Kuala_Lumpur');