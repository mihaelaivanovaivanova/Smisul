import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCart } from '../../hooks/useCart';
import { getErrorMessage } from '../../api/errors';
import { cart as cartCopy } from '../../content/copy';
import QuantityStepper from '../cart/QuantityStepper';
import type { Inventory } from '../../types/product';

interface AddToCartButtonProps {
  productVariantId: number;
  inventory: Inventory | null | undefined;
}

/**
 * A generous, non-authoritative ceiling for items with no live
 * available_quantity to bound against (e.g. backorder-enabled variants,
 * where available_quantity is reported as 0 even though the item can
 * still be purchased) — just a friendlier stepper default. The backend
 * remains the real limit either way.
 */
const BACKORDER_SOFT_MAX = 10;

/**
 * available_quantity is a soft UI bound for the stepper (good UX, avoids
 * obviously-doomed requests) — it is NOT a reimplementation of the
 * backend's cart quantity rules (absolute per-item cap, backorders, etc).
 * The backend re-validates and is the sole source of truth; whatever it
 * rejects is surfaced verbatim below rather than pre-computed here.
 *
 * Render this with a `key={productVariantId}` from the parent (see
 * ProductPage) — that forces a remount whenever the selected variant
 * changes, so quantity/pending/error/success state never leaks from one
 * variant's attempt onto another.
 */
export default function AddToCartButton({ productVariantId, inventory }: AddToCartButtonProps) {
  const { addItem } = useCart();
  const [quantity, setQuantity] = useState(1);
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [added, setAdded] = useState(false);

  const canAdd = inventory?.is_in_stock ?? false;
  const availableQuantity = inventory?.available_quantity ?? 0;
  const maxQuantity = availableQuantity > 0 ? availableQuantity : BACKORDER_SOFT_MAX;

  if (!canAdd) {
    return null;
  }

  async function handleAdd(): Promise<void> {
    setIsPending(true);
    setError(null);
    setAdded(false);

    try {
      await addItem(productVariantId, quantity);
      setAdded(true);
      setQuantity(1);
    } catch (err) {
      setError(getErrorMessage(err, cartCopy.addToCartError));
    } finally {
      setIsPending(false);
    }
  }

  return (
    <div>
      <div className="d-flex align-items-center gap-3 mb-2">
        <QuantityStepper quantity={quantity} max={maxQuantity} disabled={isPending} onChange={setQuantity} />
        <button type="button" className="btn btn-primary" onClick={() => void handleAdd()} disabled={isPending}>
          {isPending && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
          {cartCopy.addToCart}
        </button>
      </div>

      <div aria-live="polite">
        {error && <div className="text-danger small">{error}</div>}
        {added && !error && (
          <div className="text-success small">
            {cartCopy.addedToCart} <Link to="/cart">{cartCopy.viewCart}</Link>
          </div>
        )}
      </div>
    </div>
  );
}
