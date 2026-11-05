# Theme Variants – Project Context

## Identität
- **Name:** Theme Variants
- **Codename / Dateiname:** `themevariants` (Präfix: `themevariants_`)
- **Version:** 1.0.0
- **Autor:** pand0rica
- **Kurzbeschreibung:** Pro Theme eigene Template-Varianten – bestehende Templates ersetzen
  oder zusätzliche „Slots" (z.B. eine Header-Tabelle) füllen, mit Vererbung über die
  Theme-Eltern-Kette und frei wählbarer Seitenliste. Ohne PHP in Templates.
- **MyBB:** 1.8.x (verifiziert gegen 1.8.39) · **PHP:** 7.4 + 8.x

## Zweck & Zielgruppe
Foren mit mehreren Designs, die sich **ein** Template-Set teilen (Master-Theme + Child-Themes,
die nur Stylesheets besitzen). Bisher brauchte jede Markup-Abweichung pro Design entweder ein
eigenes Template-Set oder PHP-im-Template (`if($theme['tid'] == 34) …`). Theme Variants legt im
ACP fest: „Theme X nutzt für Template/Slot Y die Variante Z auf den Seiten …". Child-Themes
erben die Zuordnungen ihres Eltern-Themes und können sie überschreiben oder abschalten.

Erster Anwendungsfall (H4W, Testforum): Master „ICW Claude" (tid 38, Template-Set 32),
Childs „ICW Nacht (Probe)" (39) und „ICW Always" (40). ICW Always bekommt die Header-Tabelle des
alten Designs „Always" als Slot `headertabelle`. Später: eigene `forumbit_*`-, Top-Nav-,
Footer-Varianten pro Design.

## Kernkonzept

Zwei Zuordnungs-Typen, beide gespeichert pro Theme:

| Typ | Ziel (`target`) | Wirkung |
|---|---|---|
| `override` | Titel eines bestehenden Templates (z.B. `footer`, `forumbit_depth2_forum`) | Zur Laufzeit wird `$templates->cache[target]` durch den Inhalt des Varianten-Templates ersetzt. Jeder spätere `$templates->get(target)` liefert die Variante. |
| `slot` | Slot-Name (z.B. `headertabelle`) | Ein Template enthält den Platzhalter `<!--themevariants:headertabelle-->`. Beim Ausgeben der Seite wird er durch das gerenderte Varianten-Template ersetzt (oder durch nichts, wenn keine Zuordnung greift). |

- **Varianten sind normale MyBB-Templates** im Template-Set des Themes (oder global, sid -1).
  Das Plugin legt **keine** Varianten-Templates an und bearbeitet keine – Pflege im
  MyBB-Template-Editor bzw. per Repo-Sync. Das Plugin speichert nur die Zuordnung.
  Empfohlene Namenskonvention (nicht erzwungen): `tv_<ziel>_<design>`, z.B.
  `tv_headertabelle_always`, `tv_footer_always`.
- **Vererbung:** Effektive Zuordnung für Theme T und (type, target) = eigene Zeile von T, sonst
  die des nächsten Vorfahren (Theme-`pid`-Kette bis zur Wurzel). Eine Zeile mit **leerer
  Variante** bedeutet „hier keine Variante" und stoppt die Vererbung (Child schaltet eine
  geerbte Header-Tabelle ab bzw. nutzt wieder das Original-Template).
- **Seitenliste (`scripts`):** leer = alle Seiten; sonst kommagetrennte Skriptnamen
  (`index.php,portal.php`), verglichen mit `THIS_SCRIPT`. Greift die effektive Zuordnung auf
  der aktuellen Seite nicht, gilt für diese Seite **keine** Variante (es wird NICHT auf den
  Vorfahren zurückgefallen – die effektive Zeile entscheidet allein).
- **Inaktive Zeilen** (`active = 0`) werden bei der Auflösung ignoriert, als gäbe es sie nicht
  (dann greift ggf. der Vorfahr).

## Architektur
- **Variante:** prozedural, eine Hauptdatei + ACP-Modul (Begründung: eine Tabelle, zwei
  Laufzeit-Hooks, eine ACP-Seite; Kernlogik ~300 Zeilen).
