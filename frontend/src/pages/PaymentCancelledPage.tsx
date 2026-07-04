import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { cancelPayment, initiatePayment, redirectToGateway } from '../api/payment';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import { payment as paymentCopy } from '../content/copy';

/**
 * Reached via ICARD_CANCEL_URL — the customer chose "cancel" on iCard's own
 * page. Calling cancelPayment() here is what actually confirms the
 * cancellation on our side (order + payment both move to Cancelled); this
 * page isn't just a passive display of already-settled state.
 */
export default function PaymentCancelledPage() {
  const [searchParams] = useSearchParams();
  const orderId = Number(searchParams.get('order'));
  const token = searchParams.get('token') ?? undefined;

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isRetrying, setIsRetrying] = useState(false);
  const [retryError, setRetryError] = useState<string | null>(null);
  const requestRef = useRef<ReturnType<typeof cancelPayment> | null>(null);

  useEffect(() => {
    // cancelPayment() has real side effects (moves order/payment to
    // Cancelled) — the request itself must only ever be sent once, so it's
    // memoized in a ref rather than re-sent by StrictMode's dev-only double
    // effect invocation. Each effect invocation still attaches its own
    // isMounted-scoped handler to that same shared promise (see
    // PaymentSuccessPage for why skipping the second invocation entirely
    // would be wrong).
    let isMounted = true;

    requestRef.current ??= cancelPayment(orderId, token);

    requestRef.current
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, paymentCopy.loadError));
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [orderId, token]);

  async function handleRetry(): Promise<void> {
    setIsRetrying(true);
    setRetryError(null);

    try {
      const retried = await initiatePayment(orderId, token);

      if (retried.redirect_url) {
        redirectToGateway(retried);
        return;
      }
    } catch (err) {
      setRetryError(getErrorMessage(err, paymentCopy.retryError));
    } finally {
      setIsRetrying(false);
    }
  }

  return (
    <div className="container py-5">
      <Seo title={paymentCopy.cancelled.seoTitle} />

      {isLoading && <LoadingState message={paymentCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && (
        <div className="row justify-content-center text-center">
          <div className="col-12 col-lg-6">
            <h1 className="h3 mb-3">{paymentCopy.cancelled.title}</h1>
            <p className="text-muted mb-4">{paymentCopy.cancelled.message}</p>

            {retryError && <div className="alert alert-danger">{retryError}</div>}

            <div className="d-flex justify-content-center gap-2 flex-wrap">
              <button type="button" className="btn btn-primary" onClick={() => void handleRetry()} disabled={isRetrying}>
                {isRetrying && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
                {paymentCopy.cancelled.retry}
              </button>
              <Link to="/search" className="btn btn-outline-primary">
                {paymentCopy.cancelled.continueShopping}
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
