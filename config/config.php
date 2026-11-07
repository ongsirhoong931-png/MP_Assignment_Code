<?php
const APP_NAME = 'Tuition Attendance Management';
const BASE_URL = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kuala_Lumpur');