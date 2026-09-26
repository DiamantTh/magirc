<?php
require_once(__DIR__ . '/../../vendor/autoload.php');

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class LoginTestDatabase
{
    public $account;
    public $updates = array();

    public function selectOne($table, $where)
    {
        return $this->account && $this->account['username'] === $where['username'] ? $this->account : false;
    }

    public function update($table, $values, $where)
    {
        $this->updates[] = array($values, $where);
        $this->account['password'] = $values['password'];
        return true;
    }
}

session_save_path(sys_get_temp_dir());
session_name('magirc_login_test_' . str_replace('.', '', uniqid('', true)));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\MagIRC\Security\Security::startSession();
$admin = (new ReflectionClass(\MagIRC\Admin\Admin::class))->newInstanceWithoutConstructor();
$database = new LoginTestDatabase();
$admin->db = $database;
$loginCsrf = \MagIRC\Security\Security::csrfToken();
check(!$admin->sessionStatus(), 'Anonymous session was treated as authenticated.');
check(\MagIRC\Security\Security::verifyCsrfToken($loginCsrf), 'Anonymous session CSRF state was destroyed by the login page.');

$database->account = array('id' => 3, 'username' => 'modern', 'password' => \MagIRC\Security\Security::hashPassword('Modern-Pass-123'));
$oldSessionId = session_id();
check($admin->login('modern', 'Modern-Pass-123'), 'password_verify login failed.');
check(session_id() !== $oldSessionId, 'Session ID was not regenerated after login.');
check($_SESSION['username'] === 'modern', 'Authenticated username was not stored.');
check(count($database->updates) === 0, 'Modern password hash was unnecessarily rewritten.');
$_SESSION['_magirc_last_activity'] = time() - \MagIRC\Security\Security::SESSION_IDLE_TIMEOUT - 1;
check(!$admin->sessionStatus(), 'Expired idle session was accepted.');

// Missing accounts still perform a password verification and never reveal
// whether the username exists to the caller.
$database->account = false;
check(!$admin->login('missing-user', 'wrong-password'), 'Unknown account was accepted.');

$database->account = array('id' => 5, 'username' => 'rehash', 'password' => password_hash('Rehash-Pass-123', PASSWORD_DEFAULT));
check($admin->login('rehash', 'Rehash-Pass-123'), 'Password rehash login failed.');
check(count($database->updates) === 1, 'An outdated password hash was not upgraded.');

$_SESSION = array();
$database->account = array('id' => 4, 'username' => 'legacy', 'password' => md5('Legacy-Pass'));
check($admin->login('legacy', ' Legacy-Pass '), 'Legacy MD5 login failed.');
check(\MagIRC\Security\Security::verifyPassword('Legacy-Pass', $database->account['password']), 'Legacy MD5 password was not migrated.');
check(count($database->updates) === 2, 'Legacy hash migration did not write exactly once.');
check(!$admin->login('legacy', 'wrong-password'), 'Incorrect password was accepted.');
$longPassword = str_repeat('long-pass-', 12);
$longHash = \MagIRC\Security\Security::hashPassword($longPassword);
check(\MagIRC\Security\Security::verifyPassword($longPassword, $longHash), 'Long passwords were not hashed and verified consistently.');
check(!\MagIRC\Security\Security::verifyPassword(substr($longPassword, 0, 72) . 'different', $longHash), 'Long password hashing truncated the supplied password.');

session_destroy();
echo "login security regressions: OK\n";
