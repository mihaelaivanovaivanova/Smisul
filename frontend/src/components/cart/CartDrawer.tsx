import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../../hooks/useCart';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage } from '../../api/errors';
import { fetchCartUpsell } from '../../api/cart';
import { fetchPublicSettings } from '../../api/settings';
import { formatPrice, getVariantImage } from '../../services/productCatalog';
import { cart as cartCopy, funnelAssurance } from '../../content/copy';
import Icon from '../icons/Icon';
import QuantityStepper from './QuantityStepper';
import type { CartItem, CartUpsellOffer } from '../../types/cart';

function productHref(slug: string | undefined): string | null {
  return slug ? `/products/${slug}` : null;
}

/**
 * Slide-in cart drawer, opened by AddToCartButton on every successful add
 * instead of navigating straight to /cart (see cart-context.ts). Hand-rolled
 * modal markup (not Bootstrap's offcanvas), matching the existing
 * ExitIntentModal/AddReviewWizard convention: a raw backdrop div with
 * onClick-to-close plus an Escape keydown listener, just anchored to the
 * right edge instead of centered.
 */
export default function CartDrawer() {
  const { cart, isLoading, isDrawerOpen, closeDrawer, updateItem, removeItem, addItem } = useCart();

  const items = cart?.items ?? [];

  // Re-fetched whenever the drawer opens or the cart itself changes (add/
  // remove/quantity) — the backend is the sole source of eligibility (see
  // CartService::upsellOffer()), so this never duplicates that logic
  // client-side.
  const { data: upsellOffer } = useAsync(
    () => (isDrawerOpen ? fetchCartUpsell() : Promise.resolve(null)),
    [isDrawerOpen, cart],
    '',
  );

  // Fetched once per page load (this component stays mounted, just hidden,
  // while the drawer is closed) — not gated on isDrawerOpen like the upsell
  // fetch above, since the public settings payload is small and shared by
  // other chrome (footer, Box Now banner) that may want it too later.
  const { data: publicSettings } = useAsync(fetchPublicSettings, [], '');
  const freeShippingThreshold = publicSettings?.free_shipping_threshold ?? 0;

  useEffect(() => {
    if (!isDrawerOpen) {
      return;
    }

    function handleKeydown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        closeDrawer();
      }
    }

    document.addEventListener('keydown', handleKeydown);
    return () => document.removeEventListener('keydown', handleKeydown);
  }, [isDrawerOpen, closeDrawer]);

  if (!isDrawerOpen) {
    return null;
  }

  const isEmpty = !isLoading && items.length === 0;
  const totalSavings = items.reduce((sum, item) => sum + itemSavings(item), 0);
  const subtotal = cart?.totals.subtotal ?? 0;

  return (
    <>
      <div className="cart-drawer-backdrop" onClick={closeDrawer} />
      <aside
        className="cart-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-drawer-title"
      >
        <div className="cart-drawer__header">
          <h2 className="cart-drawer__title" id="cart-drawer-title">
            {cartCopy.title}
          </h2>
          <button
            type="button"
            className="cart-drawer__close"
            onClick={closeDrawer}
            aria-label={cartCopy.closeAria}
          >
            <Icon name="cross" />
          </button>
        </div>

        {!isEmpty && freeShippingThreshold > 0 && (
          <FreeShippingBar threshold={freeShippingThreshold} subtotal={subtotal} />
        )}

        {isEmpty ? (
          <div className="cart-drawer__empty">
            <p className="cart-drawer__empty-title">{cartCopy.empty.title}</p>
            <p className="cart-drawer__empty-message">{cartCopy.empty.message}</p>
          </div>
        ) : (
          <>
            <div className="cart-drawer__items">
              {items.map((item) => (
                <CartDrawerItem
                  key={item.id}
                  item={item}
                  onUpdate={updateItem}
                  onRemove={removeItem}
                  onNavigate={closeDrawer}
                />
              ))}
            </div>

            {upsellOffer && <CartUpsell offer={upsellOffer} onAdd={addItem} onNavigate={closeDrawer} />}

            <div className="cart-drawer__footer">
              <div className="cart-drawer__total-row">
                <span>{cartCopy.grandTotal}</span>
                <span>{cart ? formatPrice(cart.totals.grand_total) : ''}</span>
              </div>
              {totalSavings > 0 && cart && (
                <div className="cart-drawer__total-savings-row">
                  <span className="cart-drawer__total-savings">{cartCopy.savings(formatPrice(totalSavings))}</span>
                  <span className="price__compare">{formatPrice(cart.totals.grand_total + totalSavings)}</span>
                </div>
              )}

              <Link to="/checkout" className="btn btn-primary cart-drawer__checkout" onClick={closeDrawer}>
                {cartCopy.checkoutCta}
              </Link>

              <div className="cart-drawer__payment-logos" role="img" aria-label={funnelAssurance.paymentLogosAria}>
                <img src="/payments/apple-pay.svg" alt="Apple Pay" height={22} loading="lazy" />
                <img src="/payments/google-pay.svg" alt="Google Pay" height={22} loading="lazy" />
                <img src="/payments/visa-2021.svg" alt="Visa" height={14} loading="lazy" />
                <img src="/payments/mastercard.svg" alt="Mastercard" height={22} loading="lazy" />
              </div>
            </div>
          </>
        )}
      </aside>
    </>
  );
}

