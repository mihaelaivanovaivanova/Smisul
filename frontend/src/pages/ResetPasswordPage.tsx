import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import FormField from '../components/FormField';
import SubmitButton from '../components/SubmitButton';
import Alert from '../components/Alert';
import { resetPassword } from '../api/auth';
import { useFormSubmit } from '../hooks/useFormSubmit';

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const { isLoading, errors, formError, submit } = useFormSubmit();

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    await submit(async () => {
      await resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      navigate('/login', { state: { message: 'Your password has been reset. Please log in.' } });
    }, 'Unable to reset your password. The link may have expired.');
  }

  if (!token || !email) {
    return (
      <AuthCard title="Reset password">
        <Alert variant="danger">This password reset link is invalid or incomplete.</Alert>
        <p className="text-center mb-0">
          <Link to="/forgot-password">Request a new link</Link>
        </p>
      </AuthCard>
    );
  }

  return (
    <AuthCard title="Reset your password">
      {formError && <Alert variant="danger">{formError}</Alert>}

      <form onSubmit={(event) => void handleSubmit(event)} noValidate>
        <div className="mb-3">
          <label htmlFor="email" className="form-label">
            Email address
          </label>
          <input id="email" type="email" className="form-control" value={email} disabled readOnly />
        </div>

        <div className="mb-3">
          <FormField
            id="password"
            label="New password"
            type="password"
            value={password}
            onChange={setPassword}
            error={errors.password}
            required
          />
        </div>

        <div className="mb-3">
          <FormField
            id="password_confirmation"
            label="Confirm new password"
            type="password"
            value={passwordConfirmation}
            onChange={setPasswordConfirmation}
            required
          />
        </div>

        <SubmitButton isLoading={isLoading} className="w-100">
          Reset password
        </SubmitButton>
      </form>
    </AuthCard>
  );
}
