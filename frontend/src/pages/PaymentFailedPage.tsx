import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { fetchPaymentStatus, initiatePayment, redirectToGateway } from '../api/payment';
import { getErrorMessage } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import { payment as paymentCopy } from '../content/copy';
import type { Payment } from '../types/payment';

export default function PaymentFailedPage() {
  const [searchParams] = useSearchParams();
  const orderId = Number(searchParams.get('order'));
  const token = searchParams.get('token') ?? undefined;

  const [payment, setPayment] = useState<Payment | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isRetrying, setIsRetrying] = useState(false);
  const [retryError, setRetryError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    fetchPaymentStatus(orderId, token)
      .then((result) => {
        if (isMounted) {
          setPayment(result);
        }
      })
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
      <Seo title={paymentCopy.failed.seoTitle} />

      {isLoading && <LoadingState message={paymentCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && payment && (
        <div className="row justify-content-center text-center">
          <div className="col-12 col-lg-6">
            <h1 className="h3 mb-3">{paymentCopy.failed.title}</h1>
            <p className="text-muted mb-4">{paymentCopy.failed.message}</p>

            {retryError && <div className="alert alert-danger">{retryError}</div>}

            <div className="d-flex justify-content-center gap-2 flex-wrap">
              <button type="button" className="btn btn-primary" onClick={() => void handleRetry()} disabled={isRetrying}>
                {isRetrying && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
                {paymentCopy.failed.retry}
              </button>
              <Link to={`/order-confirmation/${orderId}${token ? `?token=${token}` : ''}`} className="btn btn-outline-primary">
                {paymentCopy.failed.viewOrder}
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
