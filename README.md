# Malzknecht Post-Sidebar

Kleines WordPress-Plugin für [malzknecht.de](https://malzknecht.de). Erlaubt pro Beitrag ein optionales Sidebar-Modul, das sticky mitscrollt. Wer nichts hinterlegt, sieht nichts.

## Funktionen

- Meta-Box im Post-Editor mit zwei Modi: wiederverwendbarer Block (Reusable / Synced) **oder** freies HTML / Shortcode.
- Auto-Render via Widget "Malzknecht Post-Sidebar" oder Shortcode `[mps_post_sidebar]`.
- Eigener Sticky-Wrapper (`position: sticky`), Mobile-Breakpoint automatisch.
- Kompatibel mit Astra & Astra Pro Sticky Sidebar.
- Per Filter `mps_supported_post_types` auf weitere Post-Types erweiterbar.

## Installation

1. ZIP unter **Plugins → Installieren → Plugin hochladen** hochladen, aktivieren.
2. **Design → Widgets**: Widget "Malzknecht Post-Sidebar" in die Main-Sidebar ziehen.
3. Im Beitrags-Editor: Meta-Box "Sidebar-Modul (dynamisch)" füllen (oder leer lassen).

## Dev

- Single-file Plugin (`malzknecht-post-sidebar.php`), eine CSS-Datei (`assets/style.css`).
- ZIP-Build: `zip -r malzknecht-post-sidebar.zip malzknecht-post-sidebar -x "*/.DS_Store"` aus dem Parent-Verzeichnis.

## Lizenz

GPL-2.0-or-later
