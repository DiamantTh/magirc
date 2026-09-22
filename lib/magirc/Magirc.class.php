<?php
// Root path
if (!defined('PATH_ROOT')) {
    define('PATH_ROOT', __DIR__ . '/../../');
}

class Magirc {
    public $db;
    public $cfg;
    public $slim;
    public $translator;
    public $service;

    public function __construct($useTemplateEngine = false) {
        $this->db = self::initializeDatabase();
        $this->cfg = self::initializeConfiguration();
        $this->service = self::initializeService();
        $this->slim = self::initializeFramework($useTemplateEngine);
        self::initializeLocalization();
    }

    private function initializeFramework($useTemplateEngine) {
        $container = new \DI\Container();
        $container->set('config', $this->cfg->config);
        $container->set('locales', $this->getLocalesSelect());
        $container->set('magirc', $this);
        $logger = \MagIRC\Logging\LoggerFactory::get();
        $container->set(\Psr\Log\LoggerInterface::class, $logger);

        \Slim\Factory\AppFactory::setContainer($container);
        $app = \Slim\Factory\AppFactory::create();
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $basePath = dirname($scriptName);
        if (!$useTemplateEngine || empty($this->cfg->rewrite_enable)) {
            $basePath = $scriptName;
        }
        $basePath = trim($basePath, '/');
        if ($basePath !== '') {
            $app->setBasePath('/' . $basePath);
        }

        if ($useTemplateEngine) {
            $templatePath = __DIR__ . '/../../theme/' . $this->cfg->theme . '/tpl';
            $view = \Slim\Views\Twig::create($templatePath, [
                'cache' => __DIR__ . '/../../tmp/twig',
                'debug' => false,
                'autoescape' => 'html',
            ]);
            $view->addExtension(new \MagIRC\Twig\TranslationExtension());
            $container->set(\Slim\Views\Twig::class, $view);
            $container->set('view', $view);
            $app->add(\Slim\Views\TwigMiddleware::create($app, $view));
        }

        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $errors = $app->addErrorMiddleware(false, true, true);
        $errors->setDefaultErrorHandler(function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails, $logMessage) use ($app, $useTemplateEngine, $logger) {
            if ($logErrors) {
            $logger->error('MagIRC request failed.', ['exception_class' => $exception::class]);
            }
            $notFound = $exception instanceof \Slim\Exception\HttpNotFoundException;
            $notAllowed = $exception instanceof \Slim\Exception\HttpMethodNotAllowedException;
            $status = $notFound ? 404 : ($notAllowed ? 405 : 500);
            $response = $app->getResponseFactory()->createResponse($status);

            if (!$useTemplateEngine) {
                $message = $status === 404 ? 'HTTP 404 Not Found' : ($status === 405 ? 'HTTP 405 Not Allowed' : 'HTTP 500 Internal Server Error');
                $response->getBody()->write(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }

            try {
                $view = $app->getContainer()->get(\Slim\Views\Twig::class);
                if ($status !== 500) {
                    return $view->render($response, 'error.twig', [
                        'err_code' => $status,
                        'cfg' => $this->cfg->config,
                        'locales' => $this->getLocalesSelect(),
                    ]);
                }
                return $view->render($response, 'error_fatal.twig', [
                    'err_msg' => 'An internal error occurred.',
                    'err_extra' => '',
                    'server' => [],
                    'cfg' => $this->cfg->config,
                    'locales' => $this->getLocalesSelect(),
                ]);
            } catch (Throwable $renderException) {
                $logger->error('MagIRC error template failed.', ['exception_class' => $renderException::class]);
                $response->getBody()->write('Service temporarily unavailable.');
                return $response->withStatus(500)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }
        });

        return $app;
    }

    private function initializeDatabase() {
        require_once(__DIR__.'/MagircDB.php');
        $db = MagircDB::getInstance();
        $db->query("SHOW TABLES LIKE 'magirc_config'", SQL_INIT);
        if (!$db->record) {
            die('Database table missing. Please run setup.');
        }
        return $db;
    }

    private function initializeConfiguration() {
        $cfg = new Config($this->db);
        if ($cfg->db_version < DB_VERSION) die('Upgrade in progress. Please wait a few minutes, thank you.');
        date_default_timezone_set($cfg->timezone);
        define('DEBUG', $cfg->debug_mode);
        define('BASE_URL', $cfg->base_url . '/');
        if ($cfg->debug_mode < 1) {
            ini_set('display_errors','off');
            error_reporting(E_ERROR);
        }
        return $cfg;
    }

    private function initializeService() {
        define('IRCD', $this->cfg->ircd_type);
        switch($this->cfg->service) {
            case 'anope':
                require_once(__DIR__.'/../../lib/magirc/services/Anope.class.php');
                return new Anope();
            case 'denora':
                require_once(__DIR__.'/../../lib/magirc/services/Denora.class.php');
                return new Denora();
            default:
                return null;
        }
    }

    private function initializeLocalization() {
        $locale = self::getLocale();
        \MagIRC\I18n\LocaleResolver::activate($locale, __DIR__ . '/../../locale');
        define('LOCALE', $locale);
        define('LANG', substr($locale, 0, 2));
    }

