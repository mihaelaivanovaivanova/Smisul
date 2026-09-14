import { useEffect, useState } from 'react';
import { fetchStoredPaymentMethods } from '../../api/payment';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy } from '../../content/copy';
import type { Cart } from '../../types/cart';
import type { ShippingMethod } from '../../types/checkout';
import type { PaymentMethodValue, StoredPaymentMethod } from '../../types/payment';

interface PaymentStepProps {
  cart: Cart;
  shippingMethod: ShippingMethod | null;
  selectedMethod: PaymentMethodValue;
  /** The cash-on-delivery surcharge (see PaymentMethod::fee() on the backend) — irrelevant unless selectedMethod is actually cash_on_delivery. */
  cashOnDeliveryFee: number;
  storedPaymentMethodId: number | null;
  onSelectStoredPaymentMethod: (id: number | null) => void;
}

const cardTrustMarks = ['Visa', 'Mastercard', 'Amex', 'Borica'];

/**
 * The actual method choice now happens earlier, on the delivery step (see
 * DeliveryStep.tsx - it has to come before the carrier list there, since
 * cash on delivery constrains which carriers are even selectable). By the
 * time the customer reaches this final step the method is already locked
 * in; this just confirms it, offers a saved card for a card payment, and
 * shows the total right before the Pay button.
 */
export default function PaymentStep({ cart, shippingMethod, selectedMethod, cashOnDeliveryFee, storedPaymentMethodId, onSelectStoredPaymentMethod }: PaymentStepProps) {
  const codFee = selectedMethod === 'cash_on_delivery' ? cashOnDeliveryFee : 0;
  const grandTotal = cart.totals.subtotal + (shippingMethod?.price ?? 0) + codFee;
  const [storedMethods, setStoredMethods] = useState<StoredPaymentMethod[]>([]);

  useEffect(() => {
    fetchStoredPaymentMethods().then(setStoredMethods).catch(() => setStoredMethods([]));
  }, []);

  return (
    <div>
      <h2 className="h6 mb-3">{checkoutCopy.paymentStep.title}</h2>

      <div className="payment-panel mb-3">
        <div className="small text-muted mb-1">{checkoutCopy.paymentStep.methodLabel}</div>
        <div className="fw-semibold mb-3">{checkoutCopy.paymentStep.methods[selectedMethod]}</div>

        {selectedMethod === 'card' && (
          <>
            <span className="payment-option__secure-copy d-block">
              Плащането се обработва сигурно чрез iCard. Данните на картата не се съхраняват в нашия сайт.
            </span>
            <span className="payment-trust-marks" aria-label="Поддържани начини на плащане">
              {cardTrustMarks.map((mark) => <span className="payment-trust-mark" key={mark}>{mark}</span>)}
            </span>
            <span className="payment-wallet-marks" aria-label={checkoutCopy.paymentStep.walletsAccepted}>
              <img src="/payments/apple-pay.svg" alt="Apple Pay" height={30} loading="lazy" />
              <img src="/payments/google-pay.svg" alt="Google Pay" height={30} loading="lazy" />
            </span>

            {storedMethods.length > 0 && (
              <div className="mt-3">
                <label className="form-label">Запазена карта</label>
                <select
                  className="form-select"
                  value={storedPaymentMethodId ?? ''}
                  onChange={(event) => onSelectStoredPaymentMethod(event.target.value ? Number(event.target.value) : null)}
                >
                  <option value="">Нова карта (може да я запазите в iCard прозореца)</option>
                  {storedMethods.map((method) => (
                    <option value={method.id} key={method.id}>
                      {method.brand ?? 'Карта'} •••• {method.last_four ?? '????'}
                    </option>
                  ))}
                </select>
              </div>
            )}
          </>
        )}

        {selectedMethod === 'cash_on_delivery' && (
          <>
            <span className="payment-option__hint d-block">{checkoutCopy.paymentStep.methodHints.cash_on_delivery}</span>
            {codFee > 0 && (
              <span className="payment-option__hint d-block">
                {checkoutCopy.paymentStep.cashOnDeliveryFeeNote(formatPrice(codFee))}
              </span>
            )}
          </>
        )}
      </div>

      <div className="d-flex justify-content-between fw-bold fs-5 border-top pt-3">
        <span>{cartCopy.grandTotal}</span>
        <span>{formatPrice(grandTotal)}</span>
      </div>
    </div>
  );
}
