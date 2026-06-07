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

The 2026-05-29 parallel review found 358 remaining missing-hook signals after the low-risk shared anchors were restored. Most of them are not core gaps: 293 are `stately_*` or `widget_*` theme/component slots, 5 are assets stored under `hook/`, and several high-count PHP names belong to package-owned menu, notice, trade, or post-processing contracts. Thread, user, and theme-slot review found no additional low-risk shared template anchors to restore immediately.

Deferred examples:

- `thread_message_before.htm1`, `header_nav_user_start.htm1`, and similar names are malformed or intentionally disabled samples.
- `post_draft_buttons.htm`, `post_list_inc_message_before.php`, `post_message_after.php`, `user_index_delete_user_button_after.php`, and fox-tags suffixed files are package-private nested hooks when the public `.htm` hook already exists.
- `notice_route_menu_array_end.php`, `menu_*`, `my_trade_*`, and `plugin_haya_post_like_create_end.php` belong to plugin-owned modules that core does not currently provide.
- `stately_*`, `widget_*`, legacy sidebar/nav fragments, and theme helper files belong to the future theme API and theme packaging track.

The PHP-level review found no remaining package PHP hook that should be restored as a core contract. `model_website_*`, `stately_threadlist_*`, and `stately_wellcms_*` are WellCMS/Stately private runtime slots; `qg_auction_*` and `plugin_haya_post_like_create_end.php` are package business events; `*_fox_tags.php` files are package-private includes behind existing public `.htm` hooks. The review did identify one core behavior fix outside Hook restoration: invalid public user profile requests now stop with the neutral `user_not_exists` message instead of relying on a theme-specific `x_user_start.php` workaround.

This confirms the stage-six order: build the matrix first, improve shared compatibility layers second, and only then publish formal plugin/theme development manuals.

## 2026-05-29 Sample Smoke Delta

A follow-up local scan over the same 58 ignored samples refined the earlier raw findings:

- `conf.json` parsing must trim UTF-8 BOM before classifying metadata. After matching core `xn_json_decode()` behavior, the current sample set has no metadata parse blocker in this pass.
- Standalone PHP files and `hook/` fragments must be classified separately. Hook fragments often contain `case`, `elseif`, array entries, or template-local snippets that are invalid as isolated PHP files but valid when compiled into their target location.
- After separating hook fragments, the current sample set has one standalone PHP 8 syntax blocker: an old independent model file with an unparenthesized nested ternary. Core now blocks install/enable/upgrade before such files can enter the enabled plugin set.
- The PHP syntax guard has a CI fixture: standalone plugin PHP syntax failures are reported, while `hook/` fragments are intentionally skipped because they compile into route/template context.
- The high-count frontend signals are mostly BS4 compatibility markers already covered by `bs4-compat.css` and `bs4-compat.js`: `btn-block`, `data-toggle`, `data-target`, `data-dismiss`, `text-left/right`, `float-left/right`, `custom-select`, `custom-control`, and contextual `badge-*` classes. These still need runtime smoke, but they are not automatic package blockers.
- Dependency edges in the sample set should remain first-class matrix data. Known examples include `abs_themeacp_stately -> abs_theme_stately`, `ax_notice_sx/ob_feedback/till_quick_at -> huux_notice`, and several `tt_* -> tt_credits` packages. Dependency checks are now guarded as executable backend logic, not comment-only scanner matches.

Local scan scripts and generated reports remain ignored. Only stable conclusions, field contracts, and CI guards should be committed.

## 2026-05-30 Sample Smoke Delta

The current ignored local sample scan still covers 58 packages, with 2 theme-like packages in this pass. Metadata is clean, while the remaining blocking backend signals are one standalone PHP syntax error and one PHP 8 removed-function call (`each()`) in a theme overwrite package. The frontend signals are still dominated by BS4 compatibility markers already represented in the compatibility-layer guard surface.

The PHP package guard now checks standalone plugin PHP files for removed PHP 8 function calls in addition to `php -l`, while continuing to skip `hook/` fragments that compile into Xiuno route/template context. This converts a runtime fatal class found by real samples into a pre-install/pre-enable/pre-upgrade blocker. The guard fixture includes `overwrite/` package files as standalone PHP, matching the current theme overwrite sample that still calls `each()`.

