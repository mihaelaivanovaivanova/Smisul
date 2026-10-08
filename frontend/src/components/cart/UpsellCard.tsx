import { useState } from 'react';
import { Link } from 'react-router-dom';
import { getErrorMessage } from '../../api/errors';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy } from '../../content/copy';
import type { CartUpsellOffer } from '../../types/cart';

interface UpsellCardProps {
  offer: CartUpsellOffer;
  onAdd: (productVariantId: number, quantity: number, isUpsell?: boolean) => Promise<void>;
  /** Called after a successful add — e.g. to open the cart drawer from a product page, or close it when already inside one. */
  onAdded?: () => void;
  /** Called when the shopper follows the card's link to the product's own page — e.g. to close the drawer it's shown in. */
  onNavigate?: () => void;
  /** Extra class(es) on the card's root element — e.g. a standalone bordered look outside the drawer (checkout review, product pages). */
  className?: string;
}

/**
 * One cross-sell offer card: shown wherever CartService::upsellOffers()
 * says it's eligible right now (cart drawer, checkout review step, the
 * bamboo case's own product page) — disappears on its own once its product
 * is added, since that's exactly when the backend stops including it.
 * Extracted from CartDrawer.tsx's own CartUpsell so every placement renders
 * the exact same card instead of three near-duplicates.
 */
export default function UpsellCard({ offer, onAdd, onAdded, onNavigate, className = '' }: UpsellCardProps) {
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { product } = offer;
  const href = product.slug ? `/products/${product.slug}` : null;
  const savings = offer.compare_at_amount - offer.amount;

  async function handleAdd(): Promise<void> {
    setIsPending(true);
    setError(null);
    try {
      await onAdd(offer.variant_id, 1, true);
      onAdded?.();
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
    <div className={`cart-drawer-upsell ${className}`.trim()}>
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
