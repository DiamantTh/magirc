# MagIRC #

Thank you for your interest in MagIRC, a PHP-based Web Frontend for IRC Services released under the GPLv3 license.

This software is a complete rewrite of phpDenora, a PHP-based Web Frontend for the [Denora Stats](https://github.com/denora/denora) project.

Meanwhile, MagIRC also works with [Anope](https://www.anope.org/) 2.0, which supersedes Denora.
We recommend using Anope, since it is being actively maintained and has improved performance and stability over Denora.
In case you want to migrate from Denora to Anope, we created a script for this task (see below).

### Main features ###
* REST service
* [Twig](https://twig.sensiolabs.org) templating engine
* [jQuery](https://www.jquery.com/)-based UI with AJAX interactions
* HTML5 and CSS3
* Easy installation
* Administration panel
* Slick design

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

### Using [composer](https://getcomposer.org) and [yarn](https://yarnpkg.com) (recommended) ###

1. Extract or clone the release, then build the locked dependencies and public assets:
	- `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
	- `yarn install --frozen-lockfile --production=false`
	- `yarn build:editor && yarn build:runtime`
	- Remove `node_modules/` from the webroot after the build; the application serves only `assets/vendor/` and the bundled editor at runtime.
2. Create writable `conf/` and `tmp/` directories for the web server user (recommended mode `0700`), while keeping their deny rules in place. The installer stores new database settings in `conf/*.json`; it reads legacy `conf/*.cfg.php` files without executing their contents.
3. Use your web browser to navigate to the setup folder on your server and follow on-screen instructions.
   Example: https://`yourpathtomagirc`/setup/

### Development checks ###

* `composer test` runs PHPUnit regression tests and the security regression scripts.
* `composer test:integration` runs the MySQL/MariaDB Anope and Denora integration suite when `MAGIRC_TEST_DSN` is configured.
* `composer test:installation` renders the setup entry point and checks the PHP 8.4 requirement guard (run after `yarn build:runtime`).
* `composer analyse` runs PHPStan; `composer cs` checks PSR-12; `composer rector:check` checks the configured PHP 8.4 Rector set.
* `yarn install --frozen-lockfile` reproduces the frontend dependencies from `yarn.lock`.
* `yarn build:editor` rebuilds the locally bundled Tiptap welcome editor.
* `yarn build:runtime` copies the browser runtime files to `assets/vendor`; `yarn test:runtime-assets` verifies the production asset set.

Database-backed Anope/Denora integration tests and their required environment variables are documented in [doc/integration-tests.md](doc/integration-tests.md).

The public and REST routes are registered in `src/MagIRC/Routes`; theme directories contain templates and presentation assets only. Existing installations using `conf/*.cfg.php` remain readable, while all new writes use validated JSON configuration files. Gettext still uses the native PO/MO directory contract (`locale/<locale>/LC_MESSAGES/messages.*`); this upstream snapshot does not contain tracked catalog files, so the English msgid is the fallback until catalogs are supplied.
   Setup is disabled after the first administrator is created. If you need to run the setup workflow again for maintenance, temporarily set the server environment variable `MAGIRC_ALLOW_SETUP=1`; this does not re-enable administrator creation.

For a complete installation/update runbook, including database privileges, permissions, backups and troubleshooting, see [doc/operations.md](doc/operations.md). Apache and Nginx examples are in [doc/apache-vhost.conf.example](doc/apache-vhost.conf.example) and [doc/nginx.conf.example](doc/nginx.conf.example).

### Using a release package ###
1. Download the latest MagIRC release package from [GitHub](https://h9k.github.io/magirc/)
2. Extract the MagIRC archive to your web server and move its content to the MagIRC directory.
   A release archive must contain the generated `assets/vendor/` files and `admin/js/welcome-editor.bundle.js`; if they are absent, run the build commands from the installation section before exposing the site.
3. Use your web browser to navigate to the setup folder on your server and follow on-screen instructions.
   Example: https://`yourpathtomagirc`/setup/

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
Please look at the `setup/sql/anope.sql` file and adapt it if needed (table prefixes, etc.) and run it against your Anope database.

Note that you need the MySQL `event_scheduler` set to `ON` in the MySQL server. If you have enough rights, you can turn it on via `SET GLOBAL event_scheduler = ON;`.

If you have access to the server configuration, you can modify the msql configuration file (usually `my.cnf` or `mysqld.cnf`) by setting `event_scheduler = on` in the `[mysqld]` block.

NOTE: Ubuntu/Debian users: You should **ONLY** edit the file located under `/etc/mysql/mysql.conf.d/` directory, otherwise MySQL will refuse to start/restart. On other distros/OS's, you should look for where the `mysqld.cnf` file is located.

### Migrating from Denora to Anope ###
If you want to switch from Denora to Anope, please proceed as follows:

1. Install Anope (see above)
2. Shut down Denora
3. Make Anope join the network and double check that it is working fine, e.g. the MySQL tables are being filled with data
4. Configure the `setup/tools/denora2anope.php` script and then run it from command line with `php denora2anope.php`. Be patient and do not interrupt the process!


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

To enable URL rewriting make sure your apache has the `mod_rewrite` module enabled. Then rename `htaccess.txt` to `.htaccess` and enable rewriting in the MagIRC Admin Panel.
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
