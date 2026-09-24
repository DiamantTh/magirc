# Integrations- und Funktionstests

## Lokale Ausführung

Die normalen Regressionstests benötigen keine Datenbank:

```sh
composer test
```

Für die MySQL-/MariaDB-Tests werden eine erreichbare Testdatenbank und diese Variablen benötigt:

```sh
export MAGIRC_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=magirc_test;charset=utf8mb4'
export MAGIRC_TEST_DB_USER=magirc
export MAGIRC_TEST_DB_PASSWORD=magirc
export MAGIRC_TEST_DB_PORT=3306
vendor/bin/phpunit --testsuite 'MagIRC integration tests'
```

Fehlt `MAGIRC_TEST_DSN`, werden die drei datenbankabhängigen Tests übersprungen. Die Tests legen ihre Tabellen selbst an und entfernen beziehungsweise überschreiben sie vor jedem separaten Testprozess. Es werden keine produktiven Datenbanken verwendet.

## Reproduzierbare Testdaten

`DatabaseIntegrationTest` erzeugt für Anope die Tabellen `anope_currentusage`, `anope_maxusage`, `anope_history`, `anope_server`, `anope_user`, `anope_chan`, `anope_ison`, `anope_maxusers` und `anope_chanstats`. Die Anope-Fixtures enthalten einen sichtbaren und einen eingeschränkten Channel, einen versteckten Service-User sowie aktuelle, maximale und historische Werte.

Für Denora werden die abweichenden Tabellen `denora_current`, `denora_maxvalues`, `denora_server`, `denora_user`, `denora_chan`, `denora_ison`, `denora_stats`, `denora_channelstats`, `denora_serverstats`, `denora_ustats`, `denora_cstats` und `denora_aliases` angelegt. Die Modusspalten verwenden die Denora-Namen `mode_us`, `mode_ls` und `mode_lp`.

Geprüft werden Verbindungsaufbau, JSON-Konfiguration und UTF-8-DSN, aktuelle/maximale/historische Werte, Berechtigungsantworten für sichtbare/versteckte Channels, REST-Statusantworten und getrennte Anope-/Denora-Tabellenlayouts. Die DataTables-Identifier, Filter und Pagination werden zusätzlich in den SQLite-Regressionsprüfungen gegen die produktive `DB`-Schicht validiert.

`InstallationIntegrationTest` legt das MagIRC-Schema in einer leeren Datenbank an, speichert eine neue JSON-Konfiguration, erzeugt einen gehashten Administrator mit `.installed`-Marker und führt anschließend eine Migration von Datenbankversion 18 auf 19 aus. Vorhandener Administrator und Begrüßungstext werden dabei verifiziert.

## CI

`.github/workflows/ci.yml` testet PHP 8.4 und 8.5 jeweils mit einem MariaDB-11.4-Service. Der Workflow installiert Composer- und Yarn-Lockfile reproduzierbar, baut den Tiptap-Bundle, führt Regressionen, Datenbankintegration, Sicherheitschecks, PHPStan, PHPCS, Rector und Composer Audit aus.

Auf dem Host ist PHP 8.5.10 verfügbar; ein PHP-8.4-Binary ist dort nicht
installiert. Die Regression-, Sicherheits- und Installer-Tests wurden zusätzlich
in einem temporären PHP-8.4.25-Container ausgeführt. Die CI-Matrix führt die
vollständige Suite für PHP 8.4 und 8.5 aus.
