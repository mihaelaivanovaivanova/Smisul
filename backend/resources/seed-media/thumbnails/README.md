# Thumbnails

No image-resizing pipeline exists yet — the storefront currently serves
full-size images directly for both galleries and thumbnails (see
`getGalleryImages()` in `frontend/src/services/productCatalog.ts`). If a
thumbnail-generation step is added later (e.g. on upload), generated
variants belong here rather than being committed as static files.
