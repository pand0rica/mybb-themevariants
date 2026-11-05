<?php
/**
 * Theme Variants -- admin language file (English).
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// Plugin name / permission label / module description
$l['themevariants'] = 'Theme Variants';
$l['themevariants_description'] = 'Manage per-theme template overrides and slots.';

// Settinggroup (title/description are taken from the settings table itself, these are
// only used as a fallback / for the settings group listing search)
$l['setting_group_themevariants'] = 'Theme Variants';
$l['setting_group_themevariants_desc'] = 'Settings for the Theme Variants plugin.';

// Tab navigation
$l['themevariants_tab_overview'] = 'Overview';
$l['themevariants_tab_desc_overview'] = 'Theme tree with effective template overrides and slot assignments, including inherited rows.';
$l['themevariants_tab_add'] = 'Add Assignment';
$l['themevariants_tab_desc_add'] = 'Create a new template override or slot assignment for a theme.';
$l['themevariants_tab_edit'] = 'Edit Assignment';
$l['themevariants_tab_desc_edit'] = 'Edit an existing template override or slot assignment.';

// Breadcrumb / page titles
$l['themevariants_breadcrumb'] = 'Theme Variants';
$l['themevariants_acp_title'] = 'Theme Variants';

// Overview table
$l['themevariants_col_type'] = 'Type';
$l['themevariants_col_target'] = 'Target';
$l['themevariants_col_variant'] = 'Variant';
$l['themevariants_col_scripts'] = 'Pages';
$l['themevariants_col_origin'] = 'Origin';
$l['themevariants_col_status'] = 'Status';
$l['themevariants_col_controls'] = 'Controls';
$l['themevariants_scripts_all'] = 'All pages';
$l['themevariants_origin_own'] = 'Own';
$l['themevariants_origin_inherited'] = 'Inherited from {1}';
$l['themevariants_status_active'] = 'Active';
$l['themevariants_status_inactive'] = 'Inactive';
$l['themevariants_no_assignments'] = 'No assignments for this theme.';
$l['themevariants_variant_missing_warning'] = 'Warning: template "{1}" does not exist in this theme\'s template set nor globally.';
$l['themevariants_overwrite_for_theme'] = 'Override for this theme';
$l['themevariants_edit'] = 'Edit';
$l['themevariants_delete'] = 'Delete';
$l['themevariants_activate'] = 'Activate';
$l['themevariants_deactivate'] = 'Deactivate';
$l['themevariants_master_style_note'] = 'Master Style (tid 1) cannot have its own assignments -- it is the root of the inheritance chain.';

// Type labels
$l['themevariants_type_override'] = 'Replace template';
$l['themevariants_type_slot'] = 'Fill slot';

// Form
$l['themevariants_form_title'] = 'Assignment';
$l['themevariants_field_theme'] = 'Theme';
$l['themevariants_field_theme_desc'] = 'The theme this assignment applies to. Child themes inherit it unless they define their own row for the same type/target.';
$l['themevariants_field_type'] = 'Type';
$l['themevariants_field_type_desc'] = '"Replace template" swaps an existing template at runtime. "Fill slot" renders a variant template wherever the placeholder <code>&lt;!--themevariants:name--&gt;</code> is placed in another template.';
$l['themevariants_field_target'] = 'Target';
$l['themevariants_field_target_desc'] = 'For "Replace template": the exact title of the template to replace (e.g. "footer"). For "Fill slot": the slot name used in the placeholder (letters, digits, underscore only, e.g. "headertabelle").';
$l['themevariants_field_variant'] = 'Variant';
$l['themevariants_field_variant_desc'] = 'Title of the template that provides the replacement/slot content. Leave empty to explicitly disable an inherited assignment for this theme (stops inheritance).';
$l['themevariants_field_scripts'] = 'Pages';
$l['themevariants_field_scripts_desc'] = 'One script per line or comma-separated (e.g. "index.php, portal.php"). Leave empty to apply on all pages. If the current page is not listed, this assignment does not apply here (no fallback to an inherited row).';
$l['themevariants_field_active'] = 'Active';
$l['themevariants_field_active_desc'] = 'Inactive rows are ignored during resolution, as if they did not exist (an ancestor\'s row may apply instead).';
$l['themevariants_save'] = 'Save';

// Messages / errors
$l['themevariants_saved'] = 'The assignment has been saved.';
$l['themevariants_deleted'] = 'The assignment has been deleted.';
$l['themevariants_toggled'] = 'The assignment status has been updated.';
$l['themevariants_error_invalid_theme'] = 'Please select a valid theme (Master Style cannot have its own assignments).';
$l['themevariants_error_invalid_type'] = 'Please select a valid type.';
$l['themevariants_error_invalid_target'] = 'The target is invalid for the selected type.';
$l['themevariants_error_invalid_variant'] = 'The variant title is invalid.';
$l['themevariants_error_invalid_scripts'] = 'The following page entries are invalid (expected format: name.php): {1}';
$l['themevariants_error_duplicate'] = 'An assignment for this theme, type and target already exists. Edit the existing entry instead.';
$l['themevariants_error_invalid_vid'] = 'Invalid assignment.';
$l['themevariants_confirm_delete'] = 'Are you sure you want to delete this assignment?';
