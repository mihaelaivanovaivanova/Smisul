# Seed media structure

This is the permanent home for the project's media *conventions* — where
each kind of asset belongs, both for the development seed data and for
real content later. It is deliberately separate from `storage/app/public`
(the runtime disk `Media` records actually point to): `storage/` is
gitignored and rebuilt by `php artisan migrate:fresh --seed`, so anything
that needs to survive a fresh clone lives here or in code, not there.

For the product photos, the demo PDF, and the demo video used by the
development dataset, nothing is committed as a binary file at all — they
are generated at seed time by `App\Support\PlaceholderMedia` and written
into `storage/app/public/...`. That keeps the dataset fully reproducible
from a single command with zero binary drift, and means there's nothing
here to go stale. See each subfolder's README for what belongs there
once real assets exist.

## Folders

- `brand/logos/` — the real brand mark, once one exists (see also
  `frontend/src/assets/logo/`, which is the frontend's own copy for
  direct import into the UI).
- `products/` — real product photography.
- `lifestyle/` — marketing/lifestyle photography (homepage hero, etc.).
- `videos/` — real product demo videos.
- `pdf/` — real product spec sheets / documentation.
- `icons/` — a real icon set, if the hand-authored inline SVGs in
  `frontend/src/components/icons/Icon.tsx` are ever replaced.
- `thumbnails/` — pre-generated thumbnail variants, if/when an image
  pipeline is introduced (none exists yet — the storefront currently
  serves full-size images directly).
