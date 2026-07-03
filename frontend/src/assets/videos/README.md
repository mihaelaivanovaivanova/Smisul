# Product videos

Like product images, product videos are served dynamically from the
backend's `Media` model (returned in a product's `media` array and
filtered by `mime_type` on the frontend — see
`services/productCatalog.ts`'s `getVideos()`). Nothing belongs in this
folder for the running app; it exists to document the convention and to
be an obvious home for any brand-level (non-product) video assets added
later (e.g. a homepage background clip), which don't exist yet.
