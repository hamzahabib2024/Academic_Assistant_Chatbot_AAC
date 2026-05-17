<?php
// ═══════════════════════════════════════════════════
//  AAC — Auth Endpoint
//  POST /api/auth.php?action=register
//  POST /api/auth.php?action=login
//  POST /api/auth.php?action=logout
//  GET  /api/auth.php?action=me
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

setCorsHeaders();

$action = $_GET['action'] ?? '';

// ─── REGISTER ─────────────────────────────────────
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();

    $name     = trim($body['name']     ?? '');
    $email    = trim($body['email']    ?? '');
    $password = trim($body['password'] ?? '');
    $confirm  = trim($body['confirm']  ?? '');

    if (!$name || !$email || !$password || !$confirm) {
        error('All fields are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error('Please enter a valid email address.');
    }
    if (strlen($password) < 6) {
        error('Password must be at least 6 characters.');
    }
    if ($password !== $confirm) {
        error('Passwords do not match.');
    }

    $db = getDB();

    // Check duplicate email
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        error('An account with this email already exists.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "student")');
    $stmt->execute([$name, $email, $hash]);
    $userId = (int) $db->lastInsertId();

    $token = generateToken($userId, 'student');

    success([
        'token' => $token,
        'user'  => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'student'],
    ], 'Registration successful. Welcome to AAC!');
}

// ─── LOGIN ────────────────────────────────────────
elseif ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();

    $email    = trim($body['email']    ?? '');
    $password = trim($body['password'] ?? '');

    if (!$email || !$password) {
        error('Email and password are required.');
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        error('Invalid email or password.');
    }
    if ($user['status'] === 'inactive') {
        error('Your account has been deactivated. Please contact the administrator.');
    }

    // Update last_login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    $token = generateToken((int) $user['id'], $user['role']);

    success([
        'token' => $token,
        'user'  => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
    ], 'Login successful.');
}

// ─── GET CURRENT USER ─────────────────────────────
elseif ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $payload = requireAuth();

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE id = ?');
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();

    if (!$user) error('User not found.', 404);

    success(['user' => $user]);
}

else {
    error('Invalid action or method.', 404);
}
