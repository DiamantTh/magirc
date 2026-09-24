# Integrations- und Funktionstests

## Lokale Ausführung

Die lesenden Prüfungen benötigen keine Datenbank und verändern keine vorhandene Installation:

```sh
composer check
```

Voraussetzungen sind `composer install` mit Entwicklungsabhängigkeiten, `yarn install --frozen-lockfile` und gebaute Runtime-Assets. Der Composer-Audit benötigt Netzwerkzugang. PHPUnit und die Sicherheitsprüfungen verwenden temporäre Dateien beziehungsweise den lokalen Cache unter `tmp/`.

Für die schreibenden MySQL-/MariaDB- und HTTP-Tests wird eine **eigens dafür angelegte, isolierte** Datenbank mit dem Namen `magirc_test` benötigt. Sie darf keine fremden Tabellen oder produktiven Daten enthalten:

```sh
export MAGIRC_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=magirc_test;charset=utf8mb4'
export MAGIRC_TEST_DB_USER=magirc
export MAGIRC_TEST_DB_PASSWORD=magirc
export MAGIRC_TEST_DB_PORT=3306
export MAGIRC_TEST_ISOLATED=1
composer check:isolated
```

`composer test:integration` führt nur die drei Datenbanktests aus. Ohne Isolationskennzeichen, ohne DSN oder mit einem anderen Datenbanknamen wird der Lauf mit Exitcode 2 verweigert. Auch ein direkter PHPUnit-Aufruf prüft diese Grenze. Die Tests legen ihre Tabellen selbst an und entfernen beziehungsweise überschreiben sie; sie dürfen ausschließlich gegen die ausdrücklich isolierte Testdatenbank laufen.

`composer check:isolated` erstellt zusätzlich eine temporäre Anwendungskopie, konfiguriert darin einen Testadministrator und Anope-Fixtures und startet einen PHP-Webserver ausschließlich auf `127.0.0.1`. Geprüft werden echte HTTP-Antworten für den unkonfigurierten Installer, Frontend, REST-Status/ETag/304, versteckte Channels, Admin-Login, CSRF, Sessions, Logout, Installer-Sperre und Datenbankausfall. Die Testinstanz und ihre Tabellen werden danach entfernt. Auch `composer test:installation` verwendet eine separate temporäre Anwendungskopie und greift nicht auf die Konfiguration eines vorhandenen Checkouts zu. Der PHP-Entwicklungsserver bildet die Apache-/Nginx-Sperren für statische Dateien nicht nach; diese müssen für eine produktive Bereitstellung mit dem tatsächlich verwendeten Webserver geprüft werden.

## Reproduzierbare Testdaten

`DatabaseIntegrationTest` erzeugt für Anope die Tabellen `anope_currentusage`, `anope_maxusage`, `anope_history`, `anope_server`, `anope_user`, `anope_chan`, `anope_ison`, `anope_maxusers` und `anope_chanstats`. Die Anope-Fixtures enthalten einen sichtbaren und einen eingeschränkten Channel, einen versteckten Service-User sowie aktuelle, maximale und historische Werte.

Für Denora werden die abweichenden Tabellen `denora_current`, `denora_maxvalues`, `denora_server`, `denora_user`, `denora_chan`, `denora_ison`, `denora_stats`, `denora_channelstats`, `denora_serverstats`, `denora_ustats`, `denora_cstats` und `denora_aliases` angelegt. Die Modusspalten verwenden die Denora-Namen `mode_us`, `mode_ls` und `mode_lp`.

Geprüft werden Verbindungsaufbau, JSON-Konfiguration und UTF-8-DSN, aktuelle/maximale/historische Werte, Berechtigungsantworten für sichtbare/versteckte Channels, REST-Statusantworten und getrennte Anope-/Denora-Tabellenlayouts. Die DataTables-Identifier, Filter und Pagination werden zusätzlich in den SQLite-Regressionsprüfungen gegen die produktive `DB`-Schicht validiert.

`InstallationIntegrationTest` legt das MagIRC-Schema in einer leeren Datenbank an, speichert eine neue JSON-Konfiguration, erzeugt einen gehashten Administrator mit `.installed`-Marker und führt anschließend eine Migration von Datenbankversion 18 auf 19 aus. Vorhandener Administrator und Begrüßungstext werden dabei verifiziert.

## CI

`.github/workflows/ci.yml` ist nur manuell über `workflow_dispatch` startbar. Es installiert die Lockfile-Abhängigkeiten, baut die Assets und ruft `composer check:isolated` für PHP 8.4 und 8.5 mit einem MariaDB-11.4-Service auf. Die lokale Prüfung verwendet denselben nativen Befehl. Die bestehende GitHub-Richtlinie `allowed_actions: local_only` verhindert derzeit die optionalen externen Setup-Actions; sie wird durch diesen Auftrag nicht geändert.

Der native Aufruf wurde lokal mit PHP 8.5.10 und MariaDB 11.4 ausgeführt.
PHP 8.4.26 wurde in einem temporären Container gegen dieselbe isolierte
MariaDB getestet, einschließlich HTTP, Regressionen und statischer PHP-Checks.
MySQL 8.4 wurde zusätzlich mit den Datenbank- und HTTP-Fixtures geprüft.
Dies sind lokale Ergebnisse; ein grüner GitHub-Actions-Lauf liegt nicht vor.
