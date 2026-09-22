<?php

/** Security helpers shared by the public application, admin panel and setup. */
class MagircSecurity
{
    public static function startSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
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

    public static function csrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSession();
        }
        if (empty($_SESSION['_magirc_csrf'])) {
            $_SESSION['_magirc_csrf'] = bin2hex(self::randomBytes(32));
        }
        return $_SESSION['_magirc_csrf'];
    }

    public static function hashPassword($password)
    {
        if (!is_string($password)) {
            throw new InvalidArgumentException('Password is invalid.');
        }
        // Hashing the full input first avoids bcrypt's 72-byte input limit.
        $hash = password_hash(hash('sha256', $password), PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }
        return '$magirc$' . $hash;
    }

    public static function verifyPassword($password, $storedHash)
    {
        if (!is_string($password) || !is_string($storedHash)) {
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
        if (!is_string($candidate) || empty($_SESSION['_magirc_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_magirc_csrf'], $candidate);
    }
}
