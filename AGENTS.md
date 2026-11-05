# Theme Variants – Agent Instructions

Dieses Repo wird mit dem **MyBB Plugin Dev Kit** entwickelt. Bevor du hier etwas tust:

1. Lies zuerst das Dev-Kit: **`mybb-devkit/AGENTS.md`** und halte dich an alle dort
   verlinkten Standards. Das Kit ist die kanonische Quelle für Konventionen, Boilerplate,
   Debugging und Testing.
2. Lies dann in diesem Repo: `PROJECT_CONTEXT.md` (Kontext) und – in der Umsetzungsphase –
   `IMPLEMENTATION_PLAN.md` (die Arbeitsschritte).

Das Dev-Kit wird **nicht** in dieses Repo kopiert; es wird per absolutem Pfad referenziert.

## Kurz-Kontext
Pro Theme eigene Template-Varianten für ein gemeinsam genutztes Template-Set: bestehende
Templates ersetzen (`override`, Laufzeit-Tausch im Template-Cache bei `global_intermediate`
Prio -10) oder Slots füllen (`<!--themevariants:name-->`, gerendert in `pre_output_page`).
Zuordnung im ACP unter Templates & Style, Vererbung über die Theme-Eltern-Kette, Seitenliste
pro Zuordnung. Eine Tabelle, ein Datacache-Eintrag, keine Frontend-Strings.

## Sprache
Antworten Deutsch, Code/Bezeichner Englisch. PHP 7.4 + 8.x. Keine externen Libraries.

## Phasen (der Nutzer löst sie einzeln aus)

| Nutzer sagt | Phase | Folge dieser Kit-Datei |
|---|---|---|
| „umsetzen / implementieren" | Umsetzung | `mybb-devkit/workflows/2-implement.md` + `IMPLEMENTATION_PLAN.md` |
| „reviewe das Projekt" | Review | `mybb-devkit/workflows/3-review.md` |
| „release das Projekt" | Release | `mybb-devkit/workflows/4-release.md` |

## Die 5 goldenen Regeln (Kurzform, Details im Kit)
Keine Core-Edits · `TABLE_PREFIX` · `$mybb->get_input()` · POST + `verify_post_check()` (Frontend; im ACP nur POST-Pflicht, Key prüft MyBB) ·
alles mit Präfix `themevariants_`. Ausgabe `htmlspecialchars_uni()`, SQL `escape_string()`/`(int)`.
