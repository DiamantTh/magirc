# Umbau 2: PHP 8.4, Slim 4 und Twig 3

## Umgesetzt

* Composer 2 ist auf PHP `>=8.4` ausgerichtet und `composer.lock` sowie `yarn.lock` sind versioniert.
* Slim 4.15, Slim PSR-7 1.8, Slim Twig-View 3.4, Slim-CSRF 1.5, PHP-DI 7 und Monolog 3 sind installiert.
* `src/MagIRC` enthält PSR-4-Komponenten für Routing, Twig-Erweiterungen, Locale-Auflösung, CSRF und Logging. Slim verwendet PSR-7-Nachrichten, PSR-15-Middleware, PSR-17-Factories und einen PSR-11-Container.
* Web- und REST-Routen liegen zentral in `src/MagIRC/Routes`; Theme-Verzeichnisse enthalten keine Anwendungsrouten mehr.
* Twig 3 rendert die getrackten Theme-, Admin- und Setup-Templates. Die alten Twig-I18n- und Aptoma-Markdown-Erweiterungen wurden durch native PHP-gettext- und Michelf-Markdown-Extensions ersetzt.
* PDO verwendet Exceptions, native Prepared Statements, utf8mb4 und nicht persistente Verbindungen. Anope und Denora behalten ihre getrennten Tabellenkonfigurationen.
* Rector, PHPStan, PHPUnit und PHPCS sind eingerichtet. Die PHPUnit-Suite prüft Routing, JSON-REST-Antworten, Twig/Markdown, Locale-Fallback, PDO und beide Statistikdienste; die Sicherheitsregressionen laufen weiterhin separat.

## Bekannte Restarbeiten

* Die historischen globalen Domainklassen unter `lib/magirc` werden für bestehende Installationen weiterhin über Composer-Classmap geladen. Neue HTTP-/Infrastrukturklassen sind PSR-4; eine vollständige Namespace-Migration der Anope-/Denora-Objekte ist wegen der unterschiedlichen Legacy-Klassen und Tabellenverträge als eigener Schritt offen.
* Dieses Umbauziel implementiert noch kein gemeinsames PSR-16/PSR-6-Statistikcache und keine öffentliche HTTP-Cache-Schicht. Das bleibt für einen separaten Schritt, damit administrative und zugriffsbeschränkte Antworten nicht versehentlich gecacht werden.
* Im synchronisierten Upstream sind keine PO-/MO-Dateien getrackt. Die native Gettext-Integration nutzt weiterhin `locale/<locale>/LC_MESSAGES/messages.{po,mo}`; bis Kataloge bereitgestellt werden, fällt sie auf den englischen Msgid zurück.
* Im Arbeitscontainer steht nur PHP 8.5 zur Verfügung. PHP 8.4 wurde über Composer als Plattformziel aufgelöst, konnte aber nicht separat ausgeführt werden.
* Der frühere CKEditor-4-Hinweis ist durch den Tiptap-Umbau in `modernization-3.md` überholt; CKEditor ist aus Composer und Admin-Template entfernt.
* Nicht getrackte lokale Theme-Dateien wurden nicht verändert. Eigene Routen aus früheren `theme/*/slim/customRoutes.inc.php` müssen als registrierte Anwendungskomponente nach `src/MagIRC/Routes` übernommen werden.