The BS4 runtime compatibility layer already covers the high-frequency frontend markers from this scan. The CI guard now also asserts the sample-driven `custom-control` subselectors and the full contextual badge family, so future frontend cleanup cannot silently remove compatibility that real packages still use.

The MySQL 8 sample SQL pass now uses `php bin/check_mysql8_compat.php --samples=plugin` against ignored local plugin/theme packages. The current pass scanned 44 install/upgrade SQL entry points and found no hard MySQL 8 blockers such as legacy `TYPE=` table syntax, zero-date defaults, or unquoted sensitive core-style column names. It did record 15 `ENGINE=MyISAM` and 18 `DEFAULT CHARSET=utf8` legacy notes. These are compatibility-matrix inputs rather than immediate package blockers: changing default engines or charset policy can affect old-site upgrades and third-party install scripts, so this remains a staged migration decision.

The core MySQL 8 static guard now also scans `install/upgrade.sql`, not only fresh-install SQL and migrations. The installer creates missing databases with an explicit whitelisted charset/collation clause instead of inheriting the server default. This does not yet force the ecosystem from legacy `utf8`/MyISAM to `utf8mb4`/InnoDB; that migration remains a staged compatibility decision once MySQL 8.4, strict-mode behavior, old-site upgrades, and plugin/theme install SQL are covered by enough smoke data.

The same guard now watches the database driver `CREATE TABLE` engine-rewrite check. A legacy `db_mysql` parenthesis bug meant the old driver would not inspect the SQL prefix correctly before attempting the MyISAM to InnoDB rewrite. PHP 8 installations still use `pdo_mysql`, but keeping both driver implementations aligned reduces drift in shared legacy code and generated bundles.

The remote MySQL schema smoke now runs against both MySQL 8.0 and MySQL 8.4. Each version executes the fresh install schema smoke, old-version upgrade/migration smoke, and local-sample-derived plugin install SQL fixtures. This keeps MySQL 8.4 LTS compatibility visible before the project decides on any default charset or engine migration.

Database connections now expose an explicit `sql_mode` configuration key for both legacy `mysql` and supported `pdo_mysql` masters. The default remains empty to preserve Xiuno's historical compatibility behavior, but maintainers can opt into strict-mode smoke by setting a safe comma-separated SQL mode list in configuration without patching the DB driver. The MySQL 8 guard keeps both drivers aligned.

The MySQL schema CI now also runs the install schema, old-version upgrade/migration, and plugin install SQL fixtures under a strict SQL mode set: `STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY,NO_ENGINE_SUBSTITUTION`. This does not change Xiuno's default runtime mode; it is an early warning gate for future strict-mode, utf8mb4, and InnoDB migration decisions. The plugin SQL fixture now seeds an existing `bbs_post` row before running a `till_post_replies`-style `ALTER TABLE`, so strict-mode coverage is no longer limited to empty tables.

`bin/check_plugin_install_sql_smoke.php` now turns two local-sample-derived install SQL paths into CI fixtures without committing third-party packages: a `till_post_replies`-style `ALTER TABLE bbs_post` add/drop flow against an already populated post table, and a `sa_shop`-style MyISAM/utf8 create/drop flow. This keeps MySQL 8 compatibility honest while preserving the rule that ignored sample packages and raw reports stay out of the repository.

The same plugin install SQL smoke now covers two additional local-sample-derived ALTER patterns against existing core rows: a `haya_post_like`-style MyISAM/utf8 like table plus `bbs_post`/`bbs_thread` `likes` counters, and an `aitu_source`-style `bbs_post.source` plus `bbs_thread.thumbnail` pair with mixed utf8mb4 column metadata. The fixture verifies that existing post and thread rows receive the expected defaults before the added columns are dropped again. This expands the MySQL 8 ecosystem guard from one post-table ALTER sample to cross-table post/thread plugin extensions.

The fixture now also includes existing-row ALTER coverage for user and group extension patterns: `huux_notice`-style `bbs_user.notices` / `bbs_user.unread_notices` counters and `tt_read_stately`-style `bbs_group.readp`, `bbs_group.allowPostRead`, and `bbs_thread.readp` columns. This keeps dependency-family and theme-adjacent plugin SQL behavior visible under both default and strict MySQL 8 CI modes.

