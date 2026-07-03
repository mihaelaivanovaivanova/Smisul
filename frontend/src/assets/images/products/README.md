# Product images

Product photography is **not** bundled in the frontend. It's served
dynamically from the backend's `Media` model (see Sprint 2/3) and
returned as absolute URLs on each product's `media` array — that's the
architecture already in place, so no local files belong in this folder.

This README exists so the folder is visible in version control and so
anyone looking for "where do product photos go" lands here and finds
the answer: upload them through the backend (admin UI, not built yet)
rather than committing image files to the frontend repo.
