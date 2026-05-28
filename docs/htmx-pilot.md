# HTMX Pilot

Xiuno Next introduces HTMX as a progressive enhancement layer, not as a frontend framework replacement. Plain links and forms must continue to work when JavaScript is disabled or HTMX fails to load.

## Vendored Asset

- File: `view/js/htmx.min.js`
- Version: `2.0.10`
- Source: `https://cdn.jsdelivr.net/npm/htmx.org@2.0.10/dist/htmx.min.js`
- SHA-256: `71EA67185BFA8C98C39D31717C6FCE5D852370FCDFD129DB4543774D3145C0DE`

`view/js/htmx-xiuno.js` is the local bridge. It attaches CSRF headers to non-GET HTMX requests and listens for the existing `showMessage` event emitted by `message()` through `HX-Trigger`.

## First Scope

The first pilot is homepage and forum pagination. Pagination links remain normal anchors, while HTMX-enabled browsers fetch the full page, select `#thread-list-region`, and swap only that region.

Covered templates:

- `view/htm/index.htm`
- `view/htm/forum.htm`

Avoided for now:

- post editor flows
- attachment/avatar upload
- login/register/password flows
- moderation actions

These paths stay on the existing jQuery/native JS behavior until the GET-only pilot is stable.
