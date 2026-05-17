<?php
// ═══════════════════════════════════════════════════
//  AAC — Auth Helper
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';

// ─── CORS Headers (needed for XAMPP localhost) ────
function setCorsHeaders(): void {
    // Restrict CORS to local development origins only (localhost / 127.0.0.1)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowOrigin = '';
    if ($origin) {
        $parts = parse_url($origin);
        $host = $parts['host'] ?? '';
        if (in_array($host, ['localhost', '127.0.0.1'])) {
            $allowOrigin = $origin; // allow exact origin (includes port if present)
        }
    }

    if ($allowOrigin) {
        header('Access-Control-Allow-Origin: ' . $allowOrigin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=UTF-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ─── JSON Response helpers ────────────────────────
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function success(array $data = [], string $message = 'OK'): void {
    // Merge associative arrays safely (PHP does not allow unpacking arrays with string keys)
    $payload = array_merge(['success' => true, 'message' => $message], $data);
    respond($payload);
}

function error(string $message, int $code = 400): void {
    respond(['success' => false, 'message' => $message], $code);
}

// ─── Simple Token (base64 encoded, stored in DB-like session table) ─
//  We use PHP sessions + a token approach suitable for XAMPP localhost
function generateToken(int $userId, string $role): string {
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'role'    => $role,
        'exp'     => time() + SESSION_LIFETIME,
        'sig'     => hash_hmac('sha256', $userId . $role, JWT_SECRET),
    ]));
    return $payload;
}

function verifyToken(string $token): ?array {
    $decoded = base64_decode($token, true);
    if (!$decoded) return null;

    $payload = json_decode($decoded, true);
    if (!$payload) return null;

    // Expiry check
    if (($payload['exp'] ?? 0) < time()) return null;

    // Signature check
    $expected = hash_hmac('sha256', $payload['user_id'] . $payload['role'], JWT_SECRET);
    if (!hash_equals($expected, $payload['sig'] ?? '')) return null;

    return $payload;
}

// ─── Require auth — returns payload or exits ──────
function requireAuth(): array {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!str_starts_with($auth, 'Bearer ')) {
        error('Unauthorized — no token provided', 401);
    }

    $token   = substr($auth, 7);
    $payload = verifyToken($token);

    if (!$payload) {
        error('Unauthorized — invalid or expired token', 401);
    }

    return $payload;
}

// ─── Require admin role ───────────────────────────
function requireAdmin(): array {
    $payload = requireAuth();
    if ($payload['role'] !== 'admin') {
        error('Forbidden — admin access required', 403);
    }
    return $payload;
}

// ─── Get JSON body ────────────────────────────────
function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