- **Dateilayout:**
  ```
  inc/plugins/themevariants.php                    # Lifecycle, Hooks, Auflösung, Rendering, Cache
  admin/modules/style/themevariants.php            # ACP-Modul unter „Templates & Style"
  inc/languages/english/admin/themevariants.lang.php
  inc/languages/deutsch_du/admin/themevariants.lang.php
  tests/cli-plugin.php                             # Lifecycle-Test (install→uninstall→install)
  tests/cli-selftest.php                           # Auflösungs-Selbsttest (reine Funktion)
  ```
  Keine Frontend-Sprachdateien (das Plugin gibt im Frontend keinen Text aus), keine eigenen
  Templates, kein Stylesheet.

## Datenbank-Tabellen
| Tabelle (`TABLE_PREFIX .`) | Zweck | Wichtige Spalten |
|---|---|---|
| `themevariants` | Zuordnungen Theme → Variante | `vid INT(11) AUTO_INCREMENT PK`, `tid INT(11) NOT NULL`, `type VARCHAR(10) NOT NULL` (`override`\|`slot`), `target VARCHAR(120) NOT NULL`, `variant VARCHAR(120) NOT NULL DEFAULT ''` (leer = keine Variante / Vererbung stoppen), `scripts TEXT` (leer = alle Seiten), `active TINYINT(1) NOT NULL DEFAULT 1`, `dateline INT(11) NOT NULL DEFAULT 0`; `UNIQUE KEY tid_type_target (tid, type, target)` |

InnoDB, `utf8`/`utf8_unicode_ci` nach `conventions.md` §4. `target`/`variant` sind
Template-Titel (MyBB: `templates.title` VARCHAR(120)).

## Datacache
Eintrag `themevariants` (über `$cache->update()`):
```php
[
  'themes' => [tid => pid, ...],               // alle Themes aus TABLE_PREFIX.'themes'
  'rows'   => [tid => [ [type, target, variant, scripts(array), active], ... ], ...],
]
```
- Neu aufgebaut durch `themevariants_rebuild_cache()`: in `activate()`, nach jedem Speichern/
  Löschen im ACP.
- Laufzeit liest nur den Cache (keine Query pro Seitenaufruf). Fehlt die aktuelle `tid` in
  `themes` (Theme wurde nach dem letzten Aufbau angelegt), einmal neu aufbauen.
- `uninstall()` entfernt den Eintrag (`$cache->delete('themevariants')` falls vorhanden, sonst
  `DELETE FROM datacache WHERE title='themevariants'`).

## Settings (settinggroup `themevariants`)
| Setting | optionscode | Zweck |
|---|---|---|
| `themevariants_enabled` | `yesno` (Default 1) | Hauptschalter; bei 0 tun die Laufzeit-Hooks nichts (Slots werden dann zu leerem String ersetzt, damit keine Platzhalter stehen bleiben – Platzhalter sind ohnehin HTML-Kommentare). |
| `themevariants_debug` | `yesno` (Default 0) | Nur für Admins (`$mybb->usergroup['cancp'] == 1`): um jede ersetzte Stelle HTML-Kommentare `<!-- themevariants: slot headertabelle = tv_headertabelle_always (tid 40, geerbt von 38) -->` ausgeben. |

Settings in `install()`, nicht in `activate()` (Dev-Kit debugging.md Punkt 21).

## Hooks
| Hook | Handler | Priorität | Zweck |
|---|---|---|---|
| `global_intermediate` | `themevariants_handle_overrides` | **-10** | Effektive `override`-Zuordnungen für `$theme['tid']` + `THIS_SCRIPT` auflösen, Varianten-Templates per `$templates->cache('a,b')` nachladen und `$templates->cache[target] = $templates->cache[variant]` setzen. Variante nicht gefunden → Ziel unangetastet lassen. |
| `pre_output_page` | `themevariants_handle_slots` | 10 | `$contents` nach `<!--themevariants:([a-z0-9_]+)-->` durchsuchen; pro Treffer die effektive `slot`-Zuordnung auflösen und rendern bzw. durch `''` ersetzen. **Muss `$contents` zurückgeben.** |
| `admin_style_menu` | `themevariants_admin_menu` | 10 | Menüpunkt „Theme Variants" unter Templates & Style. |
| `admin_style_action_handler` | `themevariants_admin_action_handler` | 10 | Action `themevariants` → `themevariants.php`. |
| `admin_style_permissions` | `themevariants_admin_permissions` | 10 | Admin-Recht `themevariants`. |

