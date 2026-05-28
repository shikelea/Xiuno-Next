# Ecosystem Compatibility Matrix

This document records the current local plugin/theme compatibility audit. It is not the public compatibility list yet; it is the internal input used before the ecosystem rebuild phase.

Local sample directories under `plugin/` are reference-only and ignored by git. Do not commit third-party sample code or generated reports.

Local compatibility tooling and generated reports stay outside the repository unless they become stable CI guards. Temporary sample tooling may inspect ignored samples, normalize findings, or run smoke checks; do not commit those local scripts or generated reports.

## Matrix Fields

The local audit used the following field contract. Future public compatibility lists should keep this shape unless stage-six requirements change.

- `status`: current compatibility classification.
- `issue_types`: high-level groups such as `php8`, `bs4`, `csrf`, `theme`, `hook`, and `metadata`.
- `minimum_xiuno_next`: earliest Xiuno Next baseline implied by the scan.
- `workaround`: practical mitigation before a package/core fix lands.
- `fix_owner`: whether the fix belongs in the core compatibility layer, the third-party package, or the theme API boundary.
- `missing_hooks`: absent legacy hook files referenced by the package.
- `metadata_valid`: whether `conf.json` was valid enough for install/enable flow review.

The local matrix summary also included `issue_type_counts` (affected sample count by issue type) and `missing_hook_counts` (affected sample count by hook name) so the stage-six review can prioritize shared compatibility work before package-by-package patches.

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

Local temporary smoke on the six `likely_compatible` samples passed for `a8c5_rank_member`, `cf_nored`, `rob_reply_hide`, `till_users_widget`, `till_widget_monthlyProgress`, and `wr_html2word`. Negative controls also behaved as expected: `till_password_strength` fails on missing hook points, while `ax_comment` fails on invalid `conf.json`.

This confirms the stage-six order: build the matrix first, improve shared compatibility layers second, and only then publish formal plugin/theme development manuals.
