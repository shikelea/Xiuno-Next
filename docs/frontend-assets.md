# Frontend Asset Audit

Xiuno Next is in the lightweight modernization phase. Frontend cleanup should be subtractive: keep the compatibility layer that old plugins and themes rely on, but remove historical assets that core no longer loads.

## Scanner

Run the audit from the repository root:

```powershell
php bin/scan_frontend_assets.php
```

The script scans tracked assets under `view/css`, `view/js`, `view/img`, `view/font`, and `admin/view/css`, then writes a local report to `tmp/frontend_assets_scan.json`. The report is intentionally ignored by git.

Asset status:

- `referenced`: core files contain a literal path reference or a resolvable CSS `url(...)`.
- `compatibility_keep`: core may not reference the asset directly, but it is retained for legacy plugin/theme compatibility.
- `possibly_unused`: no core reference and no compatibility keep reason. These are review candidates, not automatic deletions.

## Current Cleanup

Removed in this pass:

- `view/js/es6-shim.js`: only loaded for Internet Explorer; Bootstrap 5 already drops IE support, so this extra request no longer buys real compatibility.
- `view/css/bootstrap-umeditor.css`: old UMEditor/Bootstrap 4 beta stylesheet, not loaded by core and effectively comment-only.
- `view/img/water-small-xiuno.psd`: source design file, not a runtime asset.
- `view/font/FontAwesome.otf`: not referenced by the shipped FontAwesome stylesheet.
- `view/font/fontawesome-webfont.woff2`: redundant because the shipped FontAwesome stylesheet embeds WOFF2 as a data URI.

Retained for now:

- `view/js/vue.js`: unused by core, but kept until local plugin/theme samples confirm there is no ecosystem dependency on the bundled Vue 2 file.
- `view/js/popper-utils.js`: unused by core, but related to the legacy Popper 1 global. Keep until plugin/theme audit proves it is safe to remove.
- `view/js/popper.js`: redundant for Bootstrap 5 bundle internals, but still useful as a compatibility global for older plugins.
- `view/js/upload.js`, `view/img/filetype.png`: legacy upload/attachment helpers, kept until attachment plugin samples are audited.
- `view/img/water-small-xiuno.png`: alternate watermark asset, kept until branding assets are normalized.

## Cleanup Rules

Only delete an asset when all of these are true:

- It has no core reference in `php bin/scan_frontend_assets.php`.
- It is not part of the BS4 compatibility layer or a known legacy global.
- Local plugin/theme sample scans do not show a dependency on its public path.
- The deletion is recorded here and reflected in `PLAN.md`.
