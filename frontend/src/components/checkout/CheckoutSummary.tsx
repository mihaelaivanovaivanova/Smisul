import { useState } from 'react';
import type { Cart } from '../../types/cart';
import type { ShippingMethod } from '../../types/checkout';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy } from '../../content/copy';
import { validateDiscountCode, type DiscountCodeValidation } from '../../api/checkout';
import { getErrorMessage } from '../../api/errors';

interface CheckoutSummaryProps {
  cart: Cart;
  shippingMethod: ShippingMethod | null;
  /** The cash-on-delivery surcharge, already zeroed out by the caller unless that method is actually selected (see CheckoutPage). */
  codFee: number;
  appliedDiscount: DiscountCodeValidation | null;
  onDiscountApplied: (discount: DiscountCodeValidation) => void;
  onDiscountRemoved: () => void;
}

export default function CheckoutSummary({
  cart,
  shippingMethod,
  codFee,
  appliedDiscount,
  onDiscountApplied,
  onDiscountRemoved,
}: CheckoutSummaryProps) {
  const [codeInput, setCodeInput] = useState('');
  const [isApplying, setIsApplying] = useState(false);
  const [codeError, setCodeError] = useState<string | null>(null);

  const discountAmount = appliedDiscount ? (cart.totals.subtotal * appliedDiscount.discount_percent) / 100 : 0;
  const grandTotal = cart.totals.subtotal - discountAmount + (shippingMethod?.price ?? 0) + codFee;

  async function handleApplyCode(): Promise<void> {
    const code = codeInput.trim();
    if (code === '') {
      return;
    }

    setIsApplying(true);
    setCodeError(null);

    try {
      const result = await validateDiscountCode(code);
      onDiscountApplied(result);
      setCodeInput('');
    } catch (err) {
      setCodeError(getErrorMessage(err, cartCopy.discountCode.invalid));
    } finally {
      setIsApplying(false);
    }
  }

  function handleRemoveCode(): void {
    onDiscountRemoved();
    setCodeError(null);
  }

  return (
    <div className="card shadow-sm">
      <div className="card-body">
        <h2 className="h6 mb-3">{cartCopy.grandTotal}</h2>

        <div className="d-flex flex-column gap-1 mb-2">
          {cart.items.map((item) => (
            <div className="d-flex justify-content-between small text-muted" key={item.id}>
              <span>
                {item.product_variant.product?.name ?? item.product_variant.name}
                {item.product_variant.name ? ` (${item.product_variant.name})` : ''} × {item.quantity}
              </span>
              <span>{formatPrice(item.line_total)}</span>
            </div>
          ))}
        </div>

        <hr className="my-2" />

        <div className="d-flex justify-content-between small mb-2">
          <span className="text-muted">
            {cartCopy.itemsHeading} ({cart.total_quantity})
          </span>
          <span>{formatPrice(cart.totals.subtotal)}</span>
        </div>

        <div className="d-flex justify-content-between small mb-2">
          <span className="text-muted">{cartCopy.shipping}</span>
          <span>{shippingMethod ? formatPrice(shippingMethod.price) : cartCopy.shippingCalculatedLater}</span>
        </div>

        {codFee > 0 && (
          <div className="d-flex justify-content-between small mb-2">
            <span className="text-muted">{checkoutCopy.paymentStep.methods.cash_on_delivery}</span>
            <span>+{formatPrice(codFee)}</span>
          </div>
        )}

        {appliedDiscount && (
          <div className="d-flex justify-content-between small mb-2 text-success">
            <span>
              {cartCopy.discount} ({appliedDiscount.code})
            </span>
            <span>-{formatPrice(discountAmount)}</span>
          </div>
        )}

        <div className="mb-2">
          {appliedDiscount ? (
            <div className="d-flex align-items-center justify-content-between small">
              <span className="text-success">{cartCopy.discountCode.applied(appliedDiscount.discount_percent)}</span>
              <button type="button" className="btn btn-link btn-sm p-0" onClick={handleRemoveCode}>
                {cartCopy.discountCode.remove}
              </button>
            </div>
          ) : (
            <div className="input-group input-group-sm">
              <input
                type="text"
                className="form-control"
                placeholder={cartCopy.discountCode.placeholder}
                value={codeInput}
                onChange={(event) => setCodeInput(event.target.value)}
                disabled={isApplying}
              />
              <button
                type="button"
                className="btn btn-outline-secondary"
                onClick={() => void handleApplyCode()}
                disabled={isApplying || codeInput.trim() === ''}
              >
                {isApplying ? cartCopy.discountCode.applying : cartCopy.discountCode.apply}
              </button>
            </div>
          )}
          {codeError && <div className="small text-danger mt-1">{codeError}</div>}
        </div>

        <hr />

        <div className="d-flex justify-content-between fw-bold fs-5">
          <span>{cartCopy.grandTotal}</span>
          <span>{formatPrice(grandTotal)}</span>
        </div>
      </div>
    </div>
  );
}
