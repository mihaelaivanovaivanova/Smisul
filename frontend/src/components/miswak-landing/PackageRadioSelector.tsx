import { useNavigate } from 'react-router-dom';
import AddToCartButton from '../product/AddToCartButton';
import StockStatus from '../product/StockStatus';
import { computeOriginalPrice, computeSavingsPercent } from '../../services/funnelOffers';
import type { PackageOffer } from '../../services/funnelOffers';
import { formatPrice } from '../../services/productCatalog';
import { funnelOffer as funnelOfferCopy } from '../../content/copy';
import { trackFunnelAddToCart } from '../../services/analytics';

interface PackageRadioSelectorProps {
  offers: PackageOffer[];
  /** Controlled by PurchasePanel, which also uses it to swap the gallery to the selected variant's own photo (see getGalleryImagesForVariant). */
  selectedIndex: number;
  onSelect: (index: number) => void;
}

/**
 * The reference's tiered "pick one, then Add to cart" package picker —
 * functionally different from the rest of the site's PackageOffers.tsx
 * (where every card carries its own independent Add-to-cart button):
 * here exactly one tier is selected via radio, and a single button below
 * adds that tier's variant. Scoped to this page only, per the approved
 * plan — the live funnel page keeps its existing per-card buy buttons.
 */
export default function PackageRadioSelector({ offers, selectedIndex, onSelect }: PackageRadioSelectorProps) {
  const navigate = useNavigate();

  if (offers.length === 0) {
    return null;
  }

  const selected = offers[Math.min(selectedIndex, offers.length - 1)];

  return (
    <div className="miswak-package-selector">
      {offers.map(({ pkg, variant, price }, index) => {
        const savingsPercent = computeSavingsPercent(offers, variant, price);
        const originalPrice = computeOriginalPrice(offers, variant, price);
        const isSelected = index === selectedIndex;

        return (
          <label
            key={pkg.variant_id}
            className={`miswak-package-option ${isSelected ? 'is-selected' : ''}`}
          >
            {pkg.badge && <span className="miswak-package-option__badge">{pkg.badge}</span>}
            <input
              type="radio"
              name="miswak-package"
              className="miswak-package-option__radio"
              checked={isSelected}
              onChange={() => onSelect(index)}
            />
            <span className="miswak-package-option__body">
              <span className="miswak-package-option__top">
                <span className="miswak-package-option__detail">{pkg.detail}</span>
                {savingsPercent !== null && savingsPercent > 0 && (
                  <span className="miswak-package-option__save">-{savingsPercent}%</span>
                )}
              </span>
              <span className="miswak-package-option__price-stack">
                <span className="miswak-package-option__price">{formatPrice(price.amount, price.currency)}</span>
                {originalPrice && (
                  <span className="miswak-package-option__price-original">
                    {formatPrice(originalPrice.amount, originalPrice.currency)}
                  </span>
                )}
              </span>
              <span className="miswak-package-option__bottom">
                <span className="miswak-package-option__value">{pkg.value_label}</span>
                {variant.pack_size > 1 && (
                  <span className="miswak-package-option__per-unit">
                    {funnelOfferCopy.perUnit(formatPrice(price.amount / variant.pack_size, price.currency))}
                  </span>
                )}
              </span>
            </span>
          </label>
        );
      })}

      <div className="miswak-package-selector__stock">
        <StockStatus inventory={selected.variant.inventory} />
      </div>

      <div className="miswak-package-selector__cta">
        <AddToCartButton
          key={selected.variant.id}
          productVariantId={selected.variant.id}
          inventory={selected.variant.inventory}
          label={selected.pkg.button_text}
          size="lg"
          hideQuantity
          onAdded={() => {
            trackFunnelAddToCart(selected.price.amount, selected.price.currency);
            navigate('/cart');
          }}
        />
      </div>
    </div>
  );
}
