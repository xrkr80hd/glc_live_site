<?php
declare(strict_types=1);

// Start output buffering to prevent "headers already sent" errors
ob_start();

// -----------------------------------------------------------------------------
// Liberty Church PHP Bootstrap
// This configuration uses the live production credentials so every local test
// mirrors the deployed environment exactly. Do not replace with placeholders.
// -----------------------------------------------------------------------------

// Load real environment variables if present (.env mirrors production values).
// Process-level values, such as Docker Compose environment settings, win.
$envPath = dirname(__DIR__) . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$val}");
        }
    }
}

function config_value(string $key, string $fallback): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $fallback : $value;
}

function config_bool(string $key, bool $fallback): bool
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $fallback;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

// Database credentials (MySQL)
define('DB_HOST', config_value('DB_HOST', 'localhost'));
define('DB_PORT', config_value('DB_PORT', ''));
define('DB_NAME', config_value('DB_NAME', 'golibert2_liberty_church'));
define('DB_USER', config_value('DB_USER', 'golibert2_liberty_church_user'));
define('DB_PASS', config_value('DB_PASS', config_value('DB_PASSWORD', '@LibertyChurch1065!')));
define('DB_CHARSET', config_value('DB_CHARSET', 'utf8mb4'));

// Application constants
const APP_NAME = 'Liberty Church Admin';
const SESSION_NAME = 'liberty_admin_session';
const UPLOAD_DIR = __DIR__ . '/../uploads';
const MAX_UPLOAD_BYTES = 75 * 1024 * 1024; // 75 MB cap for youth media
define('ADMIN_LOGIN_DISABLED', config_bool('ADMIN_LOGIN_DISABLED', false)); // Local OG_UPDATING conversion mode only.

// Ensure sessions are available
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// Lazily create (and memoize) the PDO handle
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $portPart = DB_PORT !== '' ? ';port=' . DB_PORT : '';
        $dsn = sprintf('mysql:host=%s%s;dbname=%s;charset=%s', DB_HOST, $portPart, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            if (defined('ADMIN_LOGIN_DISABLED') && ADMIN_LOGIN_DISABLED) {
                throw new RuntimeException('Database connection failed. Please verify credentials in php/config.php.', 0, $e);
            }
            http_response_code(500);
            exit('Database connection failed. Please verify credentials in php/config.php.');
        }
    }

    return $pdo;
}

// -----------------------------------------------------------------------------
// Helper utilities shared across admin + API endpoints
// -----------------------------------------------------------------------------

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_upload_directory(): void
{
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Unable to create uploads directory: ' . UPLOAD_DIR);
    }
}

function format_datetime(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    $dt = new DateTimeImmutable($value);
    return $dt->format('Y-m-d H:i:s');
}

function guard_auth(): void
{
    if (defined('ADMIN_LOGIN_DISABLED') && ADMIN_LOGIN_DISABLED) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] ??= [
            'id' => 0,
            'username' => 'local-admin',
            'role' => 'pastor',
        ];
        $_SESSION['admin_user_id'] = 0;
        return;
    }

    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /php/admin/login.php');
        exit;
    }
}

// CSRF utilities (tokens stored in session)
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): void
{
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('CSRF validation failed. Refresh the page and try again.');
    }
}

// Configure default timezone (matches production server)
date_default_timezone_set('America/Chicago');

?>