Forum-level permission extensions are covered as well through a `tt_offer`-style fixture that adds `allowOffer` to `bbs_group` and `bbs_forum`, plus offer counters/status fields on `bbs_thread`, verifies defaults on existing rows, and removes the added columns. This expands the install SQL smoke to the core post/thread/user/group/forum tables most commonly touched by local samples.

The MySQL 8 static guard now rejects literal defaults on `TEXT`/`BLOB`-family columns. In the ignored local sample set this class currently maps to cover/note/lucky-post package patches rather than core compatibility-layer behavior: `till_forum_cover`, `till_user_cover`, `sa_noteapp`, and `xn_lucky_post` use `TEXT DEFAULT ''`-style column definitions. These should be fixed in package SQL or migration shims before they are promoted to positive install smoke fixtures.

The same MySQL 8 guard now rejects invalid integer type order such as `UNSIGNED int`. The current ignored sample hit is `qg_auction/install.php`, where `auction_log.aid` is declared as `UNSIGNED int` instead of `int unsigned`. This remains a package repair or future migration-shim candidate, not a positive plugin install SQL fixture.

Discuz import converters are now out of scope for Xiuno Next. The legacy import scripts `tool/dx_to_xn4.php`, `tool/dx32_to_xn4.php`, and `tool/dx34_to_xn4.php` have been removed, and `bin/check_legacy_converter_safety.php` now guards against restoring them. The active old-site migration target for this phase is Xiuno BBS 4.0.4-style upgrades through the maintained `migrate` / `upgrade` path, not cross-product imports.

The legacy upgrade smoke now seeds an old `bbs_user` row with a `char(32)` MD5-style password hash before running both `migrate` and `upgrade`. The smoke verifies that the password column is widened to `varchar(255)` while the legacy user, salt, and hash remain intact for the normal login-time bcrypt upgrade path.

The same smoke also seeds a legacy content relation set across `bbs_forum`, `bbs_thread`, `bbs_post`, `bbs_attach`, `bbs_thread_top`, `bbs_forum_access`, `bbs_mythread`, and `bbs_mypost`. After both migration paths it checks multiple users and forums, the forum/thread/first-post/reply/attachment chain, quoted long replies, two text attachment records, top-thread metadata, forum permission flags, thread `last_date`/`mods` metadata, conversion-tool digest extension data (`bbs_thread_digest`, `bbs_thread.digest`, and `bbs_user.digests`), closed image-thread metadata, image attachment dimensions, strict-mode-safe boundary values (`0` dates/IPs, max unsigned IP/date/filesize, 128-byte subjects, 120-byte filenames, and empty post/filetype strings), and each user's thread/post indexes. This gives old-site migration its first executable guard for secondary content relations rather than only the minimal first-post chain.

The `upgrade` path now also has smoke coverage for cleanup of legacy runtime artifacts. Before running the 4.0.4-style upgrade path, the smoke seeds `tmp/model.min.php`, `tmp/plugin_hook.php`, `tmp/safe_mode.php`, and the `tmp/safe_mode` marker, then verifies that the upgrade command removes them. This guards against an upgraded site continuing to run stale model/plugin hook caches or remaining stuck in safe mode.

The legacy upgrade smoke now also verifies no-op idempotency after a successful 4.0.4-style upgrade. Running `php bin/xiuno upgrade` again against the already upgraded test site must exit successfully without rewriting `conf/conf.php`, changing `xn_migrations`, changing `xn_upgraded_from`, changing `xn_upgraded_date`, recreating legacy cache/safe-mode files, or altering the seeded legacy users and content relations. This keeps repeated maintenance runs from becoming a hidden migration risk.

`bin/check_plugin_dependency_flow_smoke.php` now runs real dependency failure flows in a temporary plugin app. It verifies that install-time missing/downloaded/disabled/old dependencies and uninstall-time reverse dependencies release `plugin_task`, include structured status text, link only to locally available dependency detail pages, and avoid dead links for missing remote packages.

`bin/check_ecosystem_dependency_family_smoke.php` turns the local-sample `huux_notice` dependent family (`ax_notice_sx`, `ob_feedback`, `till_quick_at`) and the `abs_themeacp_stately -> abs_theme_stately` theme-adjacent dependency into repository fixtures. It keeps plugin and theme dependency metadata in the same compatibility track without committing third-party packages.

