import { useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import Alert from '../components/Alert';
import { confirmReview } from '../api/reviews';
import { getErrorMessage } from '../api/errors';
import { reviews as reviewsCopy } from '../content/copy';

type Status = 'verifying' | 'success' | 'error';

/**
 * Mirrors VerifyEmailPage.tsx's shape exactly — same signed-backend-URL-
 * rehosted-on-the-frontend pattern (see ReviewConfirmationNotification's
 * own docblock), just for the guest "Add a review" wizard's confirmation
 * link instead of account email verification.
 */
export default function ReviewConfirmPage() {
  const { reviewId } = useParams<{ reviewId: string }>();
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState<Status>('verifying');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const expires = searchParams.get('expires');
    const signature = searchParams.get('signature');

    if (!reviewId || !expires || !signature) {
      setStatus('error');
      setMessage(reviewsCopy.confirmPage.invalidLink);
      return;
    }

    let isMounted = true;

    confirmReview(reviewId, { expires, signature })
      .then(() => {
        if (isMounted) {
          setStatus('success');
          setMessage(reviewsCopy.confirmPage.success);
        }
      })
      .catch((error: unknown) => {
        if (isMounted) {
          setStatus('error');
          setMessage(getErrorMessage(error, reviewsCopy.confirmPage.error));
        }
      });

    return () => {
      isMounted = false;
    };
  }, [reviewId, searchParams]);

  return (
    <AuthCard title={reviewsCopy.confirmPage.title}>
      {status === 'verifying' && (
        <div className="text-center py-3">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">{reviewsCopy.confirmPage.verifying}</span>
          </div>
        </div>
      )}
      {status === 'success' && <Alert variant="success">{message}</Alert>}
      {status === 'error' && <Alert variant="danger">{message}</Alert>}

      <p className="text-center mt-3 mb-0">
        <Link to="/">{reviewsCopy.confirmPage.goHome}</Link>
      </p>
    </AuthCard>
  );
}
