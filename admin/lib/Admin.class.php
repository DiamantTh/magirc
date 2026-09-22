<?php
require_once(__DIR__ . '/../../lib/magirc/ConfigStore.class.php');
require_once(__DIR__ . '/../../lib/magirc/Security.class.php');

use Psr\Log\LoggerInterface;

// Root path
if (!defined('PATH_ROOT')) {
    define('PATH_ROOT', __DIR__ . '/../../');
}

class Admin {
    public $slim;
    public $tpl;
    public $db;
    public $cfg;
    private ?LoggerInterface $logger = null;

    public function __construct(?LoggerInterface $logger = null) {
        $this->db = MagircDB::getInstance();
        $this->cfg = new Config();
        $container = new \DI\Container();
        \Slim\Factory\AppFactory::setContainer($container);
        $this->slim = \Slim\Factory\AppFactory::create();
        $logger = $this->logger = $logger ?? \MagIRC\Logging\LoggerFactory::get();
        $container->set(\Psr\Log\LoggerInterface::class, $logger);
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
        $basePath = trim($scriptName, '/');
        if ($basePath !== '') {
            $this->slim->setBasePath('/' . $basePath);
        }

        $this->tpl = \Slim\Views\Twig::create(__DIR__ . '/../tpl', [
            'cache' => __DIR__ . '/../../tmp/twig',
            'debug' => false,
            'autoescape' => 'html',
        ]);
        $this->tpl->addExtension(new \MagIRC\Twig\MarkdownExtension());
        $this->tpl->addExtension(new \MagIRC\Twig\TranslationExtension());
        $container->set('view', $this->tpl);
        $container->set(\Slim\Views\Twig::class, $this->tpl);

        $this->slim->add(\Slim\Views\TwigMiddleware::create($this->slim, $this->tpl));

        $guard = new \Slim\Csrf\Guard(
            $this->slim->getResponseFactory(),
            'csrf',
            null,
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
        $this->slim->addBodyParsingMiddleware();
        $this->slim->addRoutingMiddleware();

        $errors = $this->slim->addErrorMiddleware(false, true, true);
        $errors->setDefaultErrorHandler(function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails, $logMessage) use ($logger) {
            $logger->error('MagIRC admin request failed.', ['exception_class' => $exception::class]);
            $status = $exception instanceof \Slim\Exception\HttpNotFoundException ? 404 : ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException ? 405 : 500);
            $response = $this->slim->getResponseFactory()->createResponse($status);
            try {
                if ($status === 404 || $status === 405) {
                    return $this->tpl->render($response, 'error.twig', ['err_code' => $status]);
                }
                return $this->tpl->render($response, 'error_fatal.twig', [
                    'err_msg' => 'An internal error occurred.',
                    'err_extra' => '',
                    'server' => [],
                ]);
            } catch (Throwable $renderException) {
                $logger->error('MagIRC admin error template failed.', ['exception_class' => $renderException::class]);
                $response->getBody()->write('Service temporarily unavailable.');
                return $response->withStatus(500)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }
        });
    }

    /**
     * Admin Login
     * @param string $username
     * @param string $password
     * @return boolean true: successful, false: failed
     */
    public function login($username, $password) {
        if (!is_string($username) || !is_string($password) || $username === '' || $password === '') {
            return false;
        }
        $username = trim($username);
        if ($username === '') {
            return false;
        }
        $account = $this->db->selectOne('magirc_admin', ['username' => $username]);
        if (!$account || empty($account['password']) || !MagircSecurity::verifyPassword($password, $account['password'])) {
            return false;
        }
        if (preg_match('/^[a-f0-9]{32}$/iD', $account['password'])) {
            $hash = MagircSecurity::hashPassword(trim($password));
            if ($hash === false || !$this->db->update('magirc_admin', ['password' => $hash], ['id' => $account['id'], 'password' => $account['password']])) {
                ($this->logger ?? \MagIRC\Logging\LoggerFactory::get())->error('MagIRC legacy password upgrade failed.');
                return false;
            }
        }
        if (!session_regenerate_id(true)) {
            return false;
        }
        $_SESSION['username'] = $username;
        $_SESSION['ipaddr'] = $_SERVER['REMOTE_ADDR'] ?? '';
        return true;
    }

    /**
     * Returns session status
     * @return boolean true: valid session, false: no valid session
     */
    public function sessionStatus() {
        if (!isset($_SESSION["username"])) {
            $_SESSION["message"] = "Access denied";
            return false;
        }
        if (!isset($_SESSION["ipaddr"]) || ($_SESSION["ipaddr"] != $_SERVER["REMOTE_ADDR"])) {
            $_SESSION["message"] = "Access denied";
            return false;
        }
        return true;
    }

    /**
     * Saves the given configuration parameter and value
     * @param string $parameter
     * @param string $value
     * @return boolean true: updated, false: not updated
     */
    public function saveConfig($parameter, $value) {
        $this->cfg->$parameter = $value;
        return $this->db->update('magirc_config', ['value' => $value], ['parameter' => $parameter]);
    }

    /**
     * Gets the page content for the specified name
     * @param string $name Content identifier
     * @return string HTML content
     */
    public function getContent($name) {
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
    public function saveContent($name, $text) {
        $name = str_replace('content_', '', $name);
        if ($name === 'welcome') {
            $text = \MagIRC\Security\HtmlSanitizer::sanitize($text);
        }
        return $this->db->update('magirc_content', ['text' => $text], ['name' => $name]);
    }
}
