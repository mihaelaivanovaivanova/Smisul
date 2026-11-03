import { useState } from 'react';
import { Link } from 'react-router-dom';
import AddToCartButton from '../product/AddToCartButton';
import StockStatus from '../product/StockStatus';
import { useCart } from '../../hooks/useCart';
import { getErrorMessage } from '../../api/errors';
import { computeOriginalPrice, computeSavingsPercent } from '../../services/funnelOffers';
import { formatPrice } from '../../services/productCatalog';
import { trackFunnelAddToCart } from '../../services/analytics';
import { bundleOffer as bundleCopy, funnelOffer as funnelOfferCopy, stock as stockCopy } from '../../content/copy';
import { KIT_BONUS_ITEMS } from '../../content/kitProducts';
import type { BundleOffer } from '../../services/bundleOffers';
import type { PackageOffer } from '../../services/funnelOffers';

export type UnifiedEntry = { type: 'bundle'; offer: BundleOffer } | { type: 'pack'; offer: PackageOffer };

interface UnifiedPackageSelectorProps {
  entries: UnifiedEntry[];
  /** Owned by PurchasePanel, which also uses it to swap the gallery to the selected entry's own photo(s). */
  selectedIndex: number;
  onSelect: (index: number) => void;
  /** Live admin-configured threshold (Settings → General) - a pack/kit at/above it gets the free-shipping badge. 0/undefined renders no badge. */
  freeShippingThreshold?: number;
}

/**
 * One flat "pick one, then Add to cart" list mixing both kits and raw
 * Miswak packs - replaces the earlier Комплекти/Само Miswak tab toggle
 * (BundleRadioSelector/PackageRadioSelector shown one at a time) by
 * request, so every option sits in a single sequence instead of being
 * split behind a switch. PurchasePanel builds `entries` in the exact
 * display order wanted (1-Person Kit, 5-pack, 1-pack, 3-pack, 10-pack,
 * 2-Person Kit) - this component just renders whatever order it's given
 * and doesn't re-sort.
 *
 * Still exactly one shared radio group (one selection across both kinds)
 * and exactly one CTA below the list - which kind of Add button shows
 * depends on the selected entry's own type, since a kit adds via
 * POST /cart/bundles (useCart().addBundle) while a pack adds via the
 * ordinary AddToCartButton/useCart().addItem.
 */
export default function UnifiedPackageSelector({ entries, selectedIndex, onSelect, freeShippingThreshold }: UnifiedPackageSelectorProps) {
  const { addBundle, openDrawer } = useCart();
  const [isBundlePending, setIsBundlePending] = useState(false);
  const [bundleError, setBundleError] = useState<string | null>(null);

  if (entries.length === 0) {
    return null;
  }

  const selected = entries[Math.min(selectedIndex, entries.length - 1)];
  // computeSavingsPercent/computeOriginalPrice compare a pack against its
  // single-stick sibling - kits never factor in, so this is built once
  // rather than re-filtered on every pack row below.
  const packOffers = entries.filter((entry): entry is { type: 'pack'; offer: PackageOffer } => entry.type === 'pack').map((entry) => entry.offer);

  async function handleAddBundle(offer: BundleOffer): Promise<void> {
    setIsBundlePending(true);
    setBundleError(null);

    try {
      await addBundle(offer.bundle.id, 1);
      openDrawer();
      trackFunnelAddToCart(offer.price, offer.currency);
    } catch (err) {
      setBundleError(getErrorMessage(err, bundleCopy.addError));
    } finally {
      setIsBundlePending(false);
    }
  }

  return (
    <div className="miswak-package-selector">
      {entries.map((entry, index) => {
        const isSelected = index === selectedIndex;

        if (entry.type === 'bundle') {
          const { bundle, price, currency, retailValue, savingsPercent, description, contents, isAvailable } = entry.offer;
          const isFreeShipping = Boolean(freeShippingThreshold) && price >= freeShippingThreshold!;

          return (
            <label key={`bundle-${bundle.id}`} className={`miswak-package-option ${isSelected ? 'is-selected' : ''}`}>
              {(bundle.badge || isFreeShipping || !isAvailable) && (
                <span className="miswak-package-option__badges">
                  {isFreeShipping && (
                    <span className="miswak-package-option__badge miswak-package-option__badge--shipping">
                      {funnelOfferCopy.freeShippingBadge}
                    </span>
                  )}
                  {!isAvailable && <span className="badge badge-stock badge-stock--out">{stockCopy.outOfStock}</span>}
                  {bundle.badge && <span className="miswak-package-option__badge">{bundle.badge}</span>}
                </span>
              )}
              <input
                type="radio"
                name="miswak-unified-package"
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
        }

        const { pkg, variant, price } = entry.offer;
        const savingsPercent = computeSavingsPercent(packOffers, variant, price);
        const originalPrice = computeOriginalPrice(packOffers, variant, price);
        const isFreeShipping = Boolean(freeShippingThreshold) && price.amount >= freeShippingThreshold!;

        return (
          <label key={`pack-${pkg.variant_id}`} className={`miswak-package-option ${isSelected ? 'is-selected' : ''}`}>
            {(pkg.badge || isFreeShipping) && (
              <span className="miswak-package-option__badges">
                {isFreeShipping && (
                  <span className="miswak-package-option__badge miswak-package-option__badge--shipping">
                    {funnelOfferCopy.freeShippingBadge}
                  </span>
                )}
                {pkg.badge && <span className="miswak-package-option__badge">{pkg.badge}</span>}
              </span>
            )}
            <input
              type="radio"
              name="miswak-unified-package"
              className="miswak-package-option__radio"
              checked={isSelected}
              onChange={() => onSelect(index)}
            />
            <span className="miswak-package-option__body">
              <span className="miswak-package-option__top">
                <span className="miswak-package-option__detail">{pkg.detail}</span>
                {savingsPercent !== null && savingsPercent > 0 && <span className="miswak-package-option__save">-{savingsPercent}%</span>}
              </span>
              <span className="miswak-package-option__price-stack">
                <span className="miswak-package-option__price">{formatPrice(price.amount, price.currency)}</span>
                {originalPrice && (
                  <span className="miswak-package-option__price-original">{formatPrice(originalPrice.amount, originalPrice.currency)}</span>
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

      {selected.type === 'pack' && (
        <div className="miswak-package-selector__stock">
          <StockStatus inventory={selected.offer.variant.inventory} />
        </div>
      )}

      <div className="miswak-package-selector__cta">
        {selected.type === 'bundle' ? (
          <>
            <button
              type="button"
              className="btn btn-primary btn-lg"
              onClick={() => void handleAddBundle(selected.offer)}
              disabled={isBundlePending || !selected.offer.isAvailable}
            >
              {isBundlePending && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
              {selected.offer.isAvailable ? bundleCopy.addToCart(selected.offer.bundle.name) : bundleCopy.outOfStock}
            </button>
            <div aria-live="polite">{bundleError && <div className="text-danger small mt-1">{bundleError}</div>}</div>
          </>
        ) : (
          <AddToCartButton
            key={selected.offer.variant.id}
            productVariantId={selected.offer.variant.id}
            inventory={selected.offer.variant.inventory}
            label={selected.offer.pkg.button_text}
            size="lg"
            hideQuantity
            onAdded={() => trackFunnelAddToCart(selected.offer.price.amount, selected.offer.price.currency)}
          />
        )}
      </div>
    </div>
  );
}
