# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] – unreleased

### Added
- Per-theme template assignments of two types: *replace template* (override) and
  *fill slot* (`<!--themevariants:name-->` placeholder rendered at `pre_output_page`).
- Inheritance across the theme parent chain; an empty variant stops inheritance.
- Per-assignment page list (`THIS_SCRIPT`), active flag.
- ACP module under Templates & Style with theme tree overview, add/edit, toggle and delete.
- Settings: master switch and debug comments for admins.
- Datacache `themevariants` so the front end runs without extra queries.
- English and German (du) admin language files, CLI self-tests.

### Security
- ACP CSRF protection uses MyBB's native mechanism (dev kit `debugging.md` #23): write
  handlers only act on POST and `admin/index.php` validates the `my_post_key` itself –
  no own key check or helper.
  Save, delete and toggle now require POST; a GET link no longer triggers them.

### Changed
- Project documentation and test scripts no longer contain local paths, the test forum host
  or SSH access data; dev kit references point to the `mybb-devkit` repository.

### Fixed
- The delete confirmation used `addslashes()` to build a JavaScript string; it now uses
  `json_encode()`.
- Plugin website in `themevariants_info()` points to `pand0rica/mybb-themevariants`.
- ACP assignment form: the *Type* radio buttons passed the checked state as label (`generate_radio_button()` expects the label as 3rd parameter and `checked` in the options), so the first option read “1 Replace template” and neither option was pre-selected – also not when editing an existing slot assignment.

