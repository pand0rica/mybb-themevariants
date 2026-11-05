# Theme Variants – Implementation Plan

**Umsetzung:** folge `mybb-devkit/workflows/2-implement.md`. Schritte der Reihe
nach. Bei Unklarheit gezielt nachfragen, aber nicht umplanen. Alle Details (Tabelle, Hooks,
Prioritäten, ACP-Felder, Validierungsregeln) stehen in `PROJECT_CONTEXT.md` – dort nachlesen,
nicht neu erfinden.

Testumgebung: das Testforum (Zugangsdaten nur in den privaten Notizen, nie im Repo). Nie auf
dem Live-Forum testen.

---

## Schritt 1 – Grundgerüst & Zugriffsschutz
- [x] **Ziel:** Hauptdatei mit `IN_MYBB`-Guard, `themevariants_info()` (codename
      `themevariants`, version `1.0.0`, compatibility `18*`), Hook-Registrierung aller fünf
      Hooks aus `PROJECT_CONTEXT.md` → „Hooks" **mit den dort genannten Prioritäten**
      (`global_intermediate` -10). Admin-Hooks nur registrieren, wenn `defined('IN_ADMINCP')`.
- **Dateien:** `inc/plugins/themevariants.php`
- **Vorlage:** `mybb-devkit/standards/boilerplate.md` §1, §2
- **Abnahme:** `php -l` sauber (lokal verifiziert, PHP 8.5 CLI). „Erscheint in ACP → Plugins"
  **nicht getestet** (kein Forum-/SSH-Zugriff in dieser Umgebung) – manuell nachholen.

