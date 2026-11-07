<?php
require_once __DIR__ . '/../config/config.php';

function h($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function go(string $path): never {
    header('Location: ' . url($path));
    exit;
}

function csrf(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function check_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token');
    }
}

function notice(string $message): void {
    $_SESSION['notice'] = $message;
}

function show_notice(): void {
    if (isset($_SESSION['notice'])) {
        echo '<p class="notice">' . h($_SESSION['notice']) . '</p>';
        unset($_SESSION['notice']);
    }
}

function logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function me(): array {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}

function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    check_csrf();
}

function next_id(
    PDO $pdo,
    string $table,
    string $column,
    string $prefix
): string {
    return $prefix . strtoupper(
        substr(bin2hex(random_bytes(5)), 0, 9)
    );
}