`bin/check_plugin_same_type_dependency_smoke.php` covers the deeper replacement edge: a new theme can pass its dependency preflight because an old same-type theme is still installed, then invalidate itself when the same-type auto-uninstall batch removes that dependency. The smoke verifies the new theme, the dependency theme, and every touched same-type candidate roll back together, while `plugin_task` is released and the dependency message remains structured.

`bin/check_plugin_theme_overwrite_compile_smoke.php` adds a runtime guard for the theme overwrite compiler. It verifies that enabled packages participate in `overwrites_rank` order, disabled or not-installed packages are ignored, symlink overwrite files are skipped, and `disabled_plugin` mode falls back to the core template. This keeps the theme compatibility track covered by executable behavior rather than static checks alone.

`bin/check_plugin_hook_context_compile_smoke.php` covers hook fragments that are intentionally not standalone-linted. It compiles `case` route fragments, HTML template fragments, and PHP hooks with legacy `<?php exit;` guards into their target contexts, verifying `hooks_rank` order while excluding disabled or not-installed packages.

`bin/check_plugin_upgrade_lifecycle_smoke.php` exercises the trusted-package upgrade rollback boundary that will matter when local or rebuilt-registry upgrades are re-enabled. It snapshots an installed package, replaces it with a new package whose `upgrade.php` exits or throws, and verifies the old package directory, old `conf.json` state, backup snapshot cleanup, and `plugin_task` release.

## 2026-05-29 Lifecycle Hardening Delta

The plugin manager now treats lifecycle state changes as a guarded path rather than a best-effort write:

- `plugin_install()`, `plugin_unstall()`, `plugin_enable()`, and `plugin_disable()` snapshot plugin state before mutating globals and return `FALSE` if `conf.json` cannot be written.
- Admin install, unstall, and upgrade flows execute `install.php`, `unstall.php`, and `upgrade.php` through a lifecycle wrapper. Runtime `Throwable` failures restore the previous installed/enabled state and release the shared plugin task lock before reporting failure.
- Auto-unstall writes for mutually exclusive themes or same-suffix plugins now check write results while still staying inside the shared plugin task lock.
- `bin/check_plugin_lifecycle_safety.php` is part of CI so config write checks, lifecycle rollback, and lock-safe failure reporting cannot silently regress.
- `bin/check_plugin_lifecycle_exit_smoke.php` now runs real child-process lifecycle smoke for legacy `install.php` / `unstall.php` files that call `exit`. The parent process verifies that `conf.json` is restored to the original state and `plugin_task` is released after shutdown rollback. It also covers same-type plugin/theme replacement where an old package exits during `unstall.php`, ensuring the new package and the old package are restored as one batch.

Downloaded plugin packages now use a package-directory rollback boundary during download and upgrade. The admin flow snapshots the existing plugin directory before replacement, restores it if package copy, PHP syntax checks, config writes, or `upgrade.php` fail, and deletes the snapshot after success. `bin/check_plugin_package_rollback.php` is part of CI so the rollback path and dotfile-aware package copies cannot silently regress. The remaining stage-six input is runtime smoke for representative real plugin/theme samples.

The same review also hardened adjacent admin paths: base settings and GitHub proxy settings now fail when `conf/conf.php` cannot be written, custom update proxies are constrained to public HTTPS URLs before test/save/download paths use them, admin user create/update now rejects empty or malformed username/email/password/group data before writing account records, and forum/group list deletion now requires explicit `delete_fid`/`delete_gid` markers instead of treating missing POST rows as deletions. `bin/check_admin_config_safety.php`, `bin/check_admin_user_safety.php`, and `bin/check_admin_list_delete_safety.php` guard those contracts in CI.

## 2026-05-29 Theme API Safety Delta

The first committed theme smoke target focuses on the core resource API rather than local third-party samples. `theme_enqueue_style()` and `theme_enqueue_script()` still accept relative and HTTP(S) assets, but renderers now skip whitespace/control-character URLs, protocol-relative URLs, and non-HTTP schemes such as `javascript:` or `data:`. Script attributes are filtered to valid attribute names and skip `on*` event handlers before HTML attribute escaping. `bin/check_theme_api_safety.php` guards capability lookup, resource ordering assumptions, URL filtering, attribute filtering, and attribute escaping in CI.

## 2026-05-29 Frontend and Upgrade Boundary Delta

