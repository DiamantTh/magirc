# Build und Bereitstellung eines Releasepakets

Ein GitHub-Quellarchiv ist kein fertiges Laufzeitpaket: die aus `node_modules/`
gebauten Browserbibliotheken unter `httpdocs/assets/vendor/` sind absichtlich
nicht im Git enthalten. Für Installationen ohne Node.js, Yarn oder Composer auf
dem Zielserver wird auf einem Build-Rechner ein vollständiges Paket erstellt:

```sh
composer release:package
```

Der Build-Rechner benötigt PHP 8.4+, Composer 2, Node.js, Yarn 1, GNU tar,
gzip und `sha256sum`. Der Befehl baut aus dem aktuellen Commit in einem
temporären Arbeitsverzeichnis. Das Arbeitsverzeichnis muss sauber und alle
Quelländerungen müssen committed sein. Der Build installiert nur die
produktiven Composer-Abhängigkeiten, erzeugt Editor- und Runtime-Assets,
prüft deren Vorhandensein und entfernt anschließend `node_modules/`.

Das Archiv `dist/magirc-<version>-<commit>.tar.gz` enthält `vendor/`, den
Editor-Bundle und alle Browserbibliotheken. Eine `.sha256`-Datei liegt daneben.
Die Dateien unter `dist/` sind ignorierte Build-Ausgaben und werden nicht
committed. Es wird nichts veröffentlicht oder hochgeladen.

Auf dem Zielserver:

1. Archiv und Prüfsumme auf den Server übertragen und prüfen:

   ```sh
   sha256sum -c magirc-<version>-<commit>.tar.gz.sha256
   tar -xzf magirc-<version>-<commit>.tar.gz -C /srv/www
   ```

2. Den Webserver-DocumentRoot auf den entpackten Ordner `httpdocs/` setzen.
   `src/`, `vendor/`, `conf/`, `tmp/`, `tests/`, `templates/`, `themes/` und
   `locale/` bleiben außerhalb des Webroots.
3. Dem PHP-Benutzer Leserechte auf den Anwendungscode und Schreibrechte auf
   `conf/` und `tmp/` geben. Für beide Verzeichnisse wird `0700` empfohlen;
   neu gespeicherte Konfigurationen und Installationsmarker erhalten restriktive
   Dateirechte.
4. Setup unter `/setup/` öffnen. Für ein Upgrade die bestehenden `conf/`- und
   `tmp/`-Verzeichnisse sowie eigene Theme-Dateien nach der Anleitung in
   [operations.md](operations.md) übernehmen.

Nach dem Entpacken benötigt der Betrieb weder Node.js, Yarn, Docker noch
Composer. PHP, PHP-FPM/Apache, MySQL oder MariaDB und der konfigurierte
Webserver bleiben Laufzeitvoraussetzungen. Bei einem Git-Checkout oder einem
GitHub-Quellarchiv müssen Composer- und Frontend-Assets vor dem Einsatz auf
einem Build-Rechner erzeugt werden.
