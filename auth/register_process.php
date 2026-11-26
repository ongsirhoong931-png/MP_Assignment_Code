<?php
session_start();
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? 'student';
    $reference_id = trim($_POST['reference_id'] ?? null);

    // Block admin registration via public form
    if ($role === 'admin') {
        $_SESSION['error'] = "Admin accounts cannot be registered publicly.";
        header("Location: register.php");
        exit();
    }

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All required fields must be filled out.";
        header("Location: register.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: register.php");
        exit();
    }

    // Password Policy Validation (Min 12 chars, uppercase, number, special character)
    if (strlen($password) < 12 || 
        !preg_match('/[A-Z]/', $password) || 
        !preg_match('/[0-9]/', $password) || 
        !preg_match('/[\W_]/', $password)) {
        $_SESSION['error'] = "Password must be at least 12 characters long and include at least one uppercase letter, one number, and one special character.";
        header("Location: register.php");
        exit();
    }

    try {
        // Check if username or email already exists
        $checkStmt = $pdo->prepare("SELECT user_id FROM user_accounts WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->rowCount() > 0) {
            $_SESSION['error'] = "Username or Email is already taken.";
            header("Location: register.php");
            exit();
        }

        // Hash the password securely
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $admin_id   = null;
        $staff_id   = null;
        $student_id = null;

        if (!empty($reference_id)) {
            if ($role === 'staff') {
                $staff_id = $reference_id;
            } elseif ($role === 'student') {
                $student_id = $reference_id;
            }
        }

        // Set `is_active` to 0 so the account requires admin approval before login
        $sql = "INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $email, $password_hash, $role, $admin_id, $staff_id, $student_id]);

        $_SESSION['success'] = "Registration request submitted successfully! Please wait for an administrator to approve your account.";
        header("Location: register.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: register.php");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}