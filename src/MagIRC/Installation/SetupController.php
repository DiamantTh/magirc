<?php

declare(strict_types=1);

namespace MagIRC\Installation;

use MagIRC\Config\ConfigurationStore;
use MagIRC\Database\MagircDatabase;
use MagIRC\Logging\LoggerFactory;
use Throwable;

/** Coordinates the installer steps and renders their private Twig templates. */
final readonly class SetupController
{
    public function __construct(private Installer $installer)
    {
    }

    public function render(int $step, array $post = []): string
    {
        return match ($step) {
            1 => $this->renderStepOne(),
            2 => $this->renderStepTwo($post),
            3 => $this->renderStepThree($post),
            4 => $this->installer->tpl->render('step4.twig', []),
            default => throw new \InvalidArgumentException('Unsupported setup step.'),
        };
    }

    private function renderStepOne(): string
    {
        return $this->installer->tpl->render('step1.twig', [
            'step' => 1,
            'phpversion' => phpversion(),
            'status' => $this->installer->requirementsCheck(),
        ]);
    }

    private function renderStepTwo(array $post): string
    {
        $status = $this->installer->requirementsCheck();
        $dump = $check = $updated = false;
        $savedb = isset($post['savedb']);
        $db = ConfigurationStore::defaults('magirc');

        if ($status['error']) {
            $status['error'] = 'System requirements are not met.';
        } else {
            $configurationDirectory = $this->installer->paths()->private('conf');
            if ($savedb && defined('MAGIRC_SETUP_DB_UNAVAILABLE') && !is_file($configurationDirectory . DIRECTORY_SEPARATOR . '.setup_pending')) {
                $status['error'] = 'Database state could not be verified. Check the configuration locally before continuing.';
            } elseif ($savedb) {
                try {
                    if (!$this->installer->saveConfig($post)) {
                        $status['error'] = 'Configuration is invalid or could not be saved.';
                    }
                } catch (Throwable) {
                    LoggerFactory::get()->warning('MagIRC database configuration was rejected.');
                    $status['error'] = 'Configuration is invalid or could not be saved.';
                }
            }

            $configurationPath = ConfigurationStore::path('magirc', $configurationDirectory);
            if (empty($status['error']) && (is_file($configurationPath) || is_file($configurationDirectory . '/magirc.cfg.php'))) {
                try {
                    $db = ConfigurationStore::load('magirc', $configurationDirectory);
                    $this->installer->db = MagircDatabase::getInstance();
                } catch (Throwable $exception) {
                    LoggerFactory::get()->error('MagIRC setup database check failed.', ['exception_class' => $exception::class]);
                    $status['error'] = 'Database connection failed.';
                }
            } elseif (empty($status['error'])) {
                $status['error'] = 'new';
            }

            if (empty($status['error'])) {
                try {
                    $check = $this->installer->configCheck();
                    if (!$check) {
                        $dump = $this->installer->configDump();
                        if ($dump) {
                            $baseUrl = $this->installer->generateBaseUrl();
                            $this->installer->db->update('magirc_config', ['value' => $baseUrl], ['parameter' => 'base_url']);
                        }
                    } else {
                        $updated = $this->installer->configUpgrade();
                    }
                } catch (Throwable $exception) {
                    LoggerFactory::get()->error('MagIRC setup schema check failed.', ['exception_class' => $exception::class]);
                    $status['error'] = 'Database schema check failed.';
                }
            }
        }

        $db['password'] = '';
        return $this->installer->tpl->render('step2.twig', [
            'step' => 2,
            'status' => $status,
            'dump' => $dump,
            'updated' => $updated,
            'version' => DB_VERSION,
            'check' => $check,
            'db_magirc' => $db,
            'savedb' => $savedb,
        ]);
    }

    private function renderStepThree(array $post): string
    {
        $admins = $this->installer->checkAdmins();
        $error = $admins === null;

        if (isset($post['username']) || isset($post['password'])) {
            if (
                $admins !== false || !$this->installer->createAdmin(
                    $post['username'] ?? null,
                    $post['password'] ?? null
                )
            ) {
                $error = true;
            } else {
                Installer::markInstalled($this->installer->paths()->private('conf'));
                return $this->installer->tpl->render('step4.twig', []);
            }
        }

        $admins = $this->installer->checkAdmins();
        if ($admins === true) {
            Installer::markInstalled($this->installer->paths()->private('conf'));
        }

        return $this->installer->tpl->render('step3.twig', [
            'step' => 3,
            'admins' => $admins === true,
            'can_create' => $admins === false,
            'error' => $error,
        ]);
    }
}
