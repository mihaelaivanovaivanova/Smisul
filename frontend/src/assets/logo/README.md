# Logo assets

`smisul-logo.svg` is a placeholder brand mark (a simple leaf-in-circle
shape) used live by `src/components/Logo.tsx` in the header and footer.
It is hand-drawn SVG, not a downloaded/copyrighted asset.

## Replacing with the real logo

1. Drop the real logo file(s) here (prefer SVG for crisp scaling; keep a
   PNG fallback only if the real mark isn't available as vector art).
2. Keep the filename `smisul-logo.svg` (or update the import in
   `src/components/Logo.tsx`) so nothing else needs to change.
3. If the real brand has a distinct wordmark, consider removing the text
   portion rendered in `Logo.tsx` and using an all-in-one logo file
   instead.
