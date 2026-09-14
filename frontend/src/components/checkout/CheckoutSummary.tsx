import type { Cart } from '../../types/cart';
import type { ShippingMethod } from '../../types/checkout';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy } from '../../content/copy';

interface CheckoutSummaryProps {
  cart: Cart;
  shippingMethod: ShippingMethod | null;
  /** The cash-on-delivery surcharge, already zeroed out by the caller unless that method is actually selected (see CheckoutPage). */
  codFee: number;
}

export default function CheckoutSummary({ cart, shippingMethod, codFee }: CheckoutSummaryProps) {
  const grandTotal = cart.totals.subtotal + (shippingMethod?.price ?? 0) + codFee;

  return (
    <div className="card shadow-sm">
      <div className="card-body">
        <h2 className="h6 mb-3">{cartCopy.grandTotal}</h2>

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

        <hr />

        <div className="d-flex justify-content-between fw-bold fs-5">
          <span>{cartCopy.grandTotal}</span>
          <span>{formatPrice(grandTotal)}</span>
        </div>
      </div>
    </div>
  );
}
