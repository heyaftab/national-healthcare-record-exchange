<?php
declare(strict_types=1);

// Hosted environments should provide these as environment variables. The
// defaults keep the existing XAMPP setup working locally.
function nhre_env(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

// Prefer TCP for the built-in PHP preview server; `localhost` may try a
// MySQL socket that belongs to a different PHP/XAMPP installation.
define('DB_HOST', nhre_env('DB_HOST', '127.0.0.1'));
define('DB_PORT', nhre_env('DB_PORT', '3306'));
define('DB_NAME', nhre_env('DB_NAME', 'nhre'));
define('DB_USER', nhre_env('DB_USER', 'root'));
define('DB_PASS', nhre_env('DB_PASS', ''));

const SESSION_NAME = 'nhre_session';
const SESSION_LIFETIME = 7200;

const COOKIE_REMEMBER = 'nhre_remember';
const COOKIE_REMEMBER_DAYS = 30;

const LOGIN_ATTEMPT_LIMIT = 5;
const LOGIN_ATTEMPT_WINDOW_MINUTES = 15;

function is_https(): bool
{
    // Hosted proxies may terminate TLS before forwarding requests to PHP.
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/** Store uploaded files outside a serverless instance when blob storage is configured. */
function nhre_store_uploaded_file(string $tmpPath, string $directory, string $storedName, string $mimeType): bool
{
    if (getenv('NHRE_FILE_STORAGE') === 'database') {
        // Uploads must originate from PHP's HTTP upload mechanism. Accepting any
        // readable local path here could expose arbitrary server files to storage.
        if (!is_uploaded_file($tmpPath)) {
            return false;
        }
        $contents = file_get_contents($tmpPath);
        if ($contents === false) {
            return false;
        }
        $statement = db()->prepare(
            'INSERT INTO nhre_file_storage (storage_key, mime_type, contents) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE mime_type = VALUES(mime_type), contents = VALUES(contents)'
        );
        $statement->execute([$directory . '/' . $storedName, $mimeType, $contents]);
        return true;
    }

    $targetDirectory = __DIR__ . '/../uploads/' . $directory;
    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        return false;
    }
    return move_uploaded_file($tmpPath, $targetDirectory . '/' . basename($storedName));
}

/** Read a stored upload, returning its bytes and MIME type when using database storage. */
function nhre_read_stored_file(string $directory, string $storedName): ?array
{
    if (getenv('NHRE_FILE_STORAGE') === 'database') {
        $statement = db()->prepare('SELECT mime_type, contents FROM nhre_file_storage WHERE storage_key = ? LIMIT 1');
        $statement->execute([$directory . '/' . basename($storedName)]);
        $file = $statement->fetch();
        return $file ?: null;
    }

    $path = __DIR__ . '/../uploads/' . $directory . '/' . basename($storedName);
    if (!is_file($path)) {
        return null;
    }
    $contents = file_get_contents($path);
    return $contents === false ? null : [
        'mime_type' => (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream',
        'contents' => $contents,
    ];
}

function nhre_delete_stored_file(string $directory, string $storedName): void
{
    if (getenv('NHRE_FILE_STORAGE') === 'database') {
        $statement = db()->prepare('DELETE FROM nhre_file_storage WHERE storage_key = ?');
        $statement->execute([$directory . '/' . basename($storedName)]);
        return;
    }
    $path = __DIR__ . '/../uploads/' . $directory . '/' . basename($storedName);
    if (is_file($path)) {
        unlink($path);
    }
}

/** Store sessions in MySQL so they survive serverless or container restarts. */
final class NhreDatabaseSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $statement = db()->prepare('SELECT session_data FROM nhre_sessions WHERE session_id = ? AND expires_at > NOW()');
        $statement->execute([$id]);
        $data = $statement->fetchColumn();
        return $data === false ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        $statement = db()->prepare(
            'INSERT INTO nhre_sessions (session_id, session_data, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), expires_at = VALUES(expires_at)'
        );
        return $statement->execute([$id, $data, SESSION_LIFETIME]);
    }

    public function destroy(string $id): bool
    {
        $statement = db()->prepare('DELETE FROM nhre_sessions WHERE session_id = ?');
        return $statement->execute([$id]);
    }

    public function gc(int $maxLifetime): int|false
    {
        $statement = db()->prepare('DELETE FROM nhre_sessions WHERE expires_at <= NOW()');
        return $statement->execute() ? $statement->rowCount() : false;
    }
}

/** PHP's default session files are per-instance; optionally share sessions through MySQL. */
function nhre_configure_sessions(): void
{
    if (getenv('NHRE_SESSION_STORAGE') !== 'database' || session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_set_save_handler(new NhreDatabaseSessionHandler(), true);
}

function nhre_ensure_deployment_storage_tables(): void
{
    if (getenv('NHRE_FILE_STORAGE') === 'database') {
        db()->exec('CREATE TABLE IF NOT EXISTS nhre_file_storage (
            storage_key VARCHAR(255) NOT NULL PRIMARY KEY,
            mime_type VARCHAR(100) NOT NULL,
            contents LONGBLOB NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}

function nhre_ensure_session_storage_table(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS nhre_sessions (
        session_id VARCHAR(128) NOT NULL PRIMARY KEY,
        session_data MEDIUMBLOB NOT NULL,
        expires_at DATETIME NOT NULL,
        KEY idx_nhre_sessions_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

if (getenv('NHRE_FILE_STORAGE') === 'database' || getenv('NHRE_SESSION_STORAGE') === 'database') {
    nhre_ensure_deployment_storage_tables();
}
if (getenv('NHRE_SESSION_STORAGE') === 'database') {
    nhre_ensure_session_storage_table();
}
