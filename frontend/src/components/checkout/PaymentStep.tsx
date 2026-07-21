import { useEffect, useState } from 'react';
import { fetchPaymentMethods } from '../../api/checkout';
import { fetchStoredPaymentMethods } from '../../api/payment';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy } from '../../content/copy';
import type { Cart } from '../../types/cart';
import type { ShippingMethod } from '../../types/checkout';
import type { PaymentMethodOption, PaymentMethodValue, StoredPaymentMethod } from '../../types/payment';

interface PaymentStepProps {
  cart: Cart;
  shippingMethod: ShippingMethod | null;
  selectedMethod: PaymentMethodValue;
  onSelectMethod: (method: PaymentMethodValue) => void;
  storedPaymentMethodId: number | null;
  onSelectStoredPaymentMethod: (id: number | null) => void;
}

// Cash on delivery is temporarily withheld — see
// PaymentService::availablePaymentMethods() on the backend, the actual
// source of truth this list only mirrors for the rare case its fetch fails.
const fallbackMethods: PaymentMethodOption[] = [{ value: 'card', label: 'Плащане с карта' }];

const cardTrustMarks = ['Visa', 'Mastercard', 'Amex', 'Borica'];

export default function PaymentStep({
  cart,
  shippingMethod,
  selectedMethod,
  onSelectMethod,
  storedPaymentMethodId,
  onSelectStoredPaymentMethod,
}: PaymentStepProps) {
  const grandTotal = cart.totals.subtotal + (shippingMethod?.price ?? 0);
  const [methods, setMethods] = useState<PaymentMethodOption[] | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [storedMethods, setStoredMethods] = useState<StoredPaymentMethod[]>([]);

  useEffect(() => {
    let isMounted = true;
    fetchPaymentMethods()
      .then((result) => { if (isMounted) setMethods(result); })
      .catch(() => {
        if (isMounted) {
          setLoadError(true);
          setMethods(fallbackMethods);
        }
      })
      .finally(() => { if (isMounted) setIsLoading(false); });

    return () => { isMounted = false; };
  }, []);

  useEffect(() => {
    fetchStoredPaymentMethods().then(setStoredMethods).catch(() => setStoredMethods([]));
  }, []);

  useEffect(() => {
    if (methods && !methods.some((method) => method.value === selectedMethod)) onSelectMethod('card');
  }, [methods, onSelectMethod, selectedMethod]);

  return (
    <div>
      <h2 className="h6 mb-3">{checkoutCopy.paymentStep.title}</h2>
      <p className="text-muted">{checkoutCopy.paymentStep.description}</p>

      <div className="payment-panel mb-3">
        <div className="small text-muted mb-3">{checkoutCopy.paymentStep.methodLabel}</div>
        {isLoading && <div className="text-muted small">{checkoutCopy.paymentStep.methodsLoading}</div>}
        {!isLoading && loadError && <div className="text-danger small mb-2">{checkoutCopy.paymentStep.methodsLoadError}</div>}

        {!isLoading && methods && (
          <div className="d-flex flex-column gap-2">
            {methods.map((method) => {
              const isSelected = selectedMethod === method.value;
              return (
                <label key={method.value} className={`payment-option ${isSelected ? 'is-selected' : ''}`}>
                  <input
                    type="radio"
                    name="payment_method"
                    className="form-check-input mt-0"
                    checked={isSelected}
                    onChange={() => onSelectMethod(method.value)}
                  />
                  <span>
                    <span className="payment-option__label d-block">
                      {checkoutCopy.paymentStep.methods[method.value] ?? method.label}
                    </span>
                    <span className="payment-option__hint d-block">
                      {checkoutCopy.paymentStep.methodHints[method.value]}
                    </span>
                    {method.value === 'card' && (
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
                      </>
                    )}
                  </span>
                </label>
              );
            })}
          </div>
        )}

        {selectedMethod === 'card' && storedMethods.length > 0 && (
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
      </div>

      <div className="d-flex justify-content-between fw-bold fs-5 border-top pt-3">
        <span>{cartCopy.grandTotal}</span>
        <span>{formatPrice(grandTotal)}</span>
      </div>
    </div>
  );
}
