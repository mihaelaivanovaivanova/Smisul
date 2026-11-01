import type { Media } from '../types/product';

/**
 * Slugs for the two Miswak kits (seeded by ProductBundleSeeder.php), each on
 * its own styled page (KitProductPage.tsx, same layout as
 * AccessoryProductPage.tsx) rather than the default product layout. Kept
 * in one place, same reason as accessoryProducts.ts: OnePersonKitPage.tsx,
 * TwoPersonKitPage.tsx and ProductPage.tsx's routing guard never drift from
 * each other, and neither kit page has to import the other just to
 * cross-sell it.
 */
export const ONE_PERSON_KIT_SLUG = 'miswak-1-person-kit';
export const TWO_PERSON_KIT_SLUG = 'miswak-2-person-kit';

/**
 * The original 12 kit photos (real packaging/lifestyle shots of a kit's
 * box, case, bag, scraper and sticks together) - static assets bundled
 * with the frontend (public/kits/box1.webp..box12.webp). Each kit's shell
 * product (see KitShellProductService) now has its own real, independently
 * admin-manageable gallery (/admin/products - same ProductMediaManager
 * every other product's photos use, reorder/set-main/delete included),
 * seeded from these same 12 files - see KitProductPage.tsx and
 * PurchasePanel.tsx, which both prefer that live gallery and fall back to
 * this constant only for a kit whose shell has no photos yet.
 */
export const KIT_GALLERY_IMAGES: Media[] = Array.from({ length: 12 }, (_, index) => ({
  id: -(index + 1),
  url: `/kits/box${index + 1}.webp`,
  mime_type: 'image/webp',
  alt_text: 'Съдържанието на Miswak комплекта',
  is_primary: index === 0,
  sort_order: index,
  focus_x: 0.5,
  focus_y: 0.5,
}));

/**
 * Each kit's one-line tagline - shown on its own product page (right under
 * the rating) and on the kit's own option card wherever it's offered
 * alongside the other kit (BundleRadioSelector, on the main Miswak page's
 * "Complete Kits" toggle). Kept here, keyed by slug, rather than duplicated
 * as a hardcoded prop in both places, so the two can never drift apart -
 * see bundleOffers.ts's toBundleOffer(), which reads this into every
 * BundleOffer's own `description` field.
 */
export const KIT_TAGLINES: Record<string, string> = {
  [ONE_PERSON_KIT_SLUG]: 'Вземи пълна устна рутина.',
  [TWO_PERSON_KIT_SLUG]: 'Вземи пълна устна рутина за теб и твой близък.',
};

export interface KitComponentCopy {
  /** One line on what the item actually is. */
  info: string;
  /** One line on why it's worth having. */
  benefit: string;
}

/**
 * Short info/benefit copy for each real product a kit can contain - shown
 * in KitProductPage's own "What's included" section, one card per distinct
 * component product (image/name/link come from the component's own live
 * product data instead - see BundleComponent.product - only this curated
 * text isn't available from that API response). Keyed by product slug, not
 * kit slug - the Miswak entry is shared by both kits regardless of which
 * pack size they include.
 */
export const KIT_COMPONENT_COPY: Record<string, KitComponentCopy> = {
  miswak: {
    info: 'Естествена четка за зъби от корена на растението Salvadora persica.',
    benefit: 'Почиства и избелва без паста, вода или пластмаса.',
  },
  'bambukov-keis-za-miswak': {
    info: 'Калъф от естествен бамбук за ежедневно пренасяне на Miswak-а.',
    benefit: 'Пази го сух и чист между употребите, където и да си.',
  },
  'stargalka-za-ezik': {
    info: 'Медна стъргалка за езика, традиционен спътник на Miswak рутината.',
    benefit: 'Намалява бактериалния налеп и освежава дъха.',
  },
};

/**
 * Extra items every kit includes that aren't real catalog components (no
 * SKU/variant of their own, so they can't come from bundle.items) - shown
 * as a "БОНУС:" sub-list right under the regular contents checklist, both
 * on BundleRadioSelector's kit cards and KitProductPage's hero. Same three
 * items for both kits.
 */
export const KIT_BONUS_ITEMS: string[] = [
  'Ленена торбичка за съхранение и пренос на стъргалката за език',
  'Кратко ръководство за правилна употреба на Miswak',
  'PDF Наръчник за естествена грижа за устната кухина',
];
