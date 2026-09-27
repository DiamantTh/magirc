# MagIRC #

Thank you for your interest in MagIRC, a PHP-based Web Frontend for IRC Services released under the GPLv3 license.

This software is a complete rewrite of phpDenora, a PHP-based Web Frontend for the [Denora Stats](https://github.com/denora/denora) project.

Meanwhile, MagIRC also works with [Anope](https://www.anope.org/) 2.0, which supersedes Denora.
We recommend using Anope, since it is being actively maintained and has improved performance and stability over Denora.
In case you want to migrate from Denora to Anope, we created a script for this task (see below).

### Main features ###

`httpdocs/` is the only public document root. Point Apache or Nginx there;
PHP application code, Composer dependencies, configuration, templates, SQL,
locale catalogs and runtime files remain outside it. The public URLs for
statistics, administration, REST and setup are preserved under that root.

* REST service
* [Twig](https://twig.sensiolabs.org) templating engine
* [jQuery](https://www.jquery.com/)-based UI with AJAX interactions
* HTML5 and CSS3
* Easy installation
* Administration panel
* Slick design

### Project layout ###

* `httpdocs/` contains only the four HTTP entry points and browser-delivered assets.
* `src/MagIRC/` contains the shared PHP application, grouped by its responsibilities: bootstrap, admin, installer, database, HTTP, routes, security, IRC services and statistics.
* `templates/` contains private admin and setup Twig templates. `themes/<name>/templates/` contains each statistics theme; its public CSS, JavaScript, images and fonts live in `httpdocs/assets/themes/<name>/`.
* `resources/` holds non-public application inputs such as SQL schemas and the Tiptap editor source. `locale/` keeps the translated PO and compiled MO catalogs.
* `conf/` stores installation-specific configuration and markers; `tmp/` stores logs and runtime caches. Neither is under the document root.
* `tests/`, `scripts/` and `doc/` contain quality checks, build/maintenance tools and operator documentation. `vendor/` and `node_modules/` are generated dependencies, not application source.

### Requirements ###
* Web server with PHP 8.4 or newer and the `pdo_mysql`, `gettext`, `mbstring`, `dom` and `xml` extensions installed
* Composer 2 and Yarn 1 for the installation/build step. Node.js and Yarn are not needed by the running application after the production assets have been built.
* Slim 4, Twig 3 and PSR-7/15/17 components are installed from `composer.lock`; the application requires PHP 8.4 or newer.
* Web Browser supporting HTML5, CSS3 and JavaScript
* Any of the following:
	* [Denora Stats](https://github.com/denora/denora) v1.5 server with MySQL enabled
	* [Anope](https://www.anope.org/) v2.0 with the `m_mysql`, `m_chanstats` and `irc2sql` modules enabled
* Supported IRC Daemons: Bahamut, Charybdis, InspIRCd, ircd-rizon, IRCu, Nefarious, Ratbox, ScaryNet, Unreal


## Magirc installation / upgrade ##

### Security regression tests ###

Run the regression suite with `composer test`; this includes Twig, locale, REST routing, PDO and Anope/Denora checks. The standalone security tests can also be run with `composer test:security`.

Security controls and remaining deployment risks are documented in [doc/security.md](doc/security.md).

### Using [composer](https://getcomposer.org) and [yarn](https://yarnpkg.com) (recommended) ###

1. Extract or clone the release, then build the locked dependencies and public assets:
	- `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
	- `yarn install --frozen-lockfile --production=false`
	- `yarn build:editor && yarn build:runtime`
	- The web server serves generated files only from `httpdocs/assets/`; `node_modules/` and Composer `vendor/` remain outside the document root.
2. Create writable `conf/` and `tmp/` directories for the web server user (recommended mode `0700`), while keeping their deny rules in place. The installer stores new database settings in `conf/*.json`; it reads legacy `conf/*.cfg.php` files without executing their contents.
3. Use your web browser to navigate to the setup folder on your server and follow on-screen instructions.
   Example: https://`yourpathtomagirc`/setup/

### Development checks ###

* `composer check` is the native, GitHub-independent check: locked Composer platform requirements and audit, PHPUnit regressions, security scripts, PHPStan, PHPCS, Rector and existing frontend asset tests. Run `composer install` with development dependencies and build the locked frontend assets first. It does not touch an application database; tests may write disposable files under the local temporary directory or `tmp/`. Composer Audit requires network access.
* `composer test` runs PHPUnit regressions and the security scripts without database fixtures.
* `composer check:isolated` adds Anope/Denora/MagIRC database fixtures, an installation smoke test and real HTTP requests to an application copy started on `127.0.0.1`. Set `MAGIRC_TEST_ISOLATED=1` and point `MAGIRC_TEST_DSN` at a dedicated MySQL/MariaDB database named exactly `magirc_test`; the fixture tests create and drop tables there. Never point this mode at a production database. See [doc/integration-tests.md](doc/integration-tests.md).
* `composer test:integration` runs only the destructive database fixtures and requires the same isolation marker and database name.
* `composer test:installation` renders the setup entry point from a temporary application copy and checks the PHP 8.4 requirement guard (run after `yarn build:runtime`).
* `composer analyse` runs PHPStan; `composer cs` checks PSR-12; `composer rector:check` checks the configured PHP 8.4 Rector set.
* `yarn install --frozen-lockfile` reproduces the frontend dependencies from `yarn.lock`.
* `yarn build:editor` rebuilds the locally bundled Tiptap welcome editor.
* `yarn build:runtime` copies the browser runtime files to `httpdocs/assets/vendor`; `yarn test:runtime-assets` verifies the production asset set.

Database-backed Anope/Denora integration tests and their required environment variables are documented in [doc/integration-tests.md](doc/integration-tests.md).

The optional GitHub workflow starts only through `workflow_dispatch` and calls `composer check:isolated` after installing and building dependencies. GitHub repository settings currently allow only local Actions, so that optional workflow cannot start its external setup Actions until the repository owner separately changes that policy. Native checks do not depend on GitHub Actions.

The public and REST routes are registered in `src/MagIRC/Routes`. Existing installations using `conf/*.cfg.php` remain readable, while all new writes use validated JSON configuration files. Gettext uses the native PO/MO directory contract, and the translated source catalogs plus their compiled catalogs are kept under `locale/<locale>/LC_MESSAGES/`.
   Setup is disabled after the first administrator is created. If you need to run the setup workflow again for maintenance, temporarily set the server environment variable `MAGIRC_ALLOW_SETUP=1`; this does not re-enable administrator creation.

For a complete installation/update runbook, including database privileges, permissions, backups and troubleshooting, see [doc/operations.md](doc/operations.md). Apache and Nginx examples are in [doc/apache-vhost.conf.example](doc/apache-vhost.conf.example) and [doc/nginx.conf.example](doc/nginx.conf.example).
Release packaging and the runtime/build-host split are described in [doc/release.md](doc/release.md).

### Using an assembled deployment package ###
1. A maintainer can create a version-neutral deployment snapshot for QA or deployment tests on a build machine with `composer release:package`; details are in [doc/release.md](doc/release.md). This command does not publish a release or select a product version. Ordinary GitHub source archives do not include generated runtime assets.
2. Verify the `.sha256` file, then extract the archive outside the webroot. It includes production Composer dependencies and built frontend assets, so the target server does not need Composer, Node.js, Yarn or Docker.
3. Point Apache or Nginx at the package's `httpdocs/`, set the documented `conf/` and `tmp/` permissions, and open `/setup/`.

### Using git ###
You need a git client, [composer](https://getcomposer.org) and [yarn](https://yarnpkg.com)

1. Execute the following commands:
	- To install:
	    - `git clone git://github.com/h9k/magirc.git`
	    - `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
	    - `yarn install --frozen-lockfile --production=false`
	    - `yarn build:editor && yarn build:runtime`
	- To update:
	    - `git pull`
	    - Back up `conf/` and the MagIRC database.
	    - `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
	    - `yarn install --frozen-lockfile --production=false && yarn build:editor && yarn build:runtime`
	    - Remove stale Twig/cache files below `tmp/` after the update.
2. Use your web browser to navigate to the setup folder on your server and follow on-screen instructions.
   Example: https://`yourpathtomagirc`/setup/


## Anope configuration ###
You need Anope 2.0.0 or later and the following modules enabled and set up:

    m_mysql
    m_chanstats
    irc2sql

These modules are included in the Anope codebase under `extra`. Please refer to the Anope documentation on how to set those up.

Also, you will need additional database tables, views and stored procedures for the Anope database in order to get the data needed by MagIRC.
Please look at the `resources/sql/anope.sql` file and adapt it if needed (table prefixes, etc.) and run it against your Anope database.

Note that you need the MySQL `event_scheduler` set to `ON` in the MySQL server. If you have enough rights, you can turn it on via `SET GLOBAL event_scheduler = ON;`.

If you have access to the server configuration, you can modify the msql configuration file (usually `my.cnf` or `mysqld.cnf`) by setting `event_scheduler = on` in the `[mysqld]` block.

NOTE: Ubuntu/Debian users: You should **ONLY** edit the file located under `/etc/mysql/mysql.conf.d/` directory, otherwise MySQL will refuse to start/restart. On other distros/OS's, you should look for where the `mysqld.cnf` file is located.

### Migrating from Denora to Anope ###
If you want to switch from Denora to Anope, please proceed as follows:

1. Install Anope (see above)
2. Shut down Denora
3. Make Anope join the network and double check that it is working fine, e.g. the MySQL tables are being filled with data
4. Configure `scripts/denora2anope.php` and run it from the project root with `php scripts/denora2anope.php`. Be patient and do not interrupt the process!


## Denora configuration ##

### Required Denora settings ###

**Change** this to a higher value, such as 15 days (15d) to keep information for a longer time.
Important: the servercache value must NOT be smaller than the usercache value!

    usercache 15d;
    servercache 30d;

**Change** this to 1h

    uptimefreq 1h;

**Enable** the following parameters by removing the '#' in front:

    ctcpusers;
    keepusers;
    keepservers;

**Disable** the following parameter by adding a '#' in front:

    #largenet;

### Optional Denora settings ###
Limiting chanstats to +r users improves nick tracking.
To use this feature **enable** the following parameters by removing the '#' in front:

    ustatsregistered;


## Web Server configuration ##

### Apache ###
The `AcceptPathInfo` directive should be set to `Default` or `On` in the Apache configuration. It is by default on most servers.

For clean statistics URLs, enable Apache `mod_rewrite` (the shipped `httpdocs/.htaccess` contains the rewrite rules) or use the supplied Nginx example, then enable URL rewriting in the MagIRC Admin Panel. Keep the web server document root set to `httpdocs/`.
This is optional, MagIRC also works without rewriting on Apache.

For a vhost with `AllowOverride None`, use the [Apache example](doc/apache-vhost.conf.example), which includes the equivalent internal-path restrictions and PHP-FPM handling.

It is also recommended, if you allow slashes `/` in your nicknames or channel names, to set `AllowEncodedSlashes On`

### Nginx ###
Use the complete [Nginx example](doc/nginx.conf.example). It handles both
rewritten URLs and legacy `index.php/path` URLs, passes only existing PHP
entry points to PHP-FPM, and denies configuration, source, cache, test and
dependency paths. Adjust the document root and PHP-FPM socket for the host.

### lighttpd ###
Your lighttpd configuration file should contain this code (along with other settings you may need). This code requires lighttpd >= 1.4.24.

    url.rewrite-if-not-file = ("^" => "/index.php")
