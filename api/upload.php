<?php
// ═══════════════════════════════════════════════════
//  AAC — File Upload Endpoint
//  POST /api/upload.php            Upload a file
//  GET  /api/upload.php            List user's files
//  DELETE /api/upload.php?id=      Delete a file
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

setCorsHeaders();

$payload = requireAuth();
$userId  = (int) $payload['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];
$db      = getDB();

// Ensure uploads directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ─── UPLOAD FILE ──────────────────────────────────
if ($method === 'POST') {
    if (empty($_FILES['file'])) {
        error('No file uploaded.');
    }

    $file      = $_FILES['file'];
    $sessionId = (int) ($_POST['session_id'] ?? 0);

    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        error('File size exceeds 5 MB limit.');
    }

    // Validate file type
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_TYPES)) {
        error('Invalid file type. Allowed: PDF, TXT, DOC, DOCX.');
    }

    // Generate safe filename
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = sprintf('%d_%s.%s', $userId, uniqid(), strtolower($ext));
    $dest     = UPLOAD_DIR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        error('Failed to save file.', 500);
    }

    // Basic text extraction for TXT files (summary stub for PDF)
    $summary = null;
    if ($mimeType === 'text/plain') {
        $text    = file_get_contents($dest);
        $summary = mb_substr($text, 0, 500) . (mb_strlen($text) > 500 ? '...' : '');
    } elseif ($mimeType === 'application/pdf') {
        $summary = 'PDF uploaded. Content indexing requires a PDF parser library (e.g., pdfparser). File saved successfully.';
    }

    $stmt = $db->prepare(
        'INSERT INTO uploaded_files (user_id, session_id, filename, original_name, file_type, file_size, summary)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $sessionId ?: null, $safeName, $file['name'], $mimeType, $file['size'], $summary]);
    $id = (int) $db->lastInsertId();

    success([
        'file' => [
            'id'            => $id,
            'original_name' => $file['name'],
            'file_type'     => $mimeType,
            'file_size'     => $file['size'],
            'summary'       => $summary,
        ]
    ], 'File uploaded successfully.');
}

// ─── LIST FILES ───────────────────────────────────
elseif ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, original_name, file_type, file_size, summary, created_at
         FROM uploaded_files WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    $files = $stmt->fetchAll();

    success(['files' => $files, 'total' => count($files)]);
}

// ─── DELETE FILE ──────────────────────────────────
elseif ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) error('File ID required.');

    $stmt = $db->prepare('SELECT filename FROM uploaded_files WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $file = $stmt->fetch();

    if (!$file) error('File not found.', 404);

    // Remove physical file
    $path = UPLOAD_DIR . $file['filename'];
    if (file_exists($path)) unlink($path);

    $db->prepare('DELETE FROM uploaded_files WHERE id = ?')->execute([$id]);

    success([], 'File deleted.');
}

else {
    error('Method not allowed.', 405);
}
