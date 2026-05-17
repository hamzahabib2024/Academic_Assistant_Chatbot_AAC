<?php
// ═══════════════════════════════════════════════════
//  AAC — Admin Endpoint
//  GET  /api/admin.php?action=stats         Dashboard stats
//  GET  /api/admin.php?action=users         List users
//  POST /api/admin.php?action=add_user      Add user
//  PUT  /api/admin.php?action=toggle_user&id=  Activate/Deactivate
//  DELETE /api/admin.php?action=delete_user&id= Delete user
//  GET  /api/admin.php?action=logs          Chat logs
//  GET  /api/admin.php?action=activity      Recent activity
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

setCorsHeaders();

$payload = requireAdmin();
$method  = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';
$db      = getDB();

// ─── DASHBOARD STATS ──────────────────────────────
if ($action === 'stats' && $method === 'GET') {
    $totalUsers    = $db->query('SELECT COUNT(*) FROM users WHERE role = "student"')->fetchColumn();
    $totalMessages = $db->query('SELECT COUNT(*) FROM chat_messages WHERE role = "user"')->fetchColumn();
    $totalKB       = $db->query('SELECT COUNT(*) FROM knowledge_base')->fetchColumn();
    $totalFiles    = $db->query('SELECT COUNT(*) FROM uploaded_files')->fetchColumn();

    success([
        'stats' => [
            'total_users'    => (int) $totalUsers,
            'total_messages' => (int) $totalMessages,
            'total_kb'       => (int) $totalKB,
            'total_files'    => (int) $totalFiles,
        ]
    ]);
}

// ─── LIST USERS ───────────────────────────────────
elseif ($action === 'users' && $method === 'GET') {
    $search = $_GET['search'] ?? '';
    $sql    = 'SELECT id, name, email, role, status, created_at, last_login FROM users WHERE 1';
    $params = [];

    if ($search) {
        $sql    .= ' AND (name LIKE ? OR email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    success(['users' => $users, 'total' => count($users)]);
}

// ─── ADD USER ─────────────────────────────────────
elseif ($action === 'add_user' && $method === 'POST') {
    $body     = getBody();
    $name     = trim($body['name']     ?? '');
    $email    = trim($body['email']    ?? '');
    $password = trim($body['password'] ?? 'student123');
    $role     = in_array($body['role'] ?? '', ['student', 'admin']) ? $body['role'] : 'student';

    if (!$name || !$email) error('Name and email are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email address.');

    // Check duplicate
    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) error('Email already registered.');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, $role]);
    $id = (int) $db->lastInsertId();

    success(['id' => $id], 'User added successfully. Default password: ' . $password);
}

// ─── TOGGLE USER STATUS ───────────────────────────
elseif ($action === 'toggle_user' && $method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) error('User ID required.');
    if ($id === $payload['user_id']) error('You cannot deactivate yourself.');

    $stmt = $db->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) error('User not found.', 404);

    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
    $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);

    success(['new_status' => $newStatus], "User {$newStatus}.");
}

// ─── DELETE USER ──────────────────────────────────
elseif ($action === 'delete_user' && $method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) error('User ID required.');
    if ($id === $payload['user_id']) error('You cannot delete yourself.');

    $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND role != "admin"');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) error('User not found or cannot delete admin.', 404);

    success([], 'User deleted.');
}

// ─── CHAT LOGS ────────────────────────────────────
elseif ($action === 'logs' && $method === 'GET') {
    $search    = $_GET['search']     ?? '';
    $dateRange = $_GET['date_range'] ?? 'all';
    $limit     = (int) ($_GET['limit'] ?? 50);

    $sql    = 'SELECT cm.id, cm.role, cm.content, cm.created_at,
                      u.name AS student_name, u.email AS student_email,
                      cs.title AS session_title
               FROM chat_messages cm
               JOIN users u ON u.id = cm.user_id
               JOIN chat_sessions cs ON cs.id = cm.session_id
               WHERE 1';
    $params = [];

    if ($search) {
        $sql    .= ' AND (cm.content LIKE ? OR u.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($dateRange === 'today') {
        $sql .= ' AND DATE(cm.created_at) = CURDATE()';
    } elseif ($dateRange === 'week') {
        $sql .= ' AND cm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    }

    $sql .= ' ORDER BY cm.created_at DESC LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    success(['logs' => $logs, 'total' => count($logs)]);
}

// ─── RECENT ACTIVITY ──────────────────────────────
elseif ($action === 'activity' && $method === 'GET') {
    // Last 10 user questions
    $stmt = $db->query(
        'SELECT cm.content, cm.created_at, u.name AS student_name
         FROM chat_messages cm
         JOIN users u ON u.id = cm.user_id
         WHERE cm.role = "user"
         ORDER BY cm.created_at DESC
         LIMIT 10'
    );
    $recentChats = $stmt->fetchAll();

    // Last 5 registrations
    $stmt = $db->query(
        'SELECT name, email, created_at FROM users WHERE role = "student" ORDER BY created_at DESC LIMIT 5'
    );
    $recentUsers = $stmt->fetchAll();

    success(['recent_chats' => $recentChats, 'recent_users' => $recentUsers]);
}

else {
    error('Invalid action or method.', 404);
}
