import { useNavigate } from 'react-router-dom';
import AddToCartButton from '../product/AddToCartButton';
import { formatPrice } from '../../services/productCatalog';
import { trackFunnelAddToCart } from '../../services/analytics';
import { funnelOffer, stock as stockCopy } from '../../content/copy';
import type { PackageOffer } from '../../services/funnelOffers';

/**
 * The #buy section's offer stack — one card per configured funnel package,
 * with live price, compare-at anchor, savings badge, and per-piece price
 * from the variant itself. With the classic full 3-card stack the middle
 * card is the featured (visually anchored) offer; any other count renders
 * all cards as equals.
 */
const packageImages: Record<number, string> = {
  3: '/funnel/v2/packages/pack-3.webp',
  5: '/funnel/v2/packages/pack-5.webp',
  10: '/funnel/v2/packages/pack-10.webp',
};

export default function PackageOffers({ offers, showImages = false }: { offers: PackageOffer[]; showImages?: boolean }) {
  const navigate = useNavigate();
  const featuredIndex = offers.length === 3 ? 1 : -1;

  return (
    <div className="funnel-packages">
      {offers.map(({ pkg, variant, price }, index) => {
        const savingsPercent =
          price.is_on_sale && price.compare_at_amount !== null && price.compare_at_amount > 0
            ? Math.round((1 - price.amount / price.compare_at_amount) * 100)
            : null;

        return (
          <div
            className={`funnel-package-card${index === featuredIndex ? ' funnel-package-card--featured' : ''}`}
            key={pkg.variant_id}
          >
            {showImages && packageImages[variant.pack_size] && (
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
              {savingsPercent !== null && price.compare_at_amount !== null && (
                <>
                  <s className="funnel-package-card__compare">{formatPrice(price.compare_at_amount, price.currency)}</s>
                  <span className="funnel-package-card__save">-{savingsPercent}%</span>
                </>
              )}
            </div>
            {variant.pack_size > 1 && (
              <span className="funnel-package-card__per-unit">
                {funnelOffer.perUnit(formatPrice(price.amount / variant.pack_size, price.currency))}
              </span>
            )}
            {pkg.duration_label && <span className="funnel-package-card__duration">{pkg.duration_label}</span>}
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
