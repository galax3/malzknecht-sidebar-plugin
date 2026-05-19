=== Malzknecht Post-Sidebar ===
Author: Malzknecht
Version: 0.3.0
Requires at least: WordPress 6.0
Requires PHP: 7.4
License: GPL-2.0-or-later

Dynamisches Sidebar-Modul pro Beitrag.

== Beschreibung ==

Pro Beitrag kann optional ein Sidebar-Modul hinterlegt werden:
* Auswahl eines wiederverwendbaren Blocks (Reusable / Synced Block)
* ODER freies HTML mit Shortcode-Support

Wenn nichts gepflegt ist, erscheint nichts. Das Modul ist standardmaessig sticky
und scrollt mit dem Beitrag mit (Astra-kompatibel, deaktiviert sich auf Mobile).

== Installation ==

1. ZIP unter "Plugins -> Installieren -> Plugin hochladen" hochladen und aktivieren.
2. Unter "Design -> Widgets" (oder Customizer -> Widgets) das Widget
   "Malzknecht Post-Sidebar" in die Main-Sidebar ziehen.
   Alternativ: Shortcode [mps_post_sidebar] an beliebiger Stelle einfuegen.
3. Im Beitrags-Editor erscheint rechts die Meta-Box "Sidebar-Modul (dynamisch)".
   Entweder einen wiederverwendbaren Block waehlen oder freies HTML eintragen.

== Hooks / Filter ==

* `mps_supported_post_types` — Liste der Post-Types, fuer die die Meta-Box
  erscheint. Default: ['post']. Beispiel:
  add_filter( 'mps_supported_post_types', function( $pts ) {
      $pts[] = 'page';
      return $pts;
  } );

== Sticky-Verhalten anpassen ==

Im Custom-CSS:

.mps-post-sidebar {
    --mps-sticky-top: 120px;
}

== Changelog ==

= 0.3.0 =
* Eigener Custom-Post-Type "Sidebar-Module" mit vollem Block-Editor. Eigener Menuepunkt in WP-Admin. Pro Beitrag im Meta-Box auswaehlbar.
* Reusable Block und freies HTML bleiben als Fallback bestehen.
* Priorisierung: Sidebar-Modul > Synced Pattern > freies HTML.

= 0.2.3 =
* Hotfix: style.css enthielt wegen eines Truncation-Bugs in v0.2.1 nicht die :has()-basierten overflow- und sidebar-main-Overrides. Sticky greift erst jetzt wirklich.

= 0.2.2 =
* Updater-Cache wird beim Plugin-Aktivieren, beim WP-Update-Process und beim Force-Check-Klick invalidiert. Verhindert das Festkleben einer alten 404-Antwort, wenn z.B. das Repo von privat auf public umgestellt wurde.
* Neuer "GitHub-Update pruefen"-Link in der Plugins-Liste (manuelles Force-Refresh).

= 0.2.1 =
* Sticky-Fix fuer Astra: sticky greift jetzt am aside.widg