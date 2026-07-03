import { useState } from 'react';
import { Link } from 'react-router-dom';
import type { CartItem } from '../../types/cart';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy } from '../../content/copy';
import { getErrorMessage } from '../../api/errors';
import { useCart } from '../../hooks/useCart';
import QuantityStepper from './QuantityStepper';

interface CartItemRowProps {
  item: CartItem;
}

export default function CartItemRow({ item }: CartItemRowProps) {
  const { updateItem, removeItem } = useCart();
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const variant = item.product_variant;
  const product = variant.product;
  const image = product?.primary_image;

  async function handleQuantityChange(quantity: number): Promise<void> {
    setIsPending(true);
    setError(null);

    try {
      await updateItem(item.id, quantity);
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
      await removeItem(item.id);
    } catch (err) {
      setError(getErrorMessage(err, cartCopy.removeError));
      setIsPending(false);
    }
  }

  return (
    <div className="d-flex gap-3 py-3 border-bottom">
      <Link to={`/products/${product?.slug ?? ''}`} className="flex-shrink-0">
        <div className="ratio ratio-1x1 bg-white rounded-2 border" style={{ width: 80 }}>
          {image ? (
            <img src={image.url} alt={image.alt_text ?? product?.name ?? ''} className="object-fit-cover" />
          ) : (
            <div className="d-flex align-items-center justify-content-center text-muted small">
              {cartCopy.noImage}
            </div>
          )}
        </div>
      </Link>

      <div className="flex-grow-1">
        <div className="d-flex justify-content-between gap-2">
          <div>
            <Link to={`/products/${product?.slug ?? ''}`} className="text-decoration-none text-body fw-semibold">
              {product?.name ?? variant.name}
            </Link>
            {variant.name && <div className="text-muted small">{variant.name}</div>}
          </div>
          <div className="text-end">
            <div className="fw-semibold">{item.unit_price !== null ? formatPrice(item.line_total) : '—'}</div>
            {item.unit_price !== null && (
              <div className="text-muted small">{formatPrice(item.unit_price)} / бр.</div>
            )}
          </div>
        </div>

        {!item.is_available && (
          <div className="text-danger small mt-1">{cartCopy.unavailable}</div>
        )}

        <div className="d-flex align-items-center justify-content-between mt-2 gap-2">
          <QuantityStepper
            quantity={item.quantity}
            max={Math.max(item.max_quantity, item.quantity)}
            disabled={isPending}
            onChange={(quantity) => void handleQuantityChange(quantity)}
          />
          <button
            type="button"
            className="btn btn-link btn-sm text-danger text-decoration-none p-0"
            onClick={() => void handleRemove()}
            disabled={isPending}
            aria-label={cartCopy.removeAria(product?.name ?? variant.name)}
          >
            {cartCopy.remove}
          </button>
        </div>

        {error && <div className="text-danger small mt-1">{error}</div>}
      </div>
    </div>
  );
}
