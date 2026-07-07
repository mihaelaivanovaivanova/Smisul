import { useEffect, useState } from 'react';
import { fetchPaymentMethods } from '../../api/checkout';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy, checkout as checkoutCopy } from '../../content/copy';
import type { Cart } from '../../types/cart';
import type { ShippingMethod } from '../../types/checkout';
import type { PaymentMethodOption, PaymentMethodValue } from '../../types/payment';

interface PaymentStepProps {
  cart: Cart;
  shippingMethod: ShippingMethod | null;
  selectedMethod: PaymentMethodValue;
  onSelectMethod: (method: PaymentMethodValue) => void;
}

/**
 * Apple Pay's availability is a real, standard browser check
 * (`window.ApplePaySession`) built into Safari — no SDK load required for
 * just this — so the option can honestly be greyed out where it could
 * never work, rather than shown as clickable and failing later. Google
 * Pay has no equivalent zero-SDK check, so it's left selectable whenever
 * the backend says it's enabled.
 */
function isApplePayAvailableInBrowser(): boolean {
  return typeof window !== 'undefined' && 'ApplePaySession' in window;
}

/**
 * The last step before payment: totals recap and the payment method
 * selector. Once "pay" is pressed, CheckoutPage places the order and
 * renders the actual embedded iCard modal (card) or wallet SDK buttons
 * (Apple/Google Pay) in place of this step — see IcardModal/
 * IcardWalletButtons and docs/wallet-payments.md. The radio cards below
 * are just a preference selector, not the real wallet buttons themselves.
 */
export default function PaymentStep({ cart, shippingMethod, selectedMethod, onSelectMethod }: PaymentStepProps) {
  const grandTotal = cart.totals.subtotal + (shippingMethod?.price ?? 0);

  const [methods, setMethods] = useState<PaymentMethodOption[] | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    let isMounted = true;

    fetchPaymentMethods()
      .then((result) => {
        if (isMounted) setMethods(result);
      })
      .catch(() => {
        if (isMounted) {
          setLoadError(true);
          setMethods([{ value: 'card', label: checkoutCopy.paymentStep.methods.card }]);
        }
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  // If a previously-available method disappears (e.g. re-fetched after
  // being disabled) or nothing has been picked yet, fall back to card —
  // never leave the selection pointing at something no longer offered.
  useEffect(() => {
    if (methods && !methods.some((method) => method.value === selectedMethod)) {
      onSelectMethod('card');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [methods]);

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
              const isApplePayUnavailable = method.value === 'apple_pay' && !isApplePayAvailableInBrowser();

              return (
                <label
                  key={method.value}
                  className={`payment-option ${isSelected ? 'is-selected' : ''} ${isApplePayUnavailable ? 'is-disabled' : ''}`}
                >
                  <input
                    type="radio"
                    name="payment_method"
                    className="form-check-input mt-0"
                    checked={isSelected}
                    disabled={isApplePayUnavailable}
                    onChange={() => onSelectMethod(method.value)}
                  />
                  <span>
                    <span className="payment-option__label d-block">
                      {checkoutCopy.paymentStep.methods[method.value] ?? method.label}
                    </span>
                    <span className="payment-option__hint d-block">
                      {isApplePayUnavailable
                        ? checkoutCopy.paymentStep.applePayUnavailable
                        : checkoutCopy.paymentStep.methodHints[method.value]}
                    </span>
                  </span>
                </label>
              );
            })}
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