The BS4 compatibility CSRF bridge now attaches `X-CSRF-TOKEN` only to same-origin jQuery/fetch POST requests. This keeps legacy plugin/theme forms working while avoiding token leakage to cross-origin requests made by custom themes or third-party widgets. `bin/check_frontend_security.php` guards the same-origin contract.

Plugin upgrades now reload the replaced package's `conf.json` before install and re-check dependencies inside the same package rollback boundary. If a new plugin version adds a missing dependency, the upgrade fails and restores both the old package directory and the previous installed/enabled state. `bin/check_plugin_package_rollback.php` now guards this sequence.

The same dependency gate now rejects target plugin metadata errors before install/upgrade writes state. This covers malformed self metadata such as a non-array `dependencies` field after `plugin_read_by_dir()` normalization. Upgrade preflight intentionally allows the old package metadata to be repaired by the replacement package, but the upgrade path reloads the new `conf.json` and then applies the strict metadata/dependency gate inside the existing package and state rollback boundary before writing installed/enabled state.

Plugin write actions now enforce backend state preconditions before lifecycle work: install requires not-installed, unstall requires installed, enable requires installed and disabled, disable requires installed and enabled, and upgrade requires a real available upgrade. This prevents stale tabs or manual POSTs from replaying lifecycle scripts in the wrong state.

The install flow now preflights reverse dependencies before auto-uninstalling same-type plugins/themes. Auto-uninstall candidates are limited to already installed packages, run through the normal `unstall.php` lifecycle, and carry the newly installed plugin snapshot as rollback context so lifecycle failure does not leave the replacement marked installed.

Plugin lifecycle files are now protected by a shutdown rollback guard. If a legacy `install.php`, `unstall.php`, or `upgrade.php` calls `message()` / `exit` instead of returning, the pending state and package snapshots are restored and the shared plugin task lock is released during shutdown.

Same-type plugin/theme replacement now uses a batch rollback context. If installing a new theme or same-suffix plugin auto-uninstalls more than one existing package and a later package write or `unstall.php` lifecycle fails, Xiuno Next restores the new package state and every old same-type package touched earlier in the batch. This avoids split states where one old theme remains uninstalled while the replacement is rolled back.

Admin plugin list/detail pages now escape plugin package metadata before rendering names, versions, descriptions, icon URLs, QQ values, and confirmation text. External brief links are only rendered for allowed HTTP(S) URLs and use `rel="noopener noreferrer"`. This treats `conf.json` metadata from local and downloaded packages as untrusted ecosystem input.

The core compatibility injector now has a stricter resource boundary for overwritten themes: `view_url` must remain relative or site-absolute, protocol-relative and scheme-bearing values are rejected, and the BS4/CSRF JS shim is appended even when a custom theme omits closing `body/html` tags. Plugin/theme `overwrite/` resolution also skips symlink files so local package overlays cannot redirect template reads through symbolic links.

## 2026-05-29 BS4 Compatibility Guard Delta

The high-count BS4 signals from the local sample matrix now have a dedicated repository guard. `bin/check_bs4_compat_layer.php` asserts that `bs4-compat.css` keeps the common legacy selectors (`form-group`, `btn-block`, `custom-file`, `custom-control`, `input-group-prepend/append`, legacy spacing, badges, dropdown alignment, and button groups), and that `bs4-compat.js` keeps BS4 data-attribute conversion, jQuery Modal/Tooltip/Popover/Button proxies, Xiuno helper fallbacks, custom-file labels, dropdown alignment, close-button handling, tab href migration, and same-origin CSRF boundaries. This does not replace real browser smoke, but it prevents accidental deletion of the compatibility surface that the 58 local samples currently depend on.

## 2026-05-29 High-Frequency Hook Triage

A focused review of the highest-count remaining hook names found no new low-risk core hook to restore. `notice_route_menu_array_end.php` belongs to the `huux_notice` notification menu model; `menu_magichref_case_end.php` and `menu_magichref_datalist_end.php` belong to the `abs_menu` magic menu renderer/configuration; `my_trade_after.htm` belongs to the `tt_credits` trade page used by related `tt_*` packages. These are plugin-family contracts rather than shared core page anchors. The compatibility path is dependency metadata, package repair, or future native plugin/theme APIs, not adding misleading core hooks without the owning data model.
