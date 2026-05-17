<?php
// ═══════════════════════════════════════════════════
//  AAC — Database Configuration
//  Uses environment variables when available to avoid storing secrets in the repo.
//  If a .env file exists in the project root, it is loaded automatically.
//  Example (Windows PowerShell):
//    $env:DB_HOST = 'localhost'; $env:DB_USER = 'root'; $env:DB_PASS = ''; $env:DB_NAME = 'aac_db';
// ═══════════════════════════════════════════════════

function loadDotEnv(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (($value !== '') && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv("$name=$value");
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

loadDotEnv(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'aac_db');
define('DB_CHARSET', getenv('DB_CHARSET') !== false ? getenv('DB_CHARSET') : 'utf8mb4');

// ─── App Settings ────────────────────────────────
define('APP_NAME', 'AAC - AI Academic Assistant');
// JWT secret should be set via environment variable in production. Defaults remain for local development.
define('JWT_SECRET', getenv('JWT_SECRET') !== false ? getenv('JWT_SECRET') : 'aac_super_secret_key_change_this_in_production');
define('SESSION_LIFETIME', getenv('SESSION_LIFETIME') !== false ? (int)getenv('SESSION_LIFETIME') : 86400); // 24 hours in seconds
define('DEBUG', getenv('DEBUG') !== false && strtolower(trim(getenv('DEBUG'))) === 'true');

// If running in production and using the default secret, log a warning to the PHP error log.
if (PHP_SAPI !== 'cli' && JWT_SECRET === 'aac_super_secret_key_change_this_in_production') {
    error_log('[AAC] Warning: using default JWT_SECRET — set JWT_SECRET env var for better security.');
}

// ─── Gemini / Generative API settings ─────────────────
// Set via environment variables in production. Example VAR names: GEMINI_API_URL, GEMINI_API_KEY
define('GEMINI_API_URL', getenv('GEMINI_API_URL') !== false ? getenv('GEMINI_API_URL') : '');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') !== false ? getenv('GEMINI_API_KEY') : '');

// ─── Upload Settings ─────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_TYPES', ['application/pdf', 'text/plain', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// ─── PDO Connection ──────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
