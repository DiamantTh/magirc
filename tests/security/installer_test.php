<?php
$temp = sys_get_temp_dir() . '/magirc-installer-' . str_replace('.', '', uniqid('', true));
mkdir($temp, 0700);
define('MAGIRC_CONF_DIR', $temp);
require_once(__DIR__ . '/../../lib/magirc/DB.class.php');
require_once(__DIR__ . '/../../setup/lib/Setup.class.php');

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class InstallerTestDatabase
{
    public $record = false;
    public $admin = null;
    public $inserts = 0;

    public function query($query, $type = null)
    {
        $this->record = $this->admin ? array('id' => 1) : false;
        return true;
    }

    public function prepare($query)
    {
        return new InstallerTestStatement($this);
    }
}

class InstallerTestStatement
{
    private $database;
    private $values = array();

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function bindValue($key, $value, $type)
    {
        $this->values[$key] = $value;
        return true;
    }

    public function execute()
    {
        $this->database->inserts++;
        $this->database->admin = array('id' => 1, 'username' => $this->values[':username'], 'password' => $this->values[':password']);
        return true;
    }
}

try {
    check(Setup::reconcileInstallationMarker(false), 'Setup pending marker could not be created.');
    check(is_file($temp . '/.setup_pending') && !is_file($temp . '/.installed'), 'An installation without an administrator was marked as installed.');
    check(Setup::reconcileInstallationMarker(true), 'Installed marker could not be created after administrator verification.');
    check(is_file($temp . '/.installed') && !is_file($temp . '/.setup_pending'), 'Setup pending marker was not cleared after administrator verification.');
    @unlink($temp . '/.installed');
    $setup = (new ReflectionClass('Setup'))->newInstanceWithoutConstructor();
    $database = new InstallerTestDatabase();
    $setup->db = $database;
    check(!$setup->createAdmin('owner', 'short'), 'Installer accepted a password below the minimum length.');
    check($database->inserts === 0, 'Weak password attempt modified the administrator table.');
    file_put_contents($temp . '/.installing', "stale\n");
    check($setup->createAdmin('owner', 'A-Strong-Password'), 'First administrator could not be created.');
    check(MagircSecurity::verifyPassword('A-Strong-Password', $database->admin['password']), 'Installer did not store a password_hash hash.');
    check(is_file($temp . '/.installed'), 'Successful install did not create its disable marker.');
    check(!is_file($temp . '/.installing'), 'Installer lock was not released after recovering a stale lock.');
    check(!$setup->createAdmin('attacker', 'Another-Password'), 'Installer allowed a second administrator.');
    check($database->inserts === 1, 'Installer performed more than one administrator insert.');
    echo "installer security regressions: OK\n";
} finally {
    foreach (glob($temp . '/*') as $file) {
        unlink($file);
    }
    @unlink($temp . '/.installed');
    @unlink($temp . '/.installing');
    @rmdir($temp);
}
