# Changes

## 1.0.0 (2026-11-23)

- First release.
- Course page *More > Balanced groups*: choose people (role, optional existing group), balancing
  fields (profile, custom profile, gradebook items including the course total, CSV columns),
  priorities, together/apart rules (typed or from CSV), number of groups or min/max size, and
  core-style group names.
- Preview with a per-field balance table computed locally; *Run again*; *Create* makes a grouping
  and groups with the core groups API and logs `\local_balancedgroups\event\groups_created`.
- Anonymised requests to the Balanced Group Maker API (random per-request person codes, f1..fn,
  c1..cn, T/A rule codes), Privacy API provider with the external location declared.
- Site settings: enable sending (off by default), API URL, timeout.
