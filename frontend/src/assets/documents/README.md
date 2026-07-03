# Product documents (PDFs, spec sheets, certificates)

Like product images and videos, downloadable documents are served
dynamically from the backend's `Media` model and surfaced via
`services/productCatalog.ts`'s `getDownloads()` (filtered by
`mime_type === 'application/pdf'`). Nothing belongs in this folder for
the running app — it exists to document the convention for anyone
looking for "where do product PDFs go" (answer: uploaded through the
backend, not bundled in the frontend repo).
