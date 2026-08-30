import { useNavigate } from 'react-router-dom';
import AddToCartButton from '../product/AddToCartButton';
import { formatPrice } from '../../services/productCatalog';
import { trackFunnelAddToCart } from '../../services/analytics';
import { funnelOffer, stock as stockCopy } from '../../content/copy';
import { computeSavingsPercent } from '../../services/funnelOffers';
import type { PackageOffer } from '../../services/funnelOffers';

/**
 * The #pricing section's offer stack — one card per configured funnel
 * package, with live price, a real savings badge, and per-piece price
 * from the variant itself. The 5-pack (pack_size 5) is the featured/
 * visually-dominant card regardless of how many packages are configured
 * or what order they're in — not "whichever one is in the middle",
 * which broke the moment a 4th package (the 1-pack) was added.
 */
const packageImages: Record<number, string> = {
  1: '/funnel/v2/packages/pack-1.webp',
  3: '/funnel/v2/packages/pack-3.webp',
  5: '/funnel/v2/packages/pack-5.webp',
  10: '/funnel/v2/packages/pack-10.webp',
};

export default function PackageOffers({ offers, showImages = false }: { offers: PackageOffer[]; showImages?: boolean }) {
  const navigate = useNavigate();
  const featuredIndex = offers.findIndex(({ variant }) => variant.pack_size === 5);

  return (
    <div className="funnel-packages">
      {offers.map(({ pkg, variant, price }, index) => {
        // The single-stick price is itself a live, real price from this
        // same offers list (not hardcoded) — every bundle's savings badge
        // is computed against it, matching ai/context/14_Offer_and_Pricing.md's
        // own "Saving vs. single price" methodology. No fabricated
        // compare-at anchors: a previous version used
        // Price.compare_at_amount for this, but those values had no
        // documented basis anywhere in the project — removed at the data
        // level (see FunnelSeeder.php), not just hidden here.
        const savingsPercent = computeSavingsPercent(offers, variant, price);
        const hasImage = showImages && Boolean(packageImages[variant.pack_size]);

        return (
          <div
            className={`funnel-package-card${index === featuredIndex ? ' funnel-package-card--featured' : ''}${hasImage ? '' : ' funnel-package-card--no-image'}`}
            key={pkg.variant_id}
          >
            {hasImage && (
              <div className="funnel-package-card__image">
                <img
                  src={packageImages[variant.pack_size]}
                  alt={`Пакет от ${variant.pack_size} броя Miswak`}
                  width={600}
                  height={800}
                  loading="lazy"
                  decoding="async"
                />
              </div>
            )}
            <span className="funnel-package-card__badge">{pkg.badge}</span>
            <h3 className="funnel-package-card__detail h5 mb-0">{pkg.detail}</h3>
            <p className="funnel-package-card__value mb-0">{pkg.value_label}</p>
            <div className="funnel-package-card__price-row">
              <span className="funnel-package-card__price">{formatPrice(price.amount, price.currency)}</span>
              {savingsPercent !== null && savingsPercent > 0 && (
                <span className="funnel-package-card__save">-{savingsPercent}%</span>
              )}
            </div>
            {variant.pack_size > 1 && (
              <span className="funnel-package-card__per-unit">
                {funnelOffer.perUnit(formatPrice(price.amount / variant.pack_size, price.currency))}
              </span>
            )}
            {/* Honest urgency: rendered only when the inventory really is
                low — no fake countdowns, the backend's is_low_stock flag
                decides. */}
            {variant.inventory?.is_in_stock && variant.inventory.is_low_stock && variant.inventory.available_quantity > 0 && (
              <span className="funnel-package-card__stock">
                {stockCopy.lowStock(variant.inventory.available_quantity)}
              </span>
            )}
            <div className="funnel-package-card__cta">
              {variant.inventory?.is_in_stock ? (
                <AddToCartButton
                  key={variant.id}
                  productVariantId={variant.id}
                  inventory={variant.inventory}
                  label={pkg.button_text}
                  size="md"
                  hideQuantity
                  // Funnel-only: a "yes" goes straight to the cart page
                  // (with its checkout CTA) instead of leaving the visitor
                  // parked on the landing page — the storefront's product
                  // pages keep the stay-on-page behavior.
                  onAdded={() => {
                    trackFunnelAddToCart(price.amount, price.currency);
                    navigate('/cart');
                  }}
                />
              ) : (
                <span className="text-muted">{stockCopy.outOfStock}</span>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
