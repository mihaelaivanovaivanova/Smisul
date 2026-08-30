import { computeSavingsPercent } from '../../services/funnelOffers';
import type { PackageOffer } from '../../services/funnelOffers';
import { formatPrice } from '../../services/productCatalog';
import { funnelOffer } from '../../content/copy';

interface PackageOfferSummaryProps {
  /** The active variant's own offer, if the admin configured a funnel package for it — renders nothing otherwise (ProductPage falls back to plain PriceBlock). */
  offer: PackageOffer | undefined;
  /** The full offer list — savings % is computed against the 1-pack price within it, see computeSavingsPercent(). */
  offers: PackageOffer[];
}

/**
 * Replaces PriceBlock for whichever pack size is currently selected,
 * whenever that variant has a funnel package configured for it — badge on
 * its own line, then price + discount together, then per-unit price, then
 * the package's one-line tagline. Order is by request, matching how the
 * funnel landing page's own package cards read top to bottom.
 */
export default function PackageOfferSummary({ offer, offers }: PackageOfferSummaryProps) {
  if (!offer) {
    return null;
  }

  const { pkg, variant, price } = offer;
  const savingsPercent = computeSavingsPercent(offers, variant, price);

  return (
    <div className="product-package-summary">
      <span className="product-package-summary__badge">{pkg.badge}</span>
      <div className="product-package-summary__price-row">
        <span className="product-package-summary__price">{formatPrice(price.amount, price.currency)}</span>
        {savingsPercent !== null && savingsPercent > 0 && (
          <span className="product-package-summary__save">-{savingsPercent}%</span>
        )}
      </div>
      {variant.pack_size > 1 && (
        <span className="product-package-summary__per-unit">
          {funnelOffer.perUnit(formatPrice(price.amount / variant.pack_size, price.currency))}
        </span>
      )}
      <p className="product-package-summary__value">{pkg.value_label}</p>
    </div>
  );
}
