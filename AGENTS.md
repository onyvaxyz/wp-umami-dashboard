# WP Umami Dashboard – Architektur-Guide für KI

## Übersicht
WordPress-Plugin das Umami Analytics Statistiken via API im WP-Dashboard anzeigt.
Modulares Chart-System: Jeder Chart ist eine eigenständige JS-Datei.

## Dateistruktur

### PHP (Backend)
| Datei | Zweck | Wann bearbeiten |
|---|---|---|
| `wp-umami-dashboard.php` | Bootstrap, Konstanten, Requires | Neue Klasse hinzufügen, Version ändern |
| `includes/class-plugin.php` | Hook-Registrierung, Asset-Loading | Neuen Hook registrieren, Asset-Handling ändern |
| `includes/class-api-client.php` | Umami API-Kommunikation (Login, Stats) | API-Endpunkte ändern/hinzufügen |
| `includes/class-settings.php` | Settings-Page, Felder, Sanitization | Neue Einstellung hinzufügen |
| `includes/class-analytics-page.php` | Analytics-Menüseite, AJAX-Handler | Seitenstruktur/AJAX ändern |
| `includes/class-dashboard-widget.php` | WP-Dashboard-Widget | Widget-Anzeige ändern |
| `includes/class-encryption.php` | AES-256 Passwort-Verschlüsselung | Verschlüsselungslogik ändern |
| `includes/class-permissions.php` | Rollenbasierte Zugriffskontrolle | Berechtigungslogik ändern |

### JavaScript (Frontend)
| Datei | Zweck | Wann bearbeiten |
|---|---|---|
| `assets/js/helpers.js` | Utilities: escapeHtml, Farben, Icons, Flags | Neue Helper-Funktion, neues Land/Icon |
| `assets/js/core.js` | Registry, AJAX, Range-Selector, Metriken-Header | Neuen Zeitraum, Metriken-Anzeige ändern |
| `assets/js/charts/*.js` | Einzelne Chart-Module (je ~80-120 Zeilen) | Spezifischen Chart anpassen |

### CSS
| Datei | Zweck |
|---|---|
| `assets/css/widget.css` | Dashboard-Widget Styles |
| `assets/css/analytics.css` | Analytics-Seite Styles |

Dynamische Farben werden über CSS Custom Properties gesetzt:
`--umami-gradient-start`, `--umami-gradient-end`, `--umami-button-text-color`, `--umami-shadow-color`, `--umami-shadow-hover`

## Chart-Module (Registry-Pattern)

Jedes Chart-Modul in `assets/js/charts/` registriert sich automatisch:

```js
UmamiCharts.register({
    id: 'mein-chart',       // Eindeutige ID
    position: 35,           // Sortierung (10-80 belegt)
    layout: 'left',         // 'full', 'left', oder 'right'
    dataKey: 'apiKey',      // Key aus der API-Response
    render: function(data, charts) { return '<html>'; },
    initInteractions: function(data, charts) { /* Chart.js erstellen */ }
});
```

**Neuen Chart hinzufügen:** Neue Datei in `assets/js/charts/` erstellen – wird automatisch entdeckt via PHP glob().
**Chart entfernen:** Datei löschen.

### Aktuelle Chart-Positionen
| Position | ID | Layout | Beschreibung |
|---|---|---|---|
| 10 | timeline | full | Besucher/Aufrufe Linien-Chart |
| 20 | devices | full | Geräte-Cards |
| 30 | sources | left | Quellen (Referrer) |
| 40 | browsers | left | Browser |
| 50 | pages | right | Top-Seiten |
| 60 | countries | left | Länder |
| 70 | os | right | Betriebssysteme |
| 80 | events | full | Events |

Der Footer ist **kein Chart-Modul**, sondern wird statisch via PHP in `class-analytics-page.php` gerendert (wie der Header). Einstellungen: Logo, Text, Button-URL in den Settings.

### Layout-Reihenfolge
1. Metriken-Header (in core.js, immer sichtbar)
2. Full-width Module mit position < 25
3. Zwei-Spalten-Layout (left + right Module)
4. Full-width Module mit position >= 75

## Konventionen
- **PHP:** WordPress Coding Standards, Tabs, Klassen-Prefix `Jejeresources_Umami_`
- **JS:** IIFE-Pattern `(function($) { ... })(jQuery);`, `UmamiHelpers` für Utilities
- **CSS:** Alle Klassen mit `umami-` Prefix, CSS Custom Properties für Farben
- **Sicherheit:** ABSPATH-Check, Nonce, `current_user_can()`, `esc_*` Funktionen, Passwort verschlüsselt

## Datenfluss
1. User öffnet Analytics-Seite → `class-analytics-page.php` rendert HTML
2. `core.js` macht AJAX-Call → `ajax_get_stats()` → `class-api-client.php`
3. API-Client holt Token (gecacht via Transient) und Daten von Umami
4. Response wird an `core.js` zurückgegeben
5. `core.js` ruft `render()` auf jedem registrierten Chart-Modul auf
6. Danach `initInteractions()` für Chart.js-Initialisierung
