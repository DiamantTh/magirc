# Umbau 3/3: Logging, Cache und Editor

## Logging

`LoggerFactory` stellt einen gemeinsamen Monolog-3-Logger als `Psr\Log\LoggerInterface` bereit. Slim-, Admin-, REST-, PDO-, Installer- und Statistikfehler werden mit sicheren Ereignistexten protokolliert. `SecretRedactionProcessor` entfernt Kennwort-, Token-, Session- und DSN-Werte aus Nachrichten und Kontexten. Besucher erhalten weiterhin nur generische Fehlerantworten.

## Statistik-Cache

`phpfastcache/phpfastcache` 9 wird über `Psr16Adapter` mit dem lokalen `Files`-Treiber verwendet. Der Pfad ist über `cache_path` beziehungsweise `MAGIRC_CACHE_PATH` (bei Bedarf direkt im Konfigurationsarray) anpassbar; ohne Serverdienst funktioniert die Standardinstallation in `tmp/cache`. `cache_current_ttl` und `cache_history_ttl` beziehungsweise die Umgebungsvariablen `MAGIRC_CACHE_CURRENT_TTL` und `MAGIRC_CACHE_HISTORY_TTL` steuern die Laufzeiten.

Schlüssel enthalten Datenquelle, Datenbankidentität ohne Passwort, Abfragetyp, Parameter und Zugriffsumfang. Kanal- und Serverabfragen erhalten eigene Hashbereiche. Cache-Lese-, Schreib- und Löschfehler werden geloggt und als Miss behandelt. `psr/cache` ist nur als Abhängigkeit des verwendeten PSR-16-Adapters vorhanden; ein eigener PSR-6-Pool wird nicht benötigt.

## HTTP-Cache

`PublicStatisticsCacheMiddleware` setzt für eine begrenzte Liste öffentlicher GET-Statistikpfade `Cache-Control`, `ETag` und `Vary` und beantwortet passende `If-None-Match`-Anfragen mit 304. Autorisierte Anfragen, Sessions, Fehler, Admin-, Login- und Installerpfade werden nicht öffentlich gecacht. Geschützte Channelrouten bleiben außerhalb der öffentlichen Liste und führen ihre Berechtigungsprüfung vor der Antwort aus. `slim/http-cache` ist für dieses Projekt nicht erforderlich, weil die eigene PSR-15-Middleware die Pfad- und Berechtigungsgrenzen explizit abbildet.

## Tiptap und HTML

CKEditor 4 und sein Composer-Paket wurden entfernt. Tiptap Core 3 und StarterKit 3 werden mit esbuild lokal als `admin/js/welcome-editor.bundle.js` gebündelt; es wird kein Frontend-Framework eingeführt. Das Admin-Template behält die drei Begrüßungsoptionen und den gespeicherten HTML-Inhalt. `HtmlSanitizer` verarbeitet beim Laden und Speichern auch vorhandene Inhalte, erlaubt nur definierte Elemente/Attribute und entfernt Skripte, Eventattribute sowie unsichere URL-Schemata.

## Tests und Grenzen

Die PHPUnit-Suite enthält Tests für Cache-Hits/Invalidierung und Scopes, ETags/304, Geheimnisredaktion und HTML-Bereinigung. `composer test`, `composer cs`, `composer analyse`, `composer rector:check`, `composer audit`, `composer check-platform-reqs` und `yarn install --frozen-lockfile` laufen unter PHP 8.5 erfolgreich. PHP 8.4 ist als Composer-Plattform festgelegt, konnte in dieser Umgebung jedoch nicht zusätzlich ausgeführt werden.

Die Statistikdienste verwenden weiterhin ihre bestehende Datenbankabstraktion; eine aktive Cache-Invalidierung bei externen Datenbankänderungen ist nicht möglich und erfolgt über die konfigurierten TTLs. Für umfangreiche Rich-Text-Funktionen über StarterKit hinaus müssen erlaubte Tiptap-Erweiterungen und die HTML-Allowlist gemeinsam erweitert werden.