    private function getLocale() {
        $locales = self::getLocales();
        $resolved = \MagIRC\I18n\LocaleResolver::resolve(
            isset($_GET['locale']) && is_string($_GET['locale']) ? $_GET['locale'] : null,
            isset($_COOKIE['magirc_locale']) && is_string($_COOKIE['magirc_locale']) ? $_COOKIE['magirc_locale'] : null,
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '',
            $locales,
            (string) $this->cfg->locale
        );
        if (isset($_GET['locale']) && is_string($_GET['locale']) && in_array($_GET['locale'], $locales, true)) {
            setcookie('magirc_locale', $resolved, [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $resolved;
    }

    /**
     * Gets a list of available locales
     * @return array
     */
    private function getLocales() {
        $locales = [];
        foreach (glob(PATH_ROOT."locale/*") as $filename) {
            if (is_dir($filename)) {
                $locales[] = basename($filename);
            }
        }
        return $locales;
    }

    public function getLocalesSelect() {
        $locales = [];
        foreach (glob(__DIR__."/../../locale/*") as $filename) {
            if (is_dir($filename)) {
                $locale = basename($filename);
                //This is dirty but I'm lazy...
                $language = match ($locale) {
                    'en_US' => "English",
                    'de_DE' => "Deutsch",
                    'es_ES' => "Español",
                    'fr_FR' => "Français",
                    'it_IT' => "Italiano",
                    'nl_NL' => "Nederlands",
                    'ms_MY' => "Melayu",
                    'tr_TR' => "Türkçe",
                    'pt_PT' => "Português",
                    default => $locale,
                };
                $locales[$locale] = $language;
            }
        }
        return $locales;
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
        return $ps->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Prepares the given data array for use with DataTables
     * (Used by the RESTful API)
     * @param mixed $data Data
     * @param string $idcolumn Column name to use as index for the DataTables automatic row id. If not specified, the first column will be used.
     */
    public function arrayForDataTables($data, $idcolumn = null) {
        if (@$_GET['format'] == "datatables") {
            if (!$idcolumn && count($data) > 0) $idcolumn = key($data[0]);
            foreach ($data as $key => $val) {
                if (is_array($data[$key])) $data[$key]["DT_RowId"] = $val[$idcolumn];
                else $data[$key]->DT_RowId = $val->$idcolumn;
            }
            return ['data' => $data];
        }
        return $data;
    }

    /**
     * Returns the session status
     * @return boolean true: valid session, false: invalid or no session
     */
    public function sessionStatus() {
        if (!isset($_SESSION["loginUsername"])) {
            $_SESSION["message"] = "Access denied";
            return false;
        }
        if (!isset($_SESSION["loginIP"]) || ($_SESSION["loginIP"] != $_SERVER["REMOTE_ADDR"])) {
            $_SESSION["message"] = "Access denied";
            return false;
        }
        return true;
    }

    /**
     * Returns the given text with html tags for colors and styling
     * @param string $text IRC text
     * @return string HTML text
     */
    public static function irc2html($text) {
        $lines = explode("\n", mb_convert_encoding($text, 'ISO-8859-1'));
        $out = '';

        foreach ($lines as $line) {
            $line = nl2br(htmlentities(mb_convert_encoding($line, 'ISO-8859-1'), ENT_COMPAT));
            // replace control codes
            $line = preg_replace_callback('/[\003](\d{0,2})(,\d{1,2})?([^\003\x0F]*)(?:[\003](?!\d))?/', function($matches) {
                        $colors = ['#FFFFFF', '#000000', '#00007F', '#009300', '#FF0000', '#7F0000', '#9C009C', '#FC7F00', '#FFFF00', '#00FC00', '#009393', '#00FFFF', '#0000FC', '#FF00FF', '#7F7F7F', '#D2D2D2'];
                        $options = '';

                        if ($matches[2] != '') {
                            $bgcolor = trim(substr($matches[2], 1));
                            if ((int) $bgcolor < count($colors)) {
                                $options .= 'background-color: ' . $colors[(int) $bgcolor] . '; ';
                            }
                        }

                        $forecolor = trim($matches[1]);
                        if ($forecolor !== '' && (int) $forecolor < count($colors)) {
                            $options .= 'color: ' . $colors[(int) $forecolor] . ';';
                        }

                        if ($options !== '') {
                            return '<span style="' . $options . '">' . $matches[3] . '</span>';
                        }
                        return $matches[3];
                    }, $line);
            $line = preg_replace('/[\002]([^\002\x0F]*)(?:[\002])?/', '<strong>$1</strong>', $line);
            $line = preg_replace('/[\x1F]([^\x1F\x0F]*)(?:[\x1F])?/', '<span style="text-decoration: underline;">$1</span>', $line);
            $line = preg_replace('/[\x12]([^\x12\x0F]*)(?:[\x12])?/', '<span style="text-decoration: line-through;">$1</span>', $line);
            $line = preg_replace('/[\x16]([^\x16\x0F]*)(?:[\x16])?/', '<span style="font-style: italic;">$1</span>', $line);
            $line = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\S+]*(\?\S+)?)?)?)@', "<a href='$1' class='topic'>$1</a>", $line);
            // remove dirt
            $line = preg_replace('/[\x00-\x1F]/', '', $line);
            $line = preg_replace('/[\x7F-\xFF]/', '', $line);
            // append line
            if ($line != '') {
                $out .= $line;
            }
        }

        return $out;
    }

}
