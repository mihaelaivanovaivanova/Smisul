import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { loadIcardModalScript } from '../../api/payment';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import { checkout as checkoutCopy } from '../../content/copy';
import type { PaymentModalSession } from '../../types/payment';

interface IcardModalProps {
  session: PaymentModalSession;
  onSuccess: () => void;
  onError: () => void;
  onCancel: () => void;
}

/**
 * Renders the #ipg container iCard's own hosted JS mounts its payment
 * overlay into (see api/payment.ts::loadIcardModalScript) — the embedded
 * replacement for the old full-page redirect. Success/failure/cancel are
 * reported via DOM CustomEvents iCard's script dispatches on `document`,
 * not a page navigation, so this only ever calls back into CheckoutPage;
 * it never navigates anywhere itself.
 */
export default function IcardModal({ session, onSuccess, onError, onCancel }: IcardModalProps) {
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    let isMounted = true;

    loadIcardModalScript(session.modal_js_url, session.token, session.theme)
      .then(() => {
        if (isMounted) setIsLoading(false);
      })
      .catch(() => {
        if (isMounted) {
          setIsLoading(false);
          setLoadError(true);
        }
      });

    const handleSuccess = () => onSuccess();
    const handleError = () => onError();
    const handleCancel = () => onCancel();

    document.addEventListener('ipg.payment.success', handleSuccess, { once: true });
    document.addEventListener('ipg.user.close.on.success', handleSuccess, { once: true });
    document.addEventListener('ipg.payment.error', handleError, { once: true });
    document.addEventListener('ipg.user.close.on.error', handleError, { once: true });
    document.addEventListener('ipg.user.cancel', handleCancel, { once: true });

    return () => {
      isMounted = false;
      document.removeEventListener('ipg.payment.success', handleSuccess);
      document.removeEventListener('ipg.user.close.on.success', handleSuccess);
      document.removeEventListener('ipg.payment.error', handleError);
      document.removeEventListener('ipg.user.close.on.error', handleError);
      document.removeEventListener('ipg.user.cancel', handleCancel);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session.modal_js_url, session.token, session.theme]);

  return (
    <div className="payment-panel">
      {isLoading && <LoadingState message={checkoutCopy.paymentStep.modal.loading} />}
      {loadError && <ErrorState message={checkoutCopy.paymentStep.modal.loadError} />}
      {/* Portaled to document.body — iCard's own script paints a
          full-viewport overlay into this container, but Bootstrap's
          `.card` (an ancestor here) sets `position: relative`, which
          would hijack the overlay's positioning and confine/clip it to
          the card's box instead of the viewport if left in place. */}
      {createPortal(<div id="ipg" />, document.body)}
    </div>
  );
}
