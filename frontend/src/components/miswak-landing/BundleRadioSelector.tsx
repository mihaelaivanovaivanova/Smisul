import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../../hooks/useCart';
import { getErrorMessage } from '../../api/errors';
import { formatPrice } from '../../services/productCatalog';
import { trackFunnelAddToCart } from '../../services/analytics';
import { bundleOffer as bundleCopy, funnelOffer as funnelOfferCopy, stock as stockCopy } from '../../content/copy';
import { KIT_BONUS_ITEMS } from '../../content/kitProducts';
import type { BundleOffer } from '../../services/bundleOffers';

interface BundleRadioSelectorProps {
  offers: BundleOffer[];
  /** Owned by PurchasePanel, same pattern as PackageRadioSelector's selectedIndex. */
  selectedIndex: number;
  onSelect: (index: number) => void;
  /** Live admin-configured threshold (Settings → General) - same free-shipping badge rule as PackageRadioSelector. 0/undefined renders no badge. */
  freeShippingThreshold?: number;
}

/**
 * Kits as PackageRadioSelector-style radio options: pick one, then a
 * single button below adds that kit (POST /cart/bundles) - replaces the
 * earlier layout where every kit card carried its own independent Add
 * button, to match the "pick one, then Add to cart" buy box the rest of
 * this page uses for the raw Miswak packs.
 */
export default function BundleRadioSelector({ offers, selectedIndex, onSelect, freeShippingThreshold }: BundleRadioSelectorProps) {
  const { addBundle, openDrawer } = useCart();
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (offers.length === 0) {
    return null;
  }

  const selected = offers[Math.min(selectedIndex, offers.length - 1)];

  async function handleAdd(): Promise<void> {
    setIsPending(true);
    setError(null);

    try {
      await addBundle(selected.bundle.id, 1);
      openDrawer();
      trackFunnelAddToCart(selected.price, selected.currency);
    } catch (err) {
      setError(getErrorMessage(err, bundleCopy.addError));
    } finally {
      setIsPending(false);
    }
  }

  return (
    <div className="miswak-package-selector">
      {offers.map((offer, index) => {
        const { bundle, price, currency, retailValue, savingsPercent, description, contents, isAvailable } = offer;
        const isSelected = index === selectedIndex;
        const isFreeShipping = Boolean(freeShippingThreshold) && price >= freeShippingThreshold!;

        return (
          <label key={bundle.id} className={`miswak-package-option ${isSelected ? 'is-selected' : ''}`}>
            {(bundle.badge || isFreeShipping || !isAvailable) && (
              <span className="miswak-package-option__badges">
                {isFreeShipping && (
                  <span className="miswak-package-option__badge miswak-package-option__badge--shipping">
                    {funnelOfferCopy.freeShippingBadge}
                  </span>
                )}
                {!isAvailable && (
                  <span className="badge badge-stock badge-stock--out">{stockCopy.outOfStock}</span>
                )}
                {bundle.badge && <span className="miswak-package-option__badge">{bundle.badge}</span>}
              </span>
            )}
            <input
              type="radio"
              name="miswak-bundle"
              className="miswak-package-option__radio"
              checked={isSelected}
              onChange={() => onSelect(index)}
            />
            <span className="miswak-package-option__body">
              <span className="miswak-package-option__top">
                <Link to={`/products/${bundle.slug}`} className="miswak-package-option__detail miswak-package-option__detail--link">
                  {bundle.name}
                </Link>
                {savingsPercent !== null && savingsPercent > 0 && (
                  <span className="miswak-package-option__save miswak-package-option__save--kit">-{savingsPercent}%</span>
                )}
              </span>
              <span className="miswak-package-option__price-stack">
                <span className="miswak-package-option__price">{formatPrice(price, currency)}</span>
                {retailValue !== null && (
                  <span className="miswak-package-option__price-original">{formatPrice(retailValue, currency)}</span>
                )}
              </span>
              {(description || contents.length > 0) && (
                <span className="miswak-package-option__bottom">
                  {description && <span className="miswak-package-option__description">{description}</span>}
                  {contents.length > 0 && (
                    <ul className="miswak-package-option__contents">
                      {contents.map((line) => (
                        <li key={line} className="miswak-package-option__contents-item">
                          {line}
                        </li>
                      ))}
                    </ul>
                  )}
                </span>
              )}
              {KIT_BONUS_ITEMS.length > 0 && (
                // Its own grid row (see .miswak-package-option__body) so the
                // price-stack, which spans only the title+contents rows, can
                // vertically center against that height alone - the bonus
                // box sits below, excluded from that centering.
                <span className="miswak-package-option__bonus-section">
                  <span className="miswak-package-option__contents-label">🎁 {bundleCopy.bonusLabel} 🎁</span>
                  <ul className="miswak-package-option__bonus-box">
                    {KIT_BONUS_ITEMS.map((line) => (
                      <li key={line} className="miswak-package-option__contents-item miswak-package-option__contents-item--bonus">
                        {line}
                      </li>
                    ))}
                  </ul>
                </span>
              )}
            </span>
          </label>
        );
      })}

      <div className="miswak-package-selector__cta">
        <button
          type="button"
          className="btn btn-primary btn-lg"
          onClick={() => void handleAdd()}
          disabled={isPending || !selected.isAvailable}
        >
          {isPending && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
          {selected.isAvailable ? bundleCopy.addToCart(selected.bundle.name) : bundleCopy.outOfStock}
        </button>
        <div aria-live="polite">{error && <div className="text-danger small mt-1">{error}</div>}</div>
      </div>
    </div>
  );
}
