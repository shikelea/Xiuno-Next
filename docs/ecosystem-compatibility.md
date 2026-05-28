# Ecosystem Compatibility Matrix

This document defines how Xiuno Next turns local plugin/theme samples into a compatibility matrix. It is not the public compatibility list yet; it is the internal input used before the ecosystem rebuild phase.

Local sample directories under `plugin/` are reference-only and ignored by git. Do not commit third-party sample code or generated reports.

## Workflow

Run the scanner first:

```powershell
php bin/scan_ecosystem_samples.php
```

Then build the matrix:

```powershell
php bin/build_ecosystem_matrix.php
```

Generated files:

- `tmp/ecosystem_scan.json`: raw scanner findings.
- `tmp/ecosystem_matrix.json`: normalized matrix rows.
- `tmp/ecosystem_matrix.md`: local readable table.

All generated files stay in `tmp/` and are intentionally ignored.

The scanner and matrix builder tolerate malformed legacy UTF-8 by substituting invalid bytes while writing JSON. This keeps local reports machine-readable, but public compatibility lists still need manual package-name verification before publication.

For local or server-side package checks, run:

```powershell
php bin/smoke_ecosystem_sample.php <sample_dir>
```

This command temporarily enables the selected ignored sample, compiles the core files touched by its hooks, lints generated PHP, then restores the sample `conf.json`. It intentionally fails when package metadata is invalid or when a package references hook points that no longer exist in the current core index.

## Matrix Fields

- `status`: current compatibility classification.
- `issue_types`: high-level groups such as `php8`, `bs4`, `csrf`, `theme`, `hook`, and `metadata`.
- `minimum_xiuno_next`: earliest Xiuno Next baseline implied by the scan.
- `workaround`: practical mitigation before a package/core fix lands.
- `fix_owner`: whether the fix belongs in the core compatibility layer, the third-party package, or the theme API boundary.

## Status Values

- `likely_compatible`: no known scanner signal; still needs runtime smoke before public listing.
- `needs_core_compat_validation`: likely covered by the core compatibility layer, but should be validated with runtime smoke.
- `needs_metadata_repair`: package metadata is malformed and must be repaired before install/enable smoke.
- `needs_hook_boundary_review`: package references hook points that are absent from the current core hook index.
- `needs_theme_boundary_review`: theme/header/footer overrides may bypass injected CSRF, assets, or theme API expectations.
- `needs_package_patch`: package code uses removed PHP 8-era constructs and needs source changes.
- `blocked_by_php_lint`: PHP syntax errors block reliable runtime validation.
- `needs_review`: fallback status for findings that do not match a known class.

## Current Baseline

The 2026-05-28 scan covered 58 local samples, including 21 theme-like samples. It found 983 compatibility signals and 22 PHP lint errors. The normalized matrix currently classifies 10 samples as blocked by PHP lint, 15 as needing metadata repair, 5 as needing package patches, 7 as needing hook boundary review, 15 as needing core/theme validation, and 6 as likely compatible. Most findings fell into BS4 to BS5 compatibility and missing legacy hooks, followed by PHP 8 migration, theme boundary, CSRF/POST behavior, and malformed package metadata.

Local smoke on the six `likely_compatible` samples passed for `a8c5_rank_member`, `cf_nored`, `rob_reply_hide`, `till_users_widget`, `till_widget_monthlyProgress`, and `wr_html2word`. Negative controls also behaved as expected: `till_password_strength` fails on missing hook points, while `ax_comment` fails on invalid `conf.json`.

This confirms the stage-six order: build the matrix first, improve shared compatibility layers second, and only then publish formal plugin/theme development manuals.
