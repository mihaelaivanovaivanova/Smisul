import AccessoryProductPage from './AccessoryProductPage';
import { BAMBOO_CASE_SLUG, TONGUE_SCRAPER_SLUG } from '../content/accessoryProducts';
import type { AccessoryKeyFact } from './AccessoryProductPage';

export { BAMBOO_CASE_SLUG };

/** Key facts - taken from the product's own description. */
const KEY_FACTS: AccessoryKeyFact[] = [
  { icon: 'sparkle', title: 'Вентилация', text: 'Два отвора пазят Miswak-а сух между употребите.' },
  { icon: 'shield', title: 'Защита', text: 'Пази от прах и от съдържанието на чантата.' },
  { icon: 'leaf', title: 'Естествен бамбук', text: 'MOSO бамбук за многократна употреба.' },
];

/**
 * The bamboo Miswak case's product page - styling and layout come from
 * AccessoryProductPage.tsx (shared with ScraperPage.tsx); only the copy
 * below is specific to this product.
 */
export default function BambooCasePage() {
  return (
    <AccessoryProductPage
      slug={BAMBOO_CASE_SLUG}
      eyebrow="Не оставяй Miswak-а без дом"
      keyFacts={KEY_FACTS}
      crossSell={{ slug: TONGUE_SCRAPER_SLUG, heading: 'Завърши рутината' }}
    />
  );
}