/** Per-line savings — null-safe against is_on_sale/compare_at_unit_price, never fabricated. */
function itemSavings(item: CartItem): number {
  if (!item.is_on_sale || item.compare_at_unit_price === null) {
    return 0;
  }
  return (item.compare_at_unit_price - (item.unit_price ?? item.compare_at_unit_price)) * item.quantity;
}

interface CartDrawerItemProps {
  item: CartItem;
  onUpdate: (itemId: number, quantity: number) => Promise<void>;
  onRemove: (itemId: number) => Promise<void>;
  /** Closes the drawer when the shopper follows the item's link to its product page — otherwise the drawer stays open, covering the page underneath. */
  onNavigate: () => void;
}

function CartDrawerItem({ item, onUpdate, onRemove, onNavigate }: CartDrawerItemProps) {
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const variant = item.product_variant;
  const product = variant.product;
  const image = getVariantImage(variant);
  const savings = itemSavings(item);
  const href = productHref(product?.slug);

  async function handleQuantityChange(quantity: number): Promise<void> {
    setIsPending(true);
    setError(null);
    try {
      await onUpdate(item.id, quantity);
    } catch (err) {
      setError(getErrorMessage(err, cartCopy.updateError));
    } finally {
      setIsPending(false);
    }
  }

  async function handleRemove(): Promise<void> {
    setIsPending(true);
    setError(null);
    try {
      await onRemove(item.id);
    } catch (err) {
      setError(getErrorMessage(err, cartCopy.removeError));
      setIsPending(false);
    }
  }

  const compareLineTotal =
    item.is_on_sale && item.compare_at_unit_price !== null ? item.compare_at_unit_price * item.quantity : null;

  const imageNode = image ? (
    <img src={image.url} alt={image.alt_text ?? product?.name ?? ''} />
  ) : (
    <span className="cart-drawer-item__no-image">{cartCopy.noImage}</span>
  );
  const nameText = product?.name ?? variant.name;

  return (
    <div className="cart-drawer-item">
      {href ? (
        <Link to={href} className="cart-drawer-item__image" onClick={onNavigate}>
          {imageNode}
        </Link>
      ) : (
        <div className="cart-drawer-item__image">{imageNode}</div>
      )}

      <div className="cart-drawer-item__body">
        <div className="cart-drawer-item__header">
          {href ? (
            <Link to={href} className="cart-drawer-item__name" onClick={onNavigate}>
              {nameText}
            </Link>
          ) : (
            <div className="cart-drawer-item__name">{nameText}</div>
          )}
          <button
            type="button"
            className="cart-drawer-item__remove"
            onClick={() => void handleRemove()}
            disabled={isPending}
            aria-label={cartCopy.removeAria(product?.name ?? variant.name)}
          >
            <Icon name="trash" />
          </button>

          {variant.name && <div className="cart-drawer-item__variant">{variant.name}</div>}

          <div className="cart-drawer-item__price-stack">
            <span className={`cart-drawer-item__price${item.is_on_sale ? ' is-sale' : ''}`}>
              {item.unit_price !== null ? formatPrice(item.unit_price) : '—'}
            </span>
            {item.is_on_sale && item.compare_at_unit_price !== null && (
              <span className="price__compare">{formatPrice(item.compare_at_unit_price)}</span>
            )}
          </div>
        </div>

        {!item.is_available && <div className="text-danger small mt-1">{cartCopy.unavailable}</div>}

        <div className="cart-drawer-item__controls">
          <QuantityStepper
            variant="flat"
            quantity={item.quantity}
            max={Math.max(item.max_quantity, item.quantity)}
            disabled={isPending}
            onChange={(quantity) => void handleQuantityChange(quantity)}
          />

          {/* line_total is quantity * the same unit price already shown in
              the price row above (see CartItemResource::lineTotal()) - at
              quantity 1 they're always identical, so showing both just
              duplicates the same number. Only worth a second line once the
              quantity actually makes them diverge. */}
          {item.quantity > 1 && (
            <div className="cart-drawer-item__totals">
              <div className="cart-drawer-item__total-price">
                {compareLineTotal !== null && <span className="price__compare">{formatPrice(compareLineTotal)}</span>}
                <span className={`cart-drawer-item__total-amount${item.is_on_sale ? ' is-sale' : ''}`}>
                  {formatPrice(item.line_total)}
                </span>
              </div>
              {savings > 0 && <div className="cart-drawer-item__savings">{cartCopy.savings(formatPrice(savings))}</div>}
            </div>
          )}
        </div>

        {error && <div className="text-danger small mt-1">{error}</div>}
      </div>
    </div>
  );
}

