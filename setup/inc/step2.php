<?php
$status = $setup->requirementsCheck();
$dump = $check = $updated = false;
$savedb = isset($_POST['savedb']);
$db = MagircConfigStore::defaults('magirc');

if ($status['error']) {
    $status['error'] = 'System requirements are not met.';
} else {
    if ($savedb && defined('MAGIRC_SETUP_DB_UNAVAILABLE') && !is_file(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.setup_pending')) {
        $status['error'] = 'Database state could not be verified. Check the configuration locally before continuing.';
    } elseif ($savedb) {
        try {
            $setup->saveConfig();
        } catch (Throwable) {
            error_log('MagIRC database configuration was rejected.');
            $status['error'] = 'Configuration is invalid or could not be saved.';
        }
    }

    if (empty($status['error']) && (is_file(MAGIRC_CFG_FILE) || is_file(__DIR__ . '/../../conf/magirc.cfg.php'))) {
        try {
            $db = MagircConfigStore::load('magirc', MAGIRC_CONF_DIR);
            $setup->db = Magirc_DB::getInstance();
        } catch (Throwable $exception) {
            error_log('MagIRC setup database check failed: ' . $exception->getMessage());
            $status['error'] = 'Database connection failed.';
        }
    } elseif (empty($status['error'])) {
        $status['error'] = 'new';
    }

    if (empty($status['error'])) {
        try {
            $check = $setup->configCheck();
            if (!$check) {
                $dump = $setup->configDump();
                if ($dump) {
                    $base_url = $setup->generateBaseUrl();
                    $setup->db->update('magirc_config', ['value' => $base_url], ['parameter' => 'base_url']);
                }
            } else {
                $updated = $setup->configUpgrade();
            }
        } catch (Throwable $exception) {
            error_log('MagIRC setup schema check failed: ' . $exception->getMessage());
            $status['error'] = 'Database schema check failed.';
        }
    }
}

$db['password'] = '';
$template = $setup->tpl->load('step2.twig');
echo $template->render([
    'step' => 2,
    'status' => $status,
    'dump' => $dump,
    'updated' => $updated,
    'version' => DB_VERSION,
    'check' => $check,
    'db_magirc' => $db,
    'savedb' => $savedb
]);
