import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import FormField from '../components/FormField';
import SubmitButton from '../components/SubmitButton';
import Alert from '../components/Alert';
import { forgotPassword } from '../api/auth';
import { useFormSubmit } from '../hooks/useFormSubmit';
import { auth as authCopy } from '../content/copy';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const { isLoading, errors, formError, submit } = useFormSubmit();

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSuccessMessage(null);

    await submit(async () => {
      const message = await forgotPassword(email);
      setSuccessMessage(message);
    }, authCopy.forgotPassword.error);
  }

  return (
    <AuthCard title={authCopy.forgotPassword.title}>
      {successMessage && <Alert variant="success">{successMessage}</Alert>}
      {formError && <Alert variant="danger">{formError}</Alert>}

      {!successMessage && (
        <form onSubmit={(event) => void handleSubmit(event)} noValidate>
          <div className="mb-3">
            <FormField
              id="email"
              label={authCopy.forgotPassword.email}
              type="email"
              value={email}
              onChange={setEmail}
              error={errors.email}
              required
            />
          </div>

          <SubmitButton isLoading={isLoading} className="w-100">
            {authCopy.forgotPassword.submit}
          </SubmitButton>
        </form>
      )}

      <p className="text-center mt-3 mb-0">
        <Link to="/login">{authCopy.forgotPassword.backToLogin}</Link>
      </p>
    </AuthCard>
  );
}
