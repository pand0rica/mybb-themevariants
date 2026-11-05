<?php
/**
 * Theme Variants -- Admin-Sprachdatei (Deutsch, Du-Form).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// Plugin-Name / Berechtigungs-Label / Modulbeschreibung
$l['themevariants'] = 'Theme Variants';
$l['themevariants_description'] = 'Verwalte pro Theme eigene Template-Overrides und Slots.';

// Settinggroup (Titel/Beschreibung stammen aus der settings-Tabelle selbst; diese Keys
// dienen nur als Fallback bzw. für die Settinggroup-Suche)
$l['setting_group_themevariants'] = 'Theme Variants';
$l['setting_group_themevariants_desc'] = 'Einstellungen für das Theme-Variants-Plugin.';

// Tab-Navigation
$l['themevariants_tab_overview'] = 'Übersicht';
$l['themevariants_tab_desc_overview'] = 'Theme-Baum mit den effektiven Template-Overrides und Slot-Zuordnungen, inklusive geerbter Zeilen.';
$l['themevariants_tab_add'] = 'Zuordnung hinzufügen';
$l['themevariants_tab_desc_add'] = 'Lege einen neuen Template-Override oder eine neue Slot-Zuordnung für ein Theme an.';
$l['themevariants_tab_edit'] = 'Zuordnung bearbeiten';
$l['themevariants_tab_desc_edit'] = 'Bearbeite eine bestehende Template-Override- oder Slot-Zuordnung.';

// Breadcrumb / Seitentitel
$l['themevariants_breadcrumb'] = 'Theme Variants';
$l['themevariants_acp_title'] = 'Theme Variants';

// Übersichtstabelle
$l['themevariants_col_type'] = 'Typ';
$l['themevariants_col_target'] = 'Ziel';
$l['themevariants_col_variant'] = 'Variante';
$l['themevariants_col_scripts'] = 'Seiten';
$l['themevariants_col_origin'] = 'Herkunft';
$l['themevariants_col_status'] = 'Status';
$l['themevariants_col_controls'] = 'Aktionen';
$l['themevariants_scripts_all'] = 'Alle Seiten';
$l['themevariants_origin_own'] = 'eigen';
$l['themevariants_origin_inherited'] = 'geerbt von {1}';
$l['themevariants_status_active'] = 'Aktiv';
$l['themevariants_status_inactive'] = 'Inaktiv';
$l['themevariants_no_assignments'] = 'Für dieses Theme gibt es keine Zuordnungen.';
$l['themevariants_variant_missing_warning'] = 'Warnung: Das Template "{1}" existiert weder im Template-Set dieses Themes noch global.';
$l['themevariants_overwrite_for_theme'] = 'Für dieses Theme überschreiben';
$l['themevariants_edit'] = 'Bearbeiten';
$l['themevariants_delete'] = 'Löschen';
$l['themevariants_activate'] = 'Aktivieren';
$l['themevariants_deactivate'] = 'Deaktivieren';
$l['themevariants_master_style_note'] = 'Der Master-Style (tid 1) kann keine eigenen Zuordnungen haben -- er ist die Wurzel der Vererbungskette.';

// Typ-Bezeichnungen
$l['themevariants_type_override'] = 'Template ersetzen';
$l['themevariants_type_slot'] = 'Slot füllen';

// Formular
$l['themevariants_form_title'] = 'Zuordnung';
$l['themevariants_field_theme'] = 'Theme';
$l['themevariants_field_theme_desc'] = 'Das Theme, für das diese Zuordnung gilt. Child-Themes erben sie, solange sie nicht selbst eine Zeile für denselben Typ/dasselbe Ziel definieren.';
$l['themevariants_field_type'] = 'Typ';
$l['themevariants_field_type_desc'] = '„Template ersetzen" tauscht zur Laufzeit ein bestehendes Template aus. „Slot füllen" rendert ein Varianten-Template überall dort, wo der Platzhalter <code>&lt;!--themevariants:name--&gt;</code> in einem anderen Template steht.';
$l['themevariants_field_target'] = 'Ziel';
$l['themevariants_field_target_desc'] = 'Bei „Template ersetzen": der exakte Titel des zu ersetzenden Templates (z.B. „footer"). Bei „Slot füllen": der Slot-Name aus dem Platzhalter (nur Buchstaben, Ziffern, Unterstrich, z.B. „headertabelle").';
$l['themevariants_field_variant'] = 'Variante';
$l['themevariants_field_variant_desc'] = 'Titel des Templates, das den Ersatz-/Slot-Inhalt liefert. Leer lassen, um eine geerbte Zuordnung für dieses Theme gezielt abzuschalten (stoppt die Vererbung).';
$l['themevariants_field_scripts'] = 'Seiten';
$l['themevariants_field_scripts_desc'] = 'Ein Skript pro Zeile oder kommagetrennt (z.B. „index.php, portal.php"). Leer lassen für alle Seiten. Steht die aktuelle Seite nicht in der Liste, greift diese Zuordnung dort nicht (kein Rückfall auf eine geerbte Zeile).';
$l['themevariants_field_active'] = 'Aktiv';
$l['themevariants_field_active_desc'] = 'Inaktive Zeilen werden bei der Auflösung ignoriert, als gäbe es sie nicht (stattdessen kann die Zeile eines Vorfahren greifen).';
$l['themevariants_save'] = 'Speichern';

// Meldungen / Fehler
$l['themevariants_saved'] = 'Die Zuordnung wurde gespeichert.';
$l['themevariants_deleted'] = 'Die Zuordnung wurde gelöscht.';
$l['themevariants_toggled'] = 'Der Status der Zuordnung wurde aktualisiert.';
$l['themevariants_error_invalid_theme'] = 'Bitte ein gültiges Theme auswählen (der Master-Style kann keine eigenen Zuordnungen haben).';
$l['themevariants_error_invalid_type'] = 'Bitte einen gültigen Typ auswählen.';
$l['themevariants_error_invalid_target'] = 'Das Ziel ist für den gewählten Typ ungültig.';
$l['themevariants_error_invalid_variant'] = 'Der Varianten-Titel ist ungültig.';
$l['themevariants_error_invalid_scripts'] = 'Folgende Seiteneinträge sind ungültig (erwartetes Format: name.php): {1}';
$l['themevariants_error_duplicate'] = 'Für dieses Theme, diesen Typ und dieses Ziel existiert bereits eine Zuordnung. Bearbeite stattdessen den bestehenden Eintrag.';
$l['themevariants_error_invalid_vid'] = 'Ungültige Zuordnung.';
$l['themevariants_confirm_delete'] = 'Soll diese Zuordnung wirklich gelöscht werden?';
