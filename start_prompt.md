# Theme Variants – Start-Prompt für Phase 2 (Umsetzung)

Du setzt das MyBB-1.8-Plugin **Theme Variants** (Codename `themevariants`) um. Das
Umsetzungs-Paket aus Phase 1 liegt bereits vollständig im Repo:

  Repo `pand0rica/mybb-themevariants`

Lies in dieser Reihenfolge, BEVOR du irgendetwas tust:
1. `AGENTS.md` (dieses Repo) – Pointer aufs Dev-Kit, 5 goldene Regeln, Phasen-Zuordnung.
2. `PROJECT_CONTEXT.md` (dieses Repo) – Architektur, Tabellen, Settings, Hooks, Abhängigkeiten,
   „What NOT to change".
3. `IMPLEMENTATION_PLAN.md` (dieses Repo) – die nummerierten Arbeitsschritte.
4. Aus dem Dev-Kit bei Bedarf: `mybb-devkit/standards/boilerplate.md` (kopierfertige
   Code-Vorlagen) und `mybb-devkit/standards/conventions.md` (Naming, DB, Hooks,
   Security).

Folge danach exakt `mybb-devkit/workflows/2-implement.md`:
- Arbeite die Schritte aus `IMPLEMENTATION_PLAN.md` der Reihe nach ab und hake sie dort ab
  (`- [x]`), inklusive des jeweiligen Test-/Abnahmehinweises.
- Halte bei jedem Schritt die 5 goldenen Regeln ein: keine Core-Dateien ändern, `TABLE_PREFIX`
  statt `mybb_`, nur `$mybb->get_input()`, jede schreibende Aktion per POST +
  `verify_post_check()`, jede Funktion/jeder globale Bezeichner mit Präfix `themevariants_`.
  Ausgabe mit `htmlspecialchars_uni()`, SQL mit `escape_string()`/`(int)`.
- Bei Unklarheiten gezielt nachfragen – **nicht neu planen**, Struktur und Umfang aus
  `IMPLEMENTATION_PLAN.md` stehen fest. Größere Abweichungen (neue Tabelle, anderer Hook) mit
  dem Nutzer klären und danach den Plan-Eintrag aktualisieren, statt sie stillschweigend
  umzusetzen.
- Sprachdateien: englisch + deutsch_du, nur Admin-Set (das Plugin hat keine Frontend-Strings).

Keine Abhängigkeiten zu anderen Plugins – kann unabhängig gestartet werden. (Auf dem
Testforum laufen `indexvariablen` und `myheadertabelle`; sie bleiben unverändert und werden
nicht angefasst.)

Schreib **keinen** Code, bevor du die drei Repo-Dateien oben gelesen hast. Antworten auf
Deutsch, Code/Bezeichner/Kommentare auf Englisch, PHP 7.4 + 8.x kompatibel, keine externen
Libraries.

## Abschluss
Wenn alle Schritte abgehakt sind: `php -l` sauber, CLI-Lifecycle-Test (install→uninstall→
install) beweist vollständigen Cleanup, kurzer Bericht was umgesetzt wurde / was noch offen ist
(z.B. manueller ACP-Klicktest) / bekannte Risiken. Begleitdateien (README, PLUGIN_POST.html,
CHANGELOG …) sind **nicht** Teil dieser Phase – das macht Phase 3 (Review), ausgelöst mit
„reviewe das Projekt".
