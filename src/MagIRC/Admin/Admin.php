<?php

namespace MagIRC\Admin;

use PDO;
use MagIRC\Bootstrap\ApplicationPaths;
use MagIRC\Bootstrap\SlimApplicationFactory;
use MagIRC\Config\Configuration;
use MagIRC\Database\MagircDatabase;
use MagIRC\Security\Security;
use Psr\Log\LoggerInterface;

class Admin
{
    public $slim;
    public $tpl;
    public $db;
    public $cfg;
    private ?LoggerInterface $logger = null;
    private readonly ApplicationPaths $paths;

    public function __construct(?LoggerInterface $logger = null, ?ApplicationPaths $paths = null)
    {
        $this->paths = $paths ?? new ApplicationPaths(dirname(__DIR__, 3));
        $this->db = MagircDatabase::getInstance();
        $this->cfg = new Configuration();
        $logger = $this->logger = $logger ?? \MagIRC\Logging\LoggerFactory::get();
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
        $this->tpl = \Slim\Views\Twig::create($this->paths->private('templates', 'admin'), [
            'cache' => $this->paths->private('tmp', 'cache', 'twig'),
            'debug' => false,
            'autoescape' => 'html',
        ]);
        $this->tpl->addExtension(new \MagIRC\Twig\MarkdownExtension());
        $this->tpl->addExtension(new \MagIRC\Twig\TranslationExtension());
        $admin = $this;
        $errorHandler = function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails, $app) use ($logger, $admin) {
            $logger->error('MagIRC admin request failed.', ['exception_class' => $exception::class]);
            $status = $exception instanceof \Slim\Exception\HttpNotFoundException ? 404 : ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException ? 405 : 500);
            $response = $app->getResponseFactory()->createResponse($status);
            try {
                if ($status === 404 || $status === 405) {
                    return $admin->tpl->render($response, 'error.twig', [
                        'cfg' => $admin->cfg->config,
                        'locales' => $admin->getLocalesSelect(),
                        'err_code' => $status,
                    ]);
                }
                return $admin->tpl->render($response, 'error_fatal.twig', [
                    'cfg' => $admin->cfg->config,
                    'locales' => $admin->getLocalesSelect(),
                    'err_msg' => 'An internal error occurred.',
                    'err_extra' => '',
                    'server' => [],
                ]);
            } catch (\Throwable $renderException) {
                $logger->error('MagIRC admin error template failed.', ['exception_class' => $renderException::class]);
                $response->getBody()->write('Service temporarily unavailable.');
                return $response->withStatus(500)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }
        };
        $this->slim = SlimApplicationFactory::create([
            'config' => $this->cfg->config,
            'locales' => $this->getLocalesSelect(),
            'admin' => $this,
            \Psr\Log\LoggerInterface::class => $logger,
        ], trim($scriptName, '/'), $this->tpl, $errorHandler);

        $csrfStorage = null;
        $guard = new \Slim\Csrf\Guard(
            $this->slim->getResponseFactory(),
            'csrf',
            $csrfStorage,
            function ($request, $handler) {
                $response = $this->slim->getResponseFactory()->createResponse(403);
                $response->getBody()->write('Forbidden');
                return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
            },
            200,
            16,
            true
        );
        $this->slim->add($guard);
        $this->slim->add(new \MagIRC\Http\CsrfTokenMiddleware($guard, $this->tpl->getEnvironment()));
        $this->slim->add(new \MagIRC\Http\NoStoreMiddleware());
    }

    public function paths(): ApplicationPaths
    {
        return $this->paths;
    }

    /** @return array<string, string> */
    public function getLocalesSelect(): array
    {
        return \MagIRC\I18n\LocaleResolver::labels($this->paths->private('locale'));
    }

    /**
     * Admin Login
     * @param mixed $username
     * @param mixed $password
     * @return boolean true: successful, false: failed
     */
    public function login($username, $password)
    {
        if (!is_string($username) || !is_string($password) || $username === '' || $password === '') {
            return false;
        }
        $username = trim($username);
        if ($username === '' || strlen($username) > 128 || strlen($password) > Security::MAX_PASSWORD_LENGTH) {
            return false;
        }
        if (!Security::loginAttemptAllowed($username)) {
            return false;
        }
        $account = $this->db->selectOne('magirc_admin', ['username' => $username]);
        $storedHash = is_array($account) && is_string($account['password'] ?? null)
            ? $account['password']
            : Security::dummyPasswordHash();
        if (!Security::verifyPassword($password, $storedHash) || !is_array($account) || empty($account['password'])) {
            Security::recordLoginFailure($username);
            return false;
        }
        $isLegacy = (bool) preg_match('/^[a-f0-9]{32}$/iD', $account['password']);
        $needsUpgrade = $isLegacy || Security::passwordNeedsRehash($account['password']);
        if ($needsUpgrade) {
            $hash = Security::hashPassword($isLegacy ? trim($password) : $password);
            $updated = $this->db->update('magirc_admin', ['password' => $hash], ['id' => $account['id'], 'password' => $account['password']]);
            if ($updated === false) {
                if ($isLegacy) {
                    ($this->logger ?? (class_exists(\MagIRC\Logging\LoggerFactory::class) ? \MagIRC\Logging\LoggerFactory::get() : null))?->error('MagIRC legacy password upgrade failed.');
                    return false;
                }
                ($this->logger ?? (class_exists(\MagIRC\Logging\LoggerFactory::class) ? \MagIRC\Logging\LoggerFactory::get() : null))?->warning('MagIRC password hash upgrade failed.');
            }
        }
        Security::startSession();
        if (!session_regenerate_id(true)) {
            return false;
        }
        Security::clearLoginFailures($username);
        $now = time();
        $_SESSION['username'] = $username;
        $_SESSION['ipaddr'] = is_scalar($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $_SESSION['_magirc_created_at'] = $now;
        $_SESSION['_magirc_last_activity'] = $now;
        return true;
    }

    /**
     * Returns session status
     * @return boolean true: valid session, false: no valid session
     */
    public function sessionStatus()
    {
        Security::startSession();
        if (!isset($_SESSION["username"]) || !is_string($_SESSION['username'])) {
            return false;
        }
        $remoteValue = $_SERVER['REMOTE_ADDR'] ?? '';
        $remoteAddress = is_scalar($remoteValue) ? (string) $remoteValue : '';
        if (!isset($_SESSION["ipaddr"]) || !is_string($_SESSION['ipaddr']) || $_SESSION["ipaddr"] !== $remoteAddress || !Security::sessionIsFresh()) {
            Security::destroySession();
            return false;
        }
        return true;
    }

    /**
     * Saves the given configuration parameter and value
     * @param mixed $parameter
     * @param mixed $value
     * @return boolean true: updated, false: not updated
     */
    public function saveConfig($parameter, $value)
    {
        if (!is_string($parameter) || !array_key_exists($parameter, $this->cfg->config)) {
            return false;
        }
        if (!is_scalar($value)) {
            return false;
        }
        $normalized = Configuration::normalizeValue($parameter, $value);
        if (in_array($parameter, ['base_url', 'service_webchat'], true) && $value !== '' && $normalized === '') {
            return false;
        }
        $this->cfg->$parameter = $normalized;
        return $this->db->update('magirc_config', ['value' => $normalized], ['parameter' => $parameter]);
    }

    /**
     * Gets the page content for the specified name
     * @param string $name Content identifier
     * @return string HTML content
     */
    public function getContent($name)
    {
        $ps = $this->db->prepare("SELECT text FROM magirc_content WHERE name = :name");
        $ps->bindParam(':name', $name, PDO::PARAM_STR);
        $ps->execute();
        $content = $ps->fetch(PDO::FETCH_COLUMN);
        return $name === 'welcome' ? \MagIRC\Security\HtmlSanitizer::sanitize((string) $content) : $content;
    }

    /**
     * Saves the HTML content for the given page
     * @param string $name Page name
     * @param string $text HTML content
     * @return boolean true: updated, false: not updated
     */
    public function saveContent($name, $text)
    {
        $name = str_replace('content_', '', $name);
        if ($name === 'welcome') {
            $text = \MagIRC\Security\HtmlSanitizer::sanitize($text);
        }
        return $this->db->update('magirc_content', ['text' => $text], ['name' => $name]);
    }
}
