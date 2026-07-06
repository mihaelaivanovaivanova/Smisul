import { useEffect, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import Alert from '../components/Alert';
import { verifyEmail } from '../api/auth';
import { getErrorMessage } from '../api/errors';
import { auth as authCopy } from '../content/copy';

type Status = 'verifying' | 'success' | 'error';

export default function VerifyEmailPage() {
  const { id, hash } = useParams<{ id: string; hash: string }>();
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState<Status>('verifying');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const expires = searchParams.get('expires');
    const signature = searchParams.get('signature');

    if (!id || !hash || !expires || !signature) {
      setStatus('error');
      setMessage(authCopy.verifyEmail.invalidLink);
      return;
    }

    let isMounted = true;

    verifyEmail(id, hash, { expires, signature })
      .then((responseMessage) => {
        if (isMounted) {
          setStatus('success');
          setMessage(responseMessage);
        }
      })
      .catch((error: unknown) => {
        if (isMounted) {
          setStatus('error');
          setMessage(getErrorMessage(error, authCopy.verifyEmail.error));
        }
      });

    return () => {
      isMounted = false;
    };
  }, [id, hash, searchParams]);

  return (
    <AuthCard title={authCopy.verifyEmail.title}>
      {status === 'verifying' && (
        <div className="text-center py-3">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">{authCopy.verifyEmail.verifying}</span>
          </div>
        </div>
      )}
      {status === 'success' && <Alert variant="success">{message}</Alert>}
      {status === 'error' && <Alert variant="danger">{message}</Alert>}

      <p className="text-center mt-3 mb-0">
        <Link to="/login">{authCopy.verifyEmail.goToLogin}</Link>
      </p>
    </AuthCard>
  );
}