## Schritt 2 – Lifecycle (install / is_installed / uninstall)
- [x] **Ziel:** Tabelle `themevariants` exakt nach Schema (inkl. `UNIQUE KEY tid_type_target`)
      anlegen; settinggroup `themevariants` + Settings `themevariants_enabled` (1) und
      `themevariants_debug` (0) in `install()`, `rebuild_settings()`. `is_installed()` = Tabelle
      existiert. `uninstall()`: Tabelle droppen, Settings + Gruppe löschen, `rebuild_settings()`,
      Datacache-Eintrag `themevariants` entfernen (siehe `PROJECT_CONTEXT.md` → „Datacache").
      **Keine** Templates anlegen oder löschen.
- **Dateien:** `inc/plugins/themevariants.php`
- **Vorlage:** `boilerplate.md` §3, §4 · Schema-Konventionen `conventions.md` §4 ·
  `debugging.md` Punkt 21 (Settings nicht in activate)
- **Abnahme:** `tests/cli-plugin.php` nach Kit-Vorlage geschrieben (install/status/uninstall
  gegen `themevariants`-Tabelle, `settings`, `settinggroups`, Datacache, Plugin-Cache).
  **Nicht ausgeführt** – kein lokales MyBB/DB und kein SSH-Zugriff auf das Testforum in
  dieser Umgebung verfügbar. Muss auf dem Testforum nachgeholt werden (`php tests/cli-plugin.php
  install` → Zähler prüfen → `uninstall` → alle Zähler 0/MISSING → `install`).

## Schritt 3 – activate() / deactivate()
- [x] **Ziel:** `activate()` ruft nur `themevariants_rebuild_cache()` auf (idempotent).
      `deactivate()` tut bewusst nichts außer ggf. Cache leeren – es gibt keine Templates,
      Stylesheets oder Template-Injektionen (Platzhalter bleiben stehen, siehe
      „What NOT to change").
- **Dateien:** `inc/plugins/themevariants.php`
- **Vorlage:** `boilerplate.md` §9 (nur als Gegenprobe: nichts anlegen, was nicht entfernt wird)
- **Abnahme:** Code implementiert (`themevariants_activate()` ruft ausschließlich
  `themevariants_rebuild_cache()` auf; `themevariants_deactivate()` bewusst leer mit
  begründendem Kommentar). **Nicht gegen eine echte MyBB-Instanz per CLI ausgeführt**
  (kein Forum-/SSH-Zugriff in dieser Umgebung) – Teil des offenen `tests/cli-plugin.php`-Laufs
  auf dem Testforum.

## Schritt 4 – Datenzugriff, Cache & Auflösung (Kernlogik)
- [x] **Ziel:**
  - `themevariants_db_get_all(): array`, `themevariants_db_get(int $vid): ?array`,
    `themevariants_db_insert(array $row): int`, `themevariants_db_update(int $vid, array $row): void`,
    `themevariants_db_delete(int $vid): void`, `themevariants_db_exists(int $tid, string $type, string $target, int $exclude_vid = 0): bool`
    – alle Werte per `(int)` bzw. `$db->escape_string()`, Mehrzeilen per `while`.
  - `themevariants_rebuild_cache(): void` und `themevariants_get_cache(): array` (inkl.
    „tid fehlt → einmal neu aufbauen").
  - **Reine Funktion** `themevariants_resolve(array $themes, array $rows, int $tid, string $type, string $target, string $script): ?array`
    nach den Regeln in `PROJECT_CONTEXT.md` → „Kernkonzept": eigene aktive Zeile, sonst
    nächster Vorfahr mit aktiver Zeile; Rückgabe inkl. `from_tid`; leere Variante → `null`;
    Seitenliste passt nicht → `null` (kein Rückfall auf Vorfahren). Schutz gegen Zyklen in der
    `pid`-Kette (besuchte tids merken).
  - `themevariants_get_effective(...)` als Wrapper über den Cache.
  - `themevariants_parse_scripts(string $input): array` (Zeilen/Kommas → normalisierte,
    validierte Liste) und `themevariants_is_valid_template_title()` / `…_slot_name()`.
- **Dateien:** `inc/plugins/themevariants.php`
- **Vorlage:** `conventions.md` §3 (Naming), §4 (DB), §6 (Security)
- **Abnahme:** `tests/cli-selftest.php` (siehe Schritt 9) deckt alle geforderten Fälle ab:
  eigene Zeile; geerbt über 2 Ebenen; Child überschreibt; Child mit leerer Variante stoppt
  Vererbung; inaktive Child-Zeile → Vorfahr greift; Seitenliste passt / passt nicht; unbekannte
  tid; Zyklus in `pid`. **Lokal gegen die echte Produktionsdatei verifiziert** (Stub für
  `$plugins->add_hook()`, kein volles MyBB nötig, da die getesteten Funktionen pur bzw.
  Cache-basiert sind): 29/29 Prüfungen bestanden, inkl. aller oben genannten Randfälle plus
  `themevariants_parse_scripts()` und `themevariants_collect_target_pairs()`. Die im Repo
  liegende `tests/cli-selftest.php` (mit optionalem DB-Roundtrip) selbst wurde **nicht** über
  ein echtes `inc/init.php` ausgeführt (kein Forum-/SSH-Zugriff hier) – auf dem Testforum
  nachholen.

## Schritt 5 – Laufzeit: Template-Overrides
- [x] **Ziel:** `themevariants_handle_overrides()` (global_intermediate, -10): Abbruch wenn
      `themevariants_enabled` != 1 oder `$theme['tid']` leer. Alle effektiven `override`-Zuordnungen
      für aktuelles Theme + `THIS_SCRIPT` sammeln, fehlende Varianten gesammelt per
      `$templates->cache($db->escape_string(implode(',', $titles)))` nachladen, dann
      `$templates->cache[$target] = $templates->cache[$variant]` (nur wenn Variante gefunden).
      Debug-Setting: für Admins den Variant-Inhalt mit HTML-Kommentaren umschließen.
- **Dateien:** `inc/plugins/themevariants.php`
- **Vorlage:** `debugging.md` §17 (Template-Cache-Rewrite: Timing/Priorität, Nachladen)
- **Abnahme (Testforum):** Test-Template `tv_test_footer` (Set 32) mit erkennbarem Text
  anlegen, Zuordnung per SQL/CLI für tid 40: `override` `footer` → `tv_test_footer`.
  curl bzw. Browser mit Theme 40 zeigt den Text, Theme 38 nicht; mit `scripts=index.php` nur
  auf der Startseite. Testdaten danach entfernen.
  **Code vollständig nach Spezifikation implementiert** (Hook-Priorität -10, Sammeln aller
  effektiven `override`-Ziele über `themevariants_collect_target_pairs()`, Nachladen fehlender
  Varianten per `$templates->cache()`, Debug-Kommentare für Admins). Resolve-Logik dahinter ist
  per lokalem Selbsttest verifiziert (siehe Schritt 4/9). **Der curl/Browser-Live-Test gegen
  dem Testforum wurde NICHT durchgeführt** (kein SSH-/Forum-Zugriff in dieser Umgebung) –
  offener Punkt, manuell nachzuholen.

## Schritt 6 – Laufzeit: Slots
- [x] **Ziel:** `themevariants_handle_slots($contents)` (pre_output_page): per
      `preg_replace_callback('#<!--themevariants:([a-z0-9_]+)-->#', …)` jeden Platzhalter durch
      `themevariants_render($variant)` bzw. `''` ersetzen; bei deaktiviertem Plugin alle
      Platzhalter durch `''` ersetzen. Jeder Slot-Name wird pro Seite nur einmal aufgelöst
      (Ergebnis zwischenspeichern). **Immer `$contents` zurückgeben.**
      `themevariants_render()` exakt wie in `PROJECT_CONTEXT.md` → „Rendering von Slots".
- **Dateien:** `inc/plugins/themevariants.php`
- **Vorlage:** `conventions.md` §5 (Hook-Muster)
- **Abnahme (Testforum):** Platzhalter `<!--themevariants:tvtest-->` temporär in ein Set-32-
  Template (z.B. `index`) einfügen, Test-Template `tv_test_slot` mit
  `{$mybb->user['username']} / {$theme['name']}` anlegen, Zuordnung tid 38 → Slot `tvtest`.
  Theme 38 und 40 (geerbt) zeigen Namen + Theme-Namen; Zuordnung tid 40 mit leerer Variante →
  Theme 40 zeigt nichts. Platzhalter und Testdaten danach entfernen.
  **Code vollständig implementiert**: `preg_replace_callback` über
  `<!--themevariants:([a-z0-9_]+)-->`, pro Slot nur einmal aufgelöst (Ergebnis-Cache im
  Closure), deaktiviertes Plugin ersetzt alle Platzhalter durch `''`, `themevariants_render()`
  1:1 wie in `PROJECT_CONTEXT.md` vorgegeben (inkl. `EXTR_SKIP | EXTR_REFS`). **Der Live-Test
  gegen dem Testforum wurde NICHT durchgeführt** (kein SSH-/Forum-Zugriff hier) – offener
  Punkt, manuell nachzuholen.

## Schritt 7 – ACP-Modul
- [x] **Ziel:** Modul unter Templates & Style exakt nach `PROJECT_CONTEXT.md` → „ACP":
  - Menü/Action/Permission-Handler in der Hauptdatei
    (`$sub_menu[] = ['id' => 'themevariants', 'title' => …, 'link' => 'index.php?module=style-themevariants']`).
  - Modul `admin/modules/style/themevariants.php`: Breadcrumb, `output_header`,
    Helper `themevariants_acp_nav_tabs(string $active): void` mit `output_nav_tabs`
    (Tabs „Übersicht", „Zuordnung hinzufügen"; beim Bearbeiten zusätzlicher Tab „Bearbeiten"),
    Übersicht als Theme-Baum mit effektiven Zuordnungen (eigen/geerbt, Warnung bei fehlendem
    Varianten-Template), Formular mit `Form`/`FormContainer`, Validierung + Duplikatprüfung,
    POST-Handler für add/edit/delete/toggle mit `verify_post_check()`,
    `themevariants_rebuild_cache()`, `log_admin_action()`, `flash_message()` + `admin_redirect()`.
  - Alle Ausgaben `htmlspecialchars_uni()`, alle Eingaben über `$mybb->get_input()`.
- **Dateien:** `admin/modules/style/themevariants.php`, `inc/plugins/themevariants.php`
- **Vorlage:** `boilerplate.md` §12 (Aufbau an Style- statt Tools-Modul anpassen: Hooks
  `admin_style_menu` / `admin_style_action_handler` / `admin_style_permissions`) · globale
  CLAUDE.md „ACP Page Structure"
- **Abnahme:** `php -l` sauber (siehe Gesamt-Lint unten). Aufruf ACP → Templates & Style →
  Theme Variants **nicht getestet** (kein Forum-/SSH-Zugriff in dieser Umgebung).
  **Manueller Klicktest bleibt offen** (`testing.md` §8): anlegen, bearbeiten, überschreiben
  aus geerbter Zeile, aktiv umschalten, löschen, Duplikat-Fehler, ungültige Seitenliste.
  Zusätzlicher offener Punkt: Die genaue Array-Struktur, die MyBB Core dem
  `admin_style_permissions`-Hook übergibt, konnte ohne Zugriff auf den MyBB-Core-Quelltext
  nicht 1:1 verifiziert werden; Implementierung folgt dem dokumentierten
  `admin_tools_menu`/`admin_config_permissions`-Muster (`&$admin_permissions['themevariants'] =
  $lang->themevariants;`). Beim Klicktest zusätzlich prüfen, ob die Berechtigungs-Checkbox
  „Theme Variants" unter ACP → Administratoren & Rollen → Templates & Style korrekt erscheint.

## Schritt 8 – Sprachdateien (nur Admin; english + deutsch_du)
- [x] **Ziel:** alle im ACP-Modul, in `info()`-Beschreibung (falls über Sprachdatei) und in
      den Settings genutzten Keys `themevariants_*` definieren; jede Tab-`description`
      nicht leer. Keine Frontend-Sprachdateien (keine Frontend-Strings).
- **Dateien:** `inc/languages/english/admin/themevariants.lang.php`,
  `inc/languages/deutsch_du/admin/themevariants.lang.php`
- **Vorlage:** `boilerplate.md` §13
- **Abnahme:** `mybb-devkit/scripts/lang_check.py` ist ein PowerShell-Skript für
  den Testforum-Workflow und wurde hier nicht ausgeführt; stattdessen manuell per Grep alle
  `$lang->themevariants_*`-Verwendungen in `admin/modules/style/themevariants.php` und
  `inc/plugins/themevariants.php` gegen beide Admin-Sprachdateien abgeglichen – keine fehlenden
  Keys gefunden. Beide Tab-`description`-Strings (`themevariants_tab_desc_overview`,
  `themevariants_tab_desc_add`, `themevariants_tab_desc_edit`) sind nicht-leer befüllt.

## Schritt 9 – Selbsttests finalisieren
- [x] **Ziel:** `tests/cli-plugin.php` (install→uninstall→install, Zähler Tabelle/Settings/
      Datacache) und `tests/cli-selftest.php` (reine `themevariants_resolve()`- und
      `themevariants_parse_scripts()`-Fälle aus Schritt 4, ohne DB).
- **Vorlage:** `testing.md` §3, §4
- **Abnahme:** Beide Dateien nach Kit-Vorlage geschrieben und `php -l`-sauber. Die Kernlogik
  hinter `tests/cli-selftest.php` wurde lokal gegen die echte Produktionsdatei mit einem
  MyBB-losen Harness (Stub für `$plugins`) ausgeführt: **29/29 Prüfungen bestanden**
  (`themevariants_parse_scripts`, `themevariants_is_valid_*`, `themevariants_resolve` inkl.
  aller in Schritt 4 geforderten Randfälle, `themevariants_collect_target_pairs`).
  **`tests/cli-plugin.php` und die im Repo liegende `tests/cli-selftest.php` selbst (inkl.
  DB-Roundtrip) wurden NICHT gegen ein echtes MyBB (`inc/init.php`) ausgeführt** – in dieser
  Umgebung ist weder ein lokales MyBB noch SSH-Zugriff auf das Testforum verfügbar. Beide
  Skripte müssen auf dem Testforum einmal real laufen, bevor das Plugin als abgenommen gilt.

---

Begleitdateien (README, PLUGIN_POST.html, CHANGELOG, LICENSE, FUNDING, .gitignore,
.claudeignore, docs/screenshots) macht Phase 3. Die Folgearbeit im Repo `icw-style`
(Header-Platzhalter, Template `tv_headertabelle_always`, CSS) ist **nicht** Teil dieses Plans –
siehe `PROJECT_CONTEXT.md` letzter Abschnitt.
