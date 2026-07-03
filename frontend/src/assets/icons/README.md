# Icons

The handful of icons currently used on the storefront (leaf, shield,
heart, sparkle, truck) are hand-authored inline SVG paths in
`src/components/icons/Icon.tsx` — no icon library dependency was added,
per this sprint's "no heavy UI library" constraint.

## Replacing with a real icon set

If a proper brand icon set becomes available:

1. Drop the SVG files here, one per icon.
2. Update `Icon.tsx` to import and render the real files instead of the
   inline `PATHS` map (or keep the same `IconName` union and swap the
   implementation — callers don't need to change).
