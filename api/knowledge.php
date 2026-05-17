<?php
// ═══════════════════════════════════════════════════
//  AAC — Knowledge Base Endpoint  (Admin only)
//  GET    /api/knowledge.php             List all entries
//  POST   /api/knowledge.php?action=add  Add entry
//  PUT    /api/knowledge.php?action=edit&id= Edit entry
//  DELETE /api/knowledge.php?action=delete&id= Delete entry
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// GET (list) is accessible to all authenticated users for chat context
// Write operations require admin
if ($method === 'GET') {
    $payload = requireAuth();
} else {
    $payload = requireAdmin();
}

$db = getDB();

// ─── LIST ─────────────────────────────────────────
if ($method === 'GET') {
    $category = $_GET['category'] ?? '';
    $search   = $_GET['search']   ?? '';

    $sql    = 'SELECT kb.*, u.name AS created_by_name FROM knowledge_base kb LEFT JOIN users u ON u.id = kb.created_by WHERE 1';
    $params = [];

    if ($category) {
        $sql    .= ' AND kb.category = ?';
        $params[] = $category;
    }
    if ($search) {
        $sql    .= ' AND (kb.title LIKE ? OR kb.content LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= ' ORDER BY kb.category, kb.title';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    success(['entries' => $entries, 'total' => count($entries)]);
}

// ─── ADD ──────────────────────────────────────────
elseif ($method === 'POST' && $action === 'add') {
    $body     = getBody();
    $title    = trim($body['title']    ?? '');
    $category = trim($body['category'] ?? '');
    $content  = trim($body['content']  ?? '');

    if (!$title || !$category || !$content) error('All fields are required.');

    $stmt = $db->prepare('INSERT INTO knowledge_base (title, category, content, created_by) VALUES (?, ?, ?, ?)');
    $stmt->execute([$title, $category, $content, $payload['user_id']]);
    $id = (int) $db->lastInsertId();

    success(['id' => $id], 'Knowledge entry added successfully.');
}

// ─── EDIT ─────────────────────────────────────────
elseif ($method === 'PUT' && $action === 'edit') {
    $id      = (int) ($_GET['id'] ?? 0);
    if (!$id) error('Entry ID required.');

    $body     = getBody();
    $title    = trim($body['title']    ?? '');
    $category = trim($body['category'] ?? '');
    $content  = trim($body['content']  ?? '');

    if (!$title || !$category || !$content) error('All fields are required.');

    $stmt = $db->prepare('UPDATE knowledge_base SET title = ?, category = ?, content = ? WHERE id = ?');
    $stmt->execute([$title, $category, $content, $id]);

    if ($stmt->rowCount() === 0) error('Entry not found.', 404);

    success([], 'Entry updated successfully.');
}

// ─── DELETE ───────────────────────────────────────
elseif ($method === 'DELETE' && $action === 'delete') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) error('Entry ID required.');

    $stmt = $db->prepare('DELETE FROM knowledge_base WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) error('Entry not found.', 404);

    success([], 'Entry deleted.');
}

else {
    error('Invalid action or method.', 404);
}
