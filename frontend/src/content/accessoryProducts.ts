/**
 * Slugs for the two Miswak accessory products (seeded by
 * MiswakAccessoriesSeeder.php), each on its own styled page
 * (AccessoryProductPage.tsx) rather than the default product layout.
 * Kept in one place so BambooCasePage.tsx, ScraperPage.tsx and
 * ProductPage.tsx's routing guard never drift from each other, and so
 * neither page has to import the other just to cross-sell it.
 */
export const BAMBOO_CASE_SLUG = 'bambukov-keis-za-miswak';
export const TONGUE_SCRAPER_SLUG = 'stargalka-za-ezik';
