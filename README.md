# KGV24 WordPress Plugin

KGV24 verbindet eine WordPress-Webseite mit der API von
[Kleingartenverein24](https://kleingartenverein24.de). Vereine können damit
Inhalte aus ihrer KGV24-Verwaltung automatisch auf der eigenen Webseite
anzeigen.

Die erste Funktion zeigt freie beziehungsweise nicht gepachtete Gärten per
Shortcode auf einer WordPress-Seite an.

## Funktionen

- Admin-Menü `KGV24` in WordPress
- Speichern der KGV24 API-URL
- Speichern eines tenant-gebundenen API-Keys
- Verbindungstest gegen `/api/tenant/session`
- Shortcode `[kgv-garten]` für freie Gärten
- Responsives Kartenlayout für die Ausgabe im Frontend

## Voraussetzungen

- WordPress 6.0 oder neuer
- PHP 7.4 oder neuer
- Ein aktiver Kleingartenverein24-Zugang mit API-Zugriff
- Ein tenant-gebundener API-Key aus den KGV24-Einstellungen

## Installation

1. Dieses Repository als Plugin-Ordner in WordPress ablegen:

   ```text
   wp-content/plugins/kgv24/
   ```

2. Im WordPress-Adminbereich unter `Plugins` das Plugin `KGV24` aktivieren.
3. Danach erscheint links im Admin-Menü der Punkt `KGV24`.

## API-Key Einrichten

1. In Kleingartenverein24 in die Einstellungen wechseln.
2. Dort einen API-Key erstellen oder erneuern.
3. Den vollständigen Key direkt kopieren.
4. In WordPress `KGV24` öffnen.
5. API-URL prüfen:

   ```text
   https://kleingartenverein24.de
   ```

6. Den API-Key eintragen und speichern.
7. `Authentifizierung testen` ausführen.

Der API-Key wird als Bearer-Token gesendet:

```http
Authorization: Bearer kgv_live_...
```

Ein separater `X-Tenant-Slug` ist für diese Keys nicht notwendig. Die API löst
den Tenant direkt aus dem Key auf.

Wichtig: Der vollständige Key wird in Kleingartenverein24 nur direkt nach der
Erstellung oder Erneuerung angezeigt. Danach ist nur noch eine Vorschau sichtbar.

## Shortcode Verwenden

Den Shortcode in eine Seite, einen Beitrag oder einen kompatiblen Block einfügen:

```text
[kgv-garten]
```

Das Plugin ruft `/api/tenant/plots` ab und zeigt die als frei erkannten Gärten
als Karten an.

### Optionen

Maximal sechs Gärten anzeigen:

```text
[kgv-garten limit="6"]
```

Auch Gärten anzeigen, bei denen die API noch kein eindeutiges Frei-/Verpachtet-
Feld liefert:

```text
[kgv-garten show_unknown="1"]
```

Beide Optionen kombinieren:

```text
[kgv-garten limit="6" show_unknown="1"]
```

## Darstellung

Die Karten werden über `assets/css/public.css` gestylt. Das Layout ist
responsive und passt sich automatisch an schmale und breite Inhaltsbereiche an.

Angezeigt werden aktuell, sofern von der API geliefert:

- Gartennummer oder Name
- Lage beziehungsweise Weg
- Größe in Quadratmetern
- Status-Badge `Frei`

## Authentifizierung Und Zugriff

Die API prüft den API-Key serverseitig. Wenn der Key ungültig ist oder der
API-Zugriff durch Abo- oder Trial-Status blockiert wird, zeigt das Plugin eine
Fehlermeldung im Admin-Test oder im Shortcode-Bereich an.

## Entwicklung

Das Plugin ist bewusst ohne Build-Schritt aufgebaut. Die zentrale Struktur:

```text
kgv24.php
includes/class-kgv24-plugin.php
includes/class-kgv24-api-client.php
includes/class-kgv24-settings.php
includes/class-kgv24-shortcodes.php
assets/css/public.css
```

PHP-Syntax prüfen:

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n 1 php -l
```

## WordPress.org Plugin Directory

Für das spätere WordPress.org-Plugin-Directory gibt es zusätzlich zur
GitHub-README die Datei `readme.txt`. Diese Datei steuert die öffentliche
Plugin-Seite auf WordPress.org.

Vor einer Einreichung prüfen:

- `readme.txt` im offiziellen Validator testen:
  https://wordpress.org/plugins/developers/readme-validator/
- `Contributors:` auf echte WordPress.org-Benutzernamen setzen
- `Stable tag:` mit der Plugin-Version in `kgv24.php` synchron halten
- Release im WordPress.org-SVN nach Freigabe unter `tags/0.1.0/` ablegen
- Echte Screenshots als `screenshot-1.png`, `screenshot-2.png` ergänzen
- Banner/Icon-Assets für WordPress.org vorbereiten
- Datenschutz-/Service-Hinweise zu `https://kleingartenverein24.de` final mit
  den echten AGB- und Datenschutz-Links ergänzen

## Roadmap

- Exaktes API-Feld für freie Gärten final anbinden, sobald es stabil
  dokumentiert ist
- Weitere Shortcodes für KGV24-Inhalte
- Optionale Anzeige-Einstellungen im WordPress-Admin
- Lokalisierung mit `.pot`-Datei
- Release-Paket für einfache Plugin-Installation

## Sicherheit

- API-Keys werden in den WordPress-Optionen gespeichert.
- Der gespeicherte Key wird im Adminformular nicht wieder im Klartext angezeigt.
- Frontend-Ausgaben werden escaped.
- API-Requests laufen über WordPress HTTP APIs.

## Lizenz

Noch nicht festgelegt.
