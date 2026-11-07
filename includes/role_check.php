<?php
require_once __DIR__ . '/auth_check.php';

function allow_roles(string ...$roles): void {
    if (!in_array(me()['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied');
    }
}