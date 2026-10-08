import AccessoryProductPage from './AccessoryProductPage';
import { BAMBOO_CASE_SLUG, TONGUE_SCRAPER_SLUG } from '../content/accessoryProducts';
import type { AccessoryKeyFact } from './AccessoryProductPage';

export { TONGUE_SCRAPER_SLUG };

/** Key facts - taken from the product's own description. */
const KEY_FACTS: AccessoryKeyFact[] = [
  { icon: 'tooth', title: 'Свеж дъх', text: 'Намалява бактериалния налеп от повърхността на езика.' },
  { icon: 'shield', title: 'Мед', text: 'Традиционен материал за стъргалки, лесен за почистване.' },
  { icon: 'sparkle', title: 'Компактна', text: 'Побира се във всяка чанта или несесер.' },
];

/**
 * The tongue scraper's product page - styling and layout come from
 * AccessoryProductPage.tsx (shared with BambooCasePage.tsx); only the copy
 * below is specific to this product.
 */
export default function ScraperPage() {
  return (
    <AccessoryProductPage
      slug={TONGUE_SCRAPER_SLUG}
      eyebrow="Мястото, което Miswak не достига"
      keyFacts={KEY_FACTS}
      crossSell={{ slug: BAMBOO_CASE_SLUG, heading: 'Завърши рутината' }}
    />
  );
}
