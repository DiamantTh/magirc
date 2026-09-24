<?php

/** Security helpers shared by the public application, admin panel and setup. */
class MagircSecurity
{
    public const MIN_PASSWORD_LENGTH = 12;
    public const MAX_PASSWORD_LENGTH = 4096;
    public const SESSION_IDLE_TIMEOUT = 1800;
    public const SESSION_ABSOLUTE_TIMEOUT = 28800;

    private static ?string $dummyPasswordHash = null;

    public static function startSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        self::configureSessionStorage();
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
        $now = time();
        $_SESSION['_magirc_created_at'] ??= $now;
        $_SESSION['_magirc_last_activity'] ??= $now;
    }

    /** Configure a private session directory when an application runtime is configured. */
    private static function configureSessionStorage(): void
    {
        $runtime = self::runtimeDirectory();
        if ($runtime === null) {
            return;
        }
        $sessions = $runtime . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($sessions) && !@mkdir($sessions, 0700, true) && !is_dir($sessions)) {
            return;
        }
        @chmod($sessions, 0700);
        if (is_writable($sessions)) {
            session_save_path($sessions);
        }
    }

    private static function runtimeDirectory(): ?string
    {
        $runtime = getenv('MAGIRC_RUNTIME_DIR');
        if (!is_string($runtime) || $runtime === '') {
            $runtime = defined('PATH_ROOT')
                ? PATH_ROOT . 'tmp'
                : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tmp';
        }
        $runtime = rtrim($runtime, DIRECTORY_SEPARATOR);
        if (!is_dir($runtime) && !@mkdir($runtime, 0700, true) && !is_dir($runtime)) {
            return null;
        }
        @chmod($runtime, 0700);
        return $runtime;
    }

    public static function randomBytes($length)
    {
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($strong) {
                return $bytes;
            }
        }
        throw new RuntimeException('A secure random source is unavailable.');
    }

    public static function expireSessionCookie()
    {
        if (!ini_get('session.use_cookies')) {
            return;
        }
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax'
        ]);
    }

    public static function destroySession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        self::expireSessionCookie();
        session_destroy();
    }

    /** Return whether an authenticated session is still inside both lifetime limits. */
    public static function sessionIsFresh(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $now = time();
        $created = filter_var($_SESSION['_magirc_created_at'] ?? null, FILTER_VALIDATE_INT);
        $lastActivity = filter_var($_SESSION['_magirc_last_activity'] ?? null, FILTER_VALIDATE_INT);
        if ($created === false || $lastActivity === false
            || $created < 1 || $lastActivity < $created
            || $now - $created > self::SESSION_ABSOLUTE_TIMEOUT
            || $now - $lastActivity > self::SESSION_IDLE_TIMEOUT) {
            return false;
        }
        $_SESSION['_magirc_last_activity'] = $now;
        return true;
    }

    public static function csrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }
        if (!isset($_SESSION['_magirc_csrf']) || !is_string($_SESSION['_magirc_csrf']) || $_SESSION['_magirc_csrf'] === '') {
            $_SESSION['_magirc_csrf'] = bin2hex(self::randomBytes(32));
        }
        return $_SESSION['_magirc_csrf'];
    }

    public static function hashPassword($password)
    {
        if (!is_string($password) || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password is invalid.');
        }
        // Hashing the full input first avoids bcrypt's 72-byte input limit.
        $hash = password_hash(hash('sha256', $password), self::preferredPasswordAlgorithm());
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }
        return '$magirc$' . $hash;
    }

    public static function preferredPasswordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function passwordNeedsRehash($storedHash): bool
    {
        if (!is_string($storedHash) || !str_starts_with($storedHash, '$magirc$')) {
            return is_string($storedHash) && password_needs_rehash($storedHash, self::preferredPasswordAlgorithm());
        }
        return password_needs_rehash(substr($storedHash, 8), self::preferredPasswordAlgorithm());
    }

    public static function dummyPasswordHash(): string
    {
        return self::$dummyPasswordHash ??= password_hash('MagIRC invalid login sentinel', self::preferredPasswordAlgorithm());
    }

    public static function verifyPassword($password, $storedHash)
    {
        if (!is_string($password) || !is_string($storedHash) || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            return false;
        }
        if (preg_match('/^[a-f0-9]{32}$/iD', $storedHash)) {
            return hash_equals(strtolower($storedHash), md5(trim($password)));
        }
        if (str_starts_with($storedHash, '$magirc$')) {
            return password_verify(hash('sha256', $password), substr($storedHash, 8));
        }
        return password_verify($password, $storedHash);
    }

    public static function verifyCsrfToken($candidate)
    {
        if (!is_string($candidate) || !isset($_SESSION['_magirc_csrf']) || !is_string($_SESSION['_magirc_csrf']) || $_SESSION['_magirc_csrf'] === '') {
            return false;
        }
        return hash_equals($_SESSION['_magirc_csrf'], $candidate);
    }

    /**
     * Apply a small persistent per-IP/username login throttle. The files live
     * in the private runtime directory and contain only timestamps.
     */
    public static function loginAttemptAllowed(string $username): bool
    {
        $state = self::readLoginThrottle($username);
        return count($state) < 5;
    }

    public static function recordLoginFailure(string $username): void
    {
        $state = self::readLoginThrottle($username);
        $state[] = time();
        self::writeLoginThrottle($username, array_slice($state, -20));
    }

    public static function clearLoginFailures(string $username): void
    {
        $path = self::loginThrottlePath($username);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private static function readLoginThrottle(string $username): array
    {
        $path = self::loginThrottlePath($username);
        if ($path === null) {
            return [];
        }
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return [];
        }
        $state = [];
        if (@flock($handle, LOCK_EX)) {
            $contents = stream_get_contents($handle);
            $decoded = is_string($contents) ? json_decode($contents, true) : null;
            if (is_array($decoded)) {
                $cutoff = time() - 900;
                $state = array_values(array_filter($decoded, static fn ($value): bool => is_int($value) && $value >= $cutoff));
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        return $state;
    }

    private static function writeLoginThrottle(string $username, array $state): void
    {
        $path = self::loginThrottlePath($username);
        if ($path === null) {
            return;
        }
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }
        if (@flock($handle, LOCK_EX)) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(array_values($state), JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        @chmod($path, 0600);
    }

    private static function loginThrottlePath(string $username): ?string
    {
        $runtime = self::runtimeDirectory();
        if ($runtime === null) {
            return null;
        }
        $directory = $runtime . DIRECTORY_SEPARATOR . 'login-throttle';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return null;
        }
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identity = (is_string($remoteAddress) || is_int($remoteAddress) ? (string) $remoteAddress : 'unknown') . "\0" . strtolower($username);
        return $directory . DIRECTORY_SEPARATOR . hash('sha256', $identity) . '.json';
    }

    /** Send headers suitable for all HTML, JSON and setup responses. */
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self' https://www.paypal.com; img-src 'self' data: https:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://pagead2.googlesyndication.com https://s7.addthis.com; connect-src 'self' https:");
    }
}
