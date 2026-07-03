# Lifestyle / marketing images

No real lifestyle photography exists yet, and per this sprint's asset
policy we don't use external/stock image URLs as stand-ins. Instead, the
homepage's decorative visuals (hero, bio-positioning section) use a
CSS-only placeholder (`.placeholder-visual` in
`src/styles/components.css`) — a soft organic gradient, not a photo.

## Adding real photography later

1. Drop optimized images here (WebP/AVIF preferred, JPEG fallback),
   named by section, e.g. `hero-01.webp`, `bio-positioning-01.webp`.
2. Import them where `.placeholder-visual` blocks currently render (see
   `HomePage.tsx`) and swap the placeholder `<div>` for an `<img>`,
   keeping the existing alt text and sizing classes.
3. Keep file sizes reasonable (target < 200KB per hero image) since this
   is a client-rendered SPA with no image CDN/optimizer configured yet.