interface CartUpsellProps {
  offer: CartUpsellOffer;
  onAdd: (productVariantId: number, quantity: number, isUpsell?: boolean) => Promise<void>;
  /** Closes the drawer when the shopper follows the card's link to the case's product page. */
  onNavigate: () => void;
}

/** The bamboo-case cross-sell card — disappears on its own once the case is added, since that's exactly when the backend stops returning an offer (see CartService::upsellOffer()). */
function CartUpsell({ offer, onAdd, onNavigate }: CartUpsellProps) {
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { product } = offer;
  const href = productHref(product.slug);
  const savings = offer.compare_at_amount - offer.amount;

  async function handleAdd(): Promise<void> {
    setIsPending(true);
    setError(null);
    try {
      await onAdd(offer.variant_id, 1, true);
    } catch (err) {
      setError(getErrorMessage(err, cartCopy.upsell.addError));
    } finally {
      setIsPending(false);
    }
  }

  const imageNode = product.primary_image ? (
    <img src={product.primary_image.url} alt={product.primary_image.alt_text ?? product.name} />
  ) : (
    <span className="cart-drawer-item__no-image">{cartCopy.noImage}</span>
  );

  return (
    <div className="cart-drawer-upsell">
      {href ? (
        <Link to={href} className="cart-drawer-upsell__image" onClick={onNavigate}>
          {imageNode}
        </Link>
      ) : (
        <div className="cart-drawer-upsell__image">{imageNode}</div>
      )}

      <div className="cart-drawer-upsell__body">
        <div className="cart-drawer-upsell__heading">{cartCopy.upsell.heading}</div>
        {href ? (
          <Link to={href} className="cart-drawer-upsell__name" onClick={onNavigate}>
            {product.name}
          </Link>
        ) : (
          <div className="cart-drawer-upsell__name">{product.name}</div>
        )}
        <div className="cart-drawer-upsell__price-row">
          <span className="price__compare">{formatPrice(offer.compare_at_amount, offer.currency)}</span>
          <span className="cart-drawer-upsell__price is-sale">{formatPrice(offer.amount, offer.currency)}</span>
        </div>
        {savings > 0 && <div className="cart-drawer-upsell__savings">{cartCopy.savings(formatPrice(savings))}</div>}
        {error && <div className="text-danger small mt-1">{error}</div>}
      </div>

      <button
        type="button"
        className="btn btn-outline-primary btn-sm cart-drawer-upsell__add"
        onClick={() => void handleAdd()}
        disabled={isPending}
      >
        {isPending ? cartCopy.upsell.adding : cartCopy.upsell.add}
      </button>
    </div>
  );
}

interface FreeShippingBarProps {
  /** Whole-EUR cart subtotal that unlocks free shipping — admin-configured, see general.free_shipping_threshold. Caller already checked this is > 0. */
  threshold: number;
  subtotal: number;
}

/** The "spend €X more for free shipping" progress nudge, shown right under the drawer header for any non-empty cart. */
function FreeShippingBar({ threshold, subtotal }: FreeShippingBarProps) {
  const remaining = Math.max(0, threshold - subtotal);
  const isUnlocked = remaining === 0;
  const percent = Math.min(100, (subtotal / threshold) * 100);
  // Keeps the truck marker's circle visually inside the track at the
  // extremes instead of half-clipping past either edge.
  const markerPosition = Math.min(96, Math.max(4, percent));

  return (
    <div className="cart-drawer-shipping-bar">
      <p className="cart-drawer-shipping-bar__message">
        {isUnlocked ? (
          <>
            {cartCopy.freeShipping.unlockedPrefix}{' '}
            <span className="cart-drawer-shipping-bar__unlocked">{cartCopy.freeShipping.unlockedHighlight}</span>
          </>
        ) : (
          cartCopy.freeShipping.remaining(formatPrice(remaining))
        )}
      </p>
      <div className="cart-drawer-shipping-bar__track">
        <div className="cart-drawer-shipping-bar__fill" style={{ width: `${percent}%` }} />
        <div className="cart-drawer-shipping-bar__marker" style={{ left: `${markerPosition}%` }}>
          <Icon name="truck" />
        </div>
      </div>
      <p className="cart-drawer-shipping-bar__goal">{cartCopy.freeShipping.goal(formatPrice(threshold))}</p>
    </div>
  );
}
