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

The first shared hook-boundary fixes restored low-risk legacy anchors that match existing core page semantics: `user_resetpw_password_after.htm` on the reset-password form, `index_page_end.htm` after homepage pagination, `index_thread_list_nav_item_end.htm` after the homepage thread navigation items, `header_nav_user_username_after.htm` after the logged-in header username, `my_common_avatar_after.htm` after the account sidebar avatar, `my_common_groupname_after.htm` after the account profile group name, `my_nav_photo_after.htm` after the account avatar tab, `__my_nav_end.htm` at the end of the account profile navigation, `thread_username_after.htm` after the thread author link, `thread_user_avatar_after.htm` after the thread sidebar author avatar, `thread_quick_reply_message_after.htm` after the quick-reply textarea, `user_profile_avatar_after.htm` after the public profile sidebar avatar, and `user_nav_after.htm` at the end of public profile navigation tabs. Package-private hooks found inside individual samples are not promoted to core unless a stable cross-package contract is identified.

## Hook Boundary Review

Remaining missing-hook signals are split before any core change:

- Restore to core only when an old hook has a clear existing page position, is passive, and represents a shared extension contract.
- Keep package-private nested hooks inside the package or future package patches. Examples include helper includes such as `index_page_end_fox_tags.php`, package-owned menu hooks, and post-processing helper hooks included by a package's own `.htm` hook.
- Keep theme-private layout slots inside the theme API track. Large families such as `stately_*`, `widget_*`, legacy sidebars, and custom layout fragments are theme composition contracts, not default-core hook points.
- Treat malformed hook filenames and assets found under `hook/` as package quality issues, not core compatibility gaps.
- Review PHP-level runtime hooks separately because execution timing can affect data mutation, permissions, cache state, and security checks.
- Do not restore hooks whose sample output depends on a different HTML container than core provides. For example, `user_profile_groupname_after.htm` currently emits list items for theme hero layouts, while the default profile page renders the group name inline.

This confirms the stage-six order: build the matrix first, improve shared compatibility layers second, and only then publish formal plugin/theme development manuals.
