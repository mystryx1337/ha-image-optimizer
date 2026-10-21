=== HA Image Optimizer ===
Contributors: mystryx1337
Donate link: https://seo-hacker.de/
Tags: image optimizer, webp, avif, compression, performance, media library
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Leistungsstarke, rein lokale Bildoptimierung für WordPress ohne externe APIs. Generiert WebP und AVIF, verwaltet Backups und bietet Vorher-Nachher-Statistiken.

== Description ==

HA Image Optimizer ist ein vollständig selbst gehostetes, werbefreies und ressourcenschonendes Bildoptimierungs-Plugin für WordPress. Es funktioniert wie Imagify, nutzt jedoch ausschließlich deine lokalen Server-Ressourcen (ImageMagick oder GD) – ohne Drittanbieter-APIs, ohne Monatsabos und zu 100% DSGVO-konform.

= Kernfunktionen =
* **Duale Kompressionsstrategie**: Originale standardmäßig verlustfrei (Lossless), Zwischengrößen smart verlustbehaftet (~82%).
* **Next-Gen Formate**: Automatische Erzeugung von WebP und AVIF für Master-Bilder und alle Thumbnails.
* **Flexible Auslieferung**: Wahlweise universelle <picture>-Tag Ersetzung (inkl. CSS image-set()) oder 0 ms PHP-Overhead via .htaccess Server-Rewrites (Apache & LiteSpeed).
* **Backup & 1-Click Restore**: Sichert Originale in einem geschützten Verzeichnis und erlaubt jederzeitige Wiederherstellung.
* **SizeGuard**: Schutz vor negativer Ersparnis (neue Dateien werden verworfen, falls sie größer als das Original wären).
* **Massenverarbeitung**: Live-AJAX-Optimierer im Dashboard und vollständige WP-CLI Unterstützung (`wp ha-optimizer`).
* **Vorher-Nachher Transparenz**: Exakte Speicherplatzersparnis in KB/MB und Prozent pro Bild und Site-weit.
* **Mehrsprachig**: Vollständig übersetzt in die 25 meistgenutzten WordPress-Sprachen inklusive RTL-Support.

== Installation ==

1. Lade den Ordner `ha-image-optimizer` in das Verzeichnis `/wp-content/plugins/` hoch.
2. Aktiviere das Plugin im WordPress-Menü unter 'Plugins'.
3. Gehe zu 'Einstellungen -> HA Image Optimizer', um Optionen anzupassen.
4. Starte die Massenoptimierung unter 'Medien -> Massenoptimierung'.

== Frequently Asked Questions ==

= Benötigt das Plugin einen API-Schlüssel? =
Nein. Das Plugin arbeitet zu 100% lokal auf deinem Webserver über PHP Imagick oder GD.

= Was passiert bei einer Deinstallation? =
Gemäß den HA-Standards werden deine Backups und WebP-Dateien standardmäßig geschützt und nicht gelöscht. In den Einstellungen gibt es einen expliziten Schalter für die vollständige Bereinigung bei Deinstallation.

== Changelog ==

= 1.0.1 =
* Neu: Schnellzugriffs-Links direkt in der Plugin-Verwaltung (wp-admin/plugins.php).
* Neu: Visueller 4-Phasen-Stepper für die Massenoptimierung (Crawlen -> Komprimieren -> WebP/AVIF -> Fertig).
* Neu: Live-Statusanzeige mit pulsierendem Indikator und Dateiname der aktuellen Datei.
* Neu: Interaktives, aufklappbares Verlaufs-Dropdown für bereits verarbeitete Bilder mit Vorher/Nachher-Größen.
* Neu: Quick-Start Banner auf der Einstellungsseite.
* Aktualisiert: Autor-Referenzen auf https://seo-hacker.de/.

= 1.0.0 =
* Erstveröffentlichung mit lokaler Imagick/GD Engine, WebP/AVIF-Generierung, .htaccess & Picture-Tag Delivery, WP-CLI und 25 Sprachen.