### Verifizierte Core-Fakten (Testforum, MyBB 1.8.39)
- `global.php`: Theme wird bis Z. 294 geladen, `$templates->cache($templatelist)` Z. 480
  (enthält auch die seitenspezifische `$templatelist` des Skripts), `global_intermediate`
  Z. 498, danach Welcome-Block (`$pmslink` Z. 550, `$welcomeblock` Z. 553), `headerinclude`/
  `header` Z. 958/961, `footer` Z. 1070. **Kein Hook** zwischen Welcome-Block und
  `header`-Eval → Slots können nicht in `global_intermediate` gerendert werden (Daten fehlen
  noch) → Platzhalter + `pre_output_page`.
- `MyBB_Templates::get()` prüft zuerst `isset($this->cache[$title])` → ein gesetzter
  Cache-Eintrag gewinnt auch für Templates, die erst später angefordert werden.
- `MyBB_Templates::cache($list)` lädt `sid IN (-2,-1,templateset)` sortiert `sid ASC` →
  Set-Template überschreibt globales.
- `output_page()` (inc/functions.php Z. 24): `$contents = $plugins->run_hooks('pre_output_page', $contents);`
- `admin/modules/style/module_meta.php` feuert `admin_style_menu`, `admin_style_action_handler`,
  `admin_style_permissions`.

## Rendering von Slots
`themevariants_render($title)` wertet ein Template im **globalen Variablenkontext** aus, damit
Varianten dieselben Variablen nutzen können wie Templates, die in `global.php` bzw. im Skript
evaluiert werden (`$mybb`, `$lang`, `$theme`, `$welcomeblock`, `$pmslink`, Plugin-Variablen wie
`$infos`, `$wetter_a`, `$plots_a`, `$gesuche`, `$kalender_link` …):
```php
function themevariants_render(string $themevariants_title): string
{
    global $templates;
    extract($GLOBALS, EXTR_SKIP | EXTR_REFS);
    $themevariants_out = '';
    eval('$themevariants_out = "' . $templates->get($themevariants_title) . '";');
    return $themevariants_out;
}
```
Lokale Variablennamen bewusst mit Präfix, damit `EXTR_SKIP` keine globalen Namen verdeckt.

## Eigene Hooks / API
- `themevariants_get_effective(int $tid, string $type, string $target, string $script): ?array`
  – öffentliche Funktion, liefert die effektive Zuordnung (inkl. `from_tid` = Theme, von dem
  sie stammt) oder `null`. Wird auch von ACP-Übersicht und Selbsttest genutzt.
- Die eigentliche Auflösung als **reine Funktion** ohne Globals:
  `themevariants_resolve(array $themes, array $rows, int $tid, string $type, string $target, string $script): ?array`
  (testbar per CLI, `tests/cli-selftest.php`).

## Abhängigkeiten
Keine. Das Plugin kennt keine anderen Plugins. Hinweis für H4W: `indexvariablen` (global_intermediate, Prio 0) und
`myheadertabelle` (global_intermediate, Prio 20) evaluieren Templates in `global_intermediate`;
Theme Variants läuft mit **-10 vorher**, damit auch deren Templates überschrieben werden können.
`myheadertabelle` bleibt für die Alt-Designs (Template-Set 30) unverändert aktiv.

