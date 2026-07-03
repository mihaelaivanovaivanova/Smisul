import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy } from '../../content/copy';
import type { Cart } from '../../types/cart';
import type { ShippingMethod } from '../../types/checkout';

interface PaymentStepProps {
  cart: Cart;
  shippingMethod: ShippingMethod | null;
}

/**
 * The last step before iCard: everything here is display-only (totals
 * recap) plus the actual "pay" trigger, which CheckoutPage wires to the
 * same handlePlaceOrder() that creates the order and redirects — there's
 * no separate "confirm" action between them since iCard's hosted page is
 * itself the confirmation step.
 */
export default function PaymentStep({ cart, shippingMethod }: PaymentStepProps) {
  const grandTotal = cart.totals.subtotal + (shippingMethod?.price ?? 0);

  return (
    <div>
      <h2 className="h6 mb-3">{checkoutCopy.paymentStep.title}</h2>
      <p className="text-muted">{checkoutCopy.paymentStep.description}</p>

      <div className="border rounded-3 p-3 mb-3">
        <div className="d-flex justify-content-between small text-muted mb-1">
          <span>{checkoutCopy.paymentStep.methodLabel}</span>
        </div>
        <div className="fw-semibold">{checkoutCopy.paymentStep.methodValue}</div>
      </div>

      <div className="d-flex justify-content-between fw-bold fs-5 border-top pt-3">
        <span>{cartCopy.grandTotal}</span>
        <span>{formatPrice(grandTotal)}</span>
      </div>
    </div>
  );
}
