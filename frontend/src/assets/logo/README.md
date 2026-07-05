# Logo assets

`smisul-logo-full.svg` — mark + "С|МИСЪЛ" wordmark, no tagline. Used in the navbar.
`smisul-logo-tagline.svg` — same mark/wordmark plus the "избирай с мисъл" tagline. Used in the footer, where there's more vertical room.

Both are traced vector art (`#24362C` dark green / `#8E9467` olive), transparent background, cropped to their content's bounding box. Rendered via `src/components/Logo.tsx`, which picks the variant with the `tagline` prop.