## Berechtigungen
- Nur ACP. Zugriff über das Admin-Recht `themevariants` (Gruppe „Templates & Style").
- Alle schreibenden ACP-Aktionen (hinzufügen, bearbeiten, löschen, aktiv umschalten) per POST; den `my_post_key` prüft `admin/index.php`
  selbst (Dev-Kit `debugging.md` #23, native Variante – kein eigener Key-Check).
- Frontend: keine Rechteprüfung nötig (liest nur Konfiguration).

## ACP (Templates & Style → Theme Variants)
`index.php?module=style-themevariants`, Tabs über einen gemeinsamen Helper
`themevariants_acp_nav_tabs(string $active): void` (globale CLAUDE.md: `output_nav_tabs`,
jede Tab-`description` ein nicht-leerer Sprachstring):

1. **Übersicht** (`action` leer): Theme-Baum (eingerückt nach `pid`). Pro Theme eine Tabelle
   seiner **effektiven** Zuordnungen: Typ · Ziel · Variante · Seiten · Herkunft („eigen" oder
   „geerbt von <Theme>") · Status. Eigene Zeilen mit Bearbeiten/Löschen, geerbte mit
   „Für dieses Theme überschreiben" (öffnet Formular vorbefüllt mit tid = dieses Theme).
   Warnsymbol, wenn das Varianten-Template im Set des Themes und global nicht existiert.
2. **Zuordnung hinzufügen** (`action=add`) / **Bearbeiten** (`action=edit&vid=`), Felder:
   - Theme (Select, alle Themes außer Master-Style tid 1)
   - Typ (Radio: „Template ersetzen" / „Slot füllen")
   - Ziel (Text; `override`: Template-Titel, `slot`: Slot-Name). Validierung
     `^[A-Za-z0-9_\-]{1,120}$` (override) bzw. `^[a-z0-9_]{1,50}$` (slot).
   - Variante (Text mit `<datalist>` der Templates des Theme-Sets + global; leer erlaubt =
     „keine Variante / Vererbung stoppen"). Validierung wie override-Ziel. Existenz-Check nur
     als Warnung (Template kann später angelegt werden).
   - Seiten (Textarea, ein Skript pro Zeile oder kommagetrennt; leer = alle Seiten).
     Validierung pro Eintrag `^[a-z0-9_\-]+\.php$`; gespeichert normalisiert kommagetrennt.
   - Aktiv (Ja/Nein).
   - Hilfetext mit Platzhalter-Syntax `<!--themevariants:name-->` beim Typ Slot.
   Duplikat (tid, type, target) → Fehlermeldung statt DB-Fehler.
3. **Löschen** (`action=delete`, POST) und **Aktiv umschalten** (`action=toggle`, POST):
   reine Aktions-Handler mit Redirect, ohne `output_nav_tabs`.

Nach jeder Änderung `themevariants_rebuild_cache()` + `log_admin_action()`.

## What NOT to change
- **Hook-Priorität `global_intermediate` = -10** – muss vor `indexvariablen` (0) und
  `myheadertabelle` (20) laufen.
- **Slots über `pre_output_page`**, nicht über eine Template-Variable in `global_intermediate`:
  zwischen Welcome-Block und `header`-Eval gibt es keinen Hook (siehe Core-Fakten).
- **`extract($GLOBALS, EXTR_SKIP | EXTR_REFS)` in `themevariants_render()`** ist Absicht
  (globaler Variablenkontext für Varianten-Templates). Nicht durch eine feste `global`-Liste
  ersetzen – das Plugin kennt die Variablen fremder Plugins nicht.
- **Das Plugin legt keine Varianten-Templates an und löscht keine** – auch nicht beim
  Deinstallieren. Varianten gehören dem Template-Set (Nutzerdaten).
- **Platzhalter in Templates werden beim Deaktivieren nicht entfernt** (sie stehen in fremden
  Templates, sind HTML-Kommentare und damit harmlos). Kein `find_replace_templatesets`.
- **Keine Fallback-Auflösung auf den Vorfahren, wenn die Seitenliste nicht passt** – die
  effektive (nächste) Zeile entscheidet allein, sonst wird das Verhalten für Admins unvorhersagbar.
- Kein PHP-in-Template, keine tid-Listen im Code.

## Folgearbeit im Theme-Repo `icw-style` (nicht Teil dieses Plugins)
Nach erfolgreicher Umsetzung + Test auf dem Testforum:
1. `templates/header.html` (Set 32): Platzhalter `<!--themevariants:headertabelle-->` unter
   `</nav>` einfügen.
2. Neues Template `tv_headertabelle_always` (Set 32) nach dem Vorbild des alten
   `headertabelle_standard_c` (sid -1): 2 Seiten (Welcome | Icon-Buttons | Hello /
   Gesuche | Inplayfacts | Plots) mit « »-Blättern, **ohne** stepcarousel.js und ohne
   Inline-Styles; Klassen `h4w-headertabelle*`, CSS token-basiert in einem Master-Stylesheet,
   mobil einspaltig. Der „Hello"-Block nutzt `{$lang->welcome_back}`, `{$pmslink}`,
   `{$myalerts_headericon}`, `{$as_header}` statt `$infoblock` (der ist in myheadertabelle
   lokal und im Set 32 nicht verfügbar).
3. Im ACP: Theme „ICW Always" (40) → Slot `headertabelle` → `tv_headertabelle_always`,
   Seiten leer (= überall).
