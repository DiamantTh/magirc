# Sicherheitsbetrieb

MagIRC liest bestehende PHP-Konfigurationen nur mit einem Token-Parser. Neue
Konfigurationen werden als JSON mit restriktiven Dateirechten geschrieben.
Datei- und Themepfade werden auf bekannte Verzeichnisse begrenzt; IRCd-Dateien
werden ausschließlich aus der festen `lib/magirc/ircds`-Allowlist geladen.
Webserver-Beispiele und `.htaccess` sperren Konfigurationen, Quelltext,
Templates, Logs, Cache, Abhängigkeiten, Tests und Repository-Metadaten.

Werte aus der bestehenden Konfiguration werden vor ihrer Verwendung in URL- und
JavaScript-Kontexten normalisiert. Ungültige HTTP-URLs, Themes, Ports,
Zeitintervalle und Betriebsmodi werden verworfen oder auf sichere Werte gesetzt;
`javascript:`-Webchat-Ziele werden nicht ausgegeben. Setup-Formulare verwenden
Twig-HTML-Escaping. Dynamische IRC-, Länder- und Statistikwerte werden vor der
Einfügung in HTML-Attribute, Tabellen und Diagrammtooltips escaped.

Administrationsaktionen sind POST-Routen mit serverseitigem Slim-CSRF-Schutz.
Installer-POSTs verwenden ebenfalls serverseitige Tokens. Der Installer setzt
eine Installationssperre, verwendet einen exklusiven Lock für die erste
Administratoranlage und wird nach erfolgreicher Anlage durch `.installed`
deaktiviert. Eine unvollständige Einrichtung bleibt über `.setup_pending`
wiederaufnehmbar; `MAGIRC_ALLOW_SETUP=1` ist nur als ausdrücklicher lokaler
Wiederherstellungsweg vorgesehen.

Öffentliche REST-Statistiken werden nur ohne eingehende Session-ID öffentlich
zwischengespeichert. REST-Anfragen mit einem vorhandenen Session-Cookie laden
die serverseitige Session vor der Cache-Entscheidung und erhalten
`private, no-store`; ETags und HTTP 304 gelten damit nicht für angemeldete
Anfragen.

Administratorpasswörter werden mit Argon2id (oder dem sicheren PHP-Fallback)
gespeichert. Alte MD5-Werte werden nur nach erfolgreicher Anmeldung einmalig
migriert. Lange Passphrasen bis 4096 Bytes werden unterstützt; neue
Administratorpasswörter benötigen mindestens 12 Bytes. Moderne Hashes werden
bei der Anmeldung mit `password_needs_rehash()` aktualisiert. Fehlende Konten
verwenden eine Dummy-Verifikation und Loginfehler werden pro IP und Benutzer
im privaten Runtime-Verzeichnis gedrosselt.

Sessions sind strikt, cookie-only und HttpOnly. Cookies verwenden `SameSite=Lax`
und werden bei HTTPS als `Secure` markiert. Nach der Anmeldung wird die
Session-ID erneuert. Inaktivität (30 Minuten) und absolute Lebensdauer (8
Stunden) werden geprüft; Logout leert die serverseitige Session und löscht das
Cookie. Der Runtime-Session-, Log- und Cache-Speicher muss außerhalb des
öffentlichen Document-Roots liegen und mit 0700/0600 geschützt sein.

Slim-Antworten und die prozeduralen Einstiegspunkte setzen `nosniff`,
`SAMEORIGIN`, Referrer- und Permissions-Policy sowie eine CSP. Die vorhandenen
Legacy-Templates benötigen für ihre Inline-Skripte weiterhin
`'unsafe-inline'`; ein nonce-basierter CSP-Ausbau ist ein späterer Schritt.
Produktivbetrieb muss über HTTPS mit den mitgelieferten Apache- oder
Nginx-Regeln erfolgen.

Die HTML-Bereinigung erlaubt bei `href` und `src` nur HTTP(S), `mailto:` und
relative Verweise. Datei-, protokoll-relative und sonstige URI-Schemata werden
entfernt.

Noch nicht implementiert ist WebAuthn/FIDO2. Es gibt keine integrierte
Mehrfaktor- oder externe Authentifizierungsdienst-Abhängigkeit. Die vorhandene
IP-Bindung der Administrationssession kann bei wechselnden Mobilnetzen eine
erneute Anmeldung erfordern.
