<?php
require_once(__DIR__ . '/../../lib/magirc/DB.class.php');
require_once(__DIR__ . '/../../lib/magirc/Security.class.php');
require_once(__DIR__ . '/../../admin/lib/Admin.class.php');

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
MagircSecurity::startSession();
$admin = (new ReflectionClass('Admin'))->newInstanceWithoutConstructor();
$database = new LoginTestDatabase();
$admin->db = $database;

$database->account = array('id' => 3, 'username' => 'modern', 'password' => password_hash('Modern-Pass-123', PASSWORD_DEFAULT));
$oldSessionId = session_id();
check($admin->login('modern', 'Modern-Pass-123'), 'password_verify login failed.');
check(session_id() !== $oldSessionId, 'Session ID was not regenerated after login.');
check($_SESSION['username'] === 'modern', 'Authenticated username was not stored.');
check(count($database->updates) === 0, 'Modern password hash was unnecessarily rewritten.');

$_SESSION = array();
$database->account = array('id' => 4, 'username' => 'legacy', 'password' => md5('Legacy-Pass'));
check($admin->login('legacy', ' Legacy-Pass '), 'Legacy MD5 login failed.');
check(MagircSecurity::verifyPassword('Legacy-Pass', $database->account['password']), 'Legacy MD5 password was not migrated.');
check(count($database->updates) === 1, 'Legacy hash migration did not write exactly once.');
check(!$admin->login('legacy', 'wrong-password'), 'Incorrect password was accepted.');
$longPassword = str_repeat('long-pass-', 12);
$longHash = MagircSecurity::hashPassword($longPassword);
check(MagircSecurity::verifyPassword($longPassword, $longHash), 'Long passwords were not hashed and verified consistently.');
check(!MagircSecurity::verifyPassword(substr($longPassword, 0, 72) . 'different', $longHash), 'Long password hashing truncated the supplied password.');

session_destroy();
echo "login security regressions: OK\n";
