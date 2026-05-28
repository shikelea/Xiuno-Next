# HTMX Pilot

Xiuno Next introduces HTMX as a progressive enhancement layer, not as a frontend framework replacement. Plain links and forms must continue to work when JavaScript is disabled or HTMX fails to load.

## Vendored Asset

- File: `view/js/htmx.min.js`
- Version: `2.0.10`
- Source: `https://cdn.jsdelivr.net/npm/htmx.org@2.0.10/dist/htmx.min.js`
- SHA-256: `71EA67185BFA8C98C39D31717C6FCE5D852370FCDFD129DB4543774D3145C0DE`

`view/js/htmx-xiuno.js` is the local bridge. It disables HTMX script/eval execution, attaches CSRF headers to non-GET HTMX requests, and listens for the existing `showMessage` event emitted by `message()` through `HX-Trigger`.

`view/htm/htmx_thread_list_pagination_attrs.inc.htm` centralizes the attributes used by read-only thread-list pagination. Reuse that include for the current pilot instead of copying the attribute set into each template.

## Current Scope

The pilot covers read-only thread-list pagination. Pagination links remain normal anchors, while HTMX-enabled browsers fetch the full page, select `#thread-list-region`, and swap only that region.

Covered templates:

- `view/htm/index.htm`
- `view/htm/forum.htm`
- `view/htm/my_thread.htm`
- `view/htm/user_thread.htm`

Avoided for now:

- post editor flows
- attachment/avatar upload
- login/register/password flows
- moderation actions
- thread detail pagination, because the page owns quick-reply and media-resize initialization outside the swappable list area
- notification/message list pagination, because the current core has no dedicated list route/template yet

These paths stay on the existing jQuery/native JS behavior until the GET-only pilot is stable.

## Security Notes

- HTMX responses are treated as HTML fragments only; `allowScriptTags` and `allowEval` are disabled in `view/js/htmx-xiuno.js`.
- HTMX `message()` responses use `HX-Trigger` plus `204 No Content`, so message text is not swapped into the DOM as response HTML.
