# Betrieb, Installation und Updates

## Verzeichnisstruktur

Der einzige öffentliche DocumentRoot ist `httpdocs/`. Er enthält die vier
HTTP-Einstiegspunkte für Statistikseiten, Administration, REST und Setup sowie
Browser-Assets unter `httpdocs/assets/`. Stelle Apache/Nginx auf genau dieses
Verzeichnis ein; die Beispielkonfigurationen verwenden denselben Root. Die
Beispiele erwarten TLS-Zertifikate unter `/etc/letsencrypt/live/magirc.example.test/`;
ersetze Servernamen, Zertifikatspfade, Projektpfad und PHP-FPM-Socket passend
zur Installation. Apache benötigt `ssl`, `rewrite`, `proxy` und `proxy_fcgi`.

`src/MagIRC/`, `vendor/`, `conf/`, `tmp/`, `locale/`, `resources/`, `templates/`
und `themes/` bleiben privat. `conf/` enthält Datenbankzugänge und
Installationsmarker, `tmp/` enthält Twig- und Statistikcache sowie Logs. Beide
Verzeichnisse müssen für den PHP-FPM/Apache-Benutzer schreibbar sein
(empfohlen `0700`).

## Erstinstallation

Ein fertiges Releasepaket enthält produktive Composer-Abhängigkeiten und die
gebauten Browser-Assets. Dessen Zielserver braucht weder Composer noch Node.js,
Yarn oder Docker. Für einen Git-Checkout oder ein Quellarchiv müssen Composer
und Yarn auf einem Build-Rechner ausgeführt werden:

1. PHP 8.4 oder 8.5 mit `pdo_mysql`, `gettext`, `mbstring`, `dom` und `xml`
   bereitstellen.
2. Abhängigkeiten reproduzierbar installieren:

   ```sh
   composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
   yarn install --frozen-lockfile --production=false
   yarn build:editor
   yarn build:runtime
   rm -rf node_modules
   ```

   `yarn build:runtime` kopiert nur die im Browser benötigten Bibliotheken
   nach `httpdocs/assets/vendor/`. Node.js, Yarn und die Entwicklungsquellen werden im
   laufenden Betrieb nicht benötigt.
3. `conf/` und `tmp/` anlegen und dem PHP-Benutzer Schreibrechte geben.
4. `/setup/` aufrufen. Der Installer prüft die Datenbank, legt das MagIRC-
   Schema an und schreibt neue Zugangsdaten als validierte JSON-Datei.
5. Im letzten Schritt genau einen Administrator anlegen. Danach wird
   `conf/.installed` mit Modus `0600` erstellt und der Installer verweigert
   weitere Aufrufe.

Die Schritte 2 bis 5 dürfen nicht mit produktiven Datenbankzugängen in einer
öffentlich zugänglichen Entwicklungsumgebung ausgeführt werden. Datenbank-
Benutzer benötigen nur die für das jeweilige Schema erforderlichen Rechte.

## Update vorhandener Installationen

1. Wartungsfenster ankündigen und `conf/`, `tmp/` sowie die MagIRC-Datenbank
   sichern. Eigene Theme-Dateien ebenfalls sichern. Die Anope-/Denora-
   Datenbanken werden nicht vom MagIRC-Installer verändert.
2. Den vollständigen neuen Projektbaum bereitstellen. Stelle den Webserver-
   DocumentRoot von der bisherigen Projektwurzel auf `httpdocs/` um; alte
   Root-Einstiegspunkte wie `index.php` und Verzeichnisse wie `admin/` oder
   `setup/` gibt es nicht mehr. Die öffentlichen URLs `/`, `/admin/`,
   `/rest/service.php` und `/setup/` bleiben unter dem neuen Webroot bestehen.
3. `conf/*.json`, `conf/*.cfg.php`, `conf/.installed` und `tmp/` erhalten.
   Eigene Theme-Templates kommen nach `themes/<name>/templates/`; zugehörige
   Browser-Assets kommen nach `httpdocs/assets/themes/<name>/`.
4. `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
   und anschließend `yarn install --frozen-lockfile --production=false`,
   `yarn build:editor` und `yarn build:runtime` ausführen.
5. `/setup/?step=2` einmal aufrufen. Der Installer erkennt das vorhandene
   Schema und führt die versionierten Migrationen bis `DB_VERSION` aus.
   Vorhandene Begrüßungstexte, Administratoren, Themes und Katalogdateien
   bleiben erhalten. Alte `*.cfg.php`-Konfigurationen werden nur als Daten
   gelesen; PHP-Code darin wird nicht ausgeführt.
6. Nach der Prüfung `tmp/`-Caches leeren. `node_modules/` und `vendor/` liegen
   außerhalb des Document-Roots. `/setup/` ist bei gesetztem `.installed` nicht erreichbar.

Wenn ein Update mit einer unvollständigen Datenbankverbindung abbricht, bleibt
die Administration gesperrt. Die Ursache steht ohne Zugangsdaten in
`tmp/magirc.log` beziehungsweise im PHP-Error-Log. Nach Behebung der
Verbindung kann der Schritt wiederholt werden.

## Cache, Logging und Datenbankzugänge

Der Standardcache liegt unter `tmp/cache`; `MAGIRC_CACHE_PATH` oder die
`cache_path`-Konfiguration kann auf einen anderen nicht öffentlichen Pfad
zeigen. `MAGIRC_CACHE_CURRENT_TTL` und `MAGIRC_CACHE_HISTORY_TTL` steuern die
aktuellen beziehungsweise historischen Statistikdaten. Ein nicht verfügbarer
Dateicache fällt auf einen Prozesscache zurück und legt die Statistikabfragen
nicht dauerhaft lahm.

Warnungen und Fehler werden nach `tmp/magirc.log` geschrieben. Der
Secret-Redaction-Prozessor entfernt Passwörter, Tokens, Session-IDs und
Datenbankkennzeichen aus Logcontext und Nachrichten. Ist `tmp/` nicht
beschreibbar, verwendet Monolog den PHP-Error-Log. Mit `MAGIRC_RUNTIME_DIR`
kann ein Verzeichnis außerhalb des Document-Roots für Log und Cache vorgegeben
werden; das Log wird mit Modus `0600` angelegt.

## Fehlerbehebung

* **"Frontend assets are not installed"**: `yarn install --frozen-lockfile
  --production=false` und `yarn build:runtime` ausführen; danach kann
  `node_modules/` wieder entfernt werden.
* **Setup zeigt "Database connection failed"**: Host, Port, TLS-Dateien und
  Rechte im JSON prüfen. Passwörter werden in Fehlermeldungen nicht ausgegeben.
* **Setup bleibt deaktiviert**: Nur ein bestätigter Administrator setzt
  `.installed`. Bei einer Datenbank ohne Administrator wird ein eventuell
  veralteter Marker entfernt und ein geschützter Setup-Pending-Zustand erzeugt.
  `MAGIRC_ALLOW_SETUP=1` ist nur für eine kontrollierte Wartung vorgesehen.
* **Leere Twig-Seiten nach dem Update**: `tmp/cache/` leeren und die PHP-FPM-
  Worker neu laden.
