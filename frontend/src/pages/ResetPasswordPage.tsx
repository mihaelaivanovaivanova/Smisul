import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import FormField from '../components/FormField';
import SubmitButton from '../components/SubmitButton';
import Alert from '../components/Alert';
import { resetPassword } from '../api/auth';
import { useFormSubmit } from '../hooks/useFormSubmit';
import { auth as authCopy } from '../content/copy';

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
      navigate('/login', { state: { message: authCopy.resetPassword.success } });
    }, authCopy.resetPassword.error);
  }

  if (!token || !email) {
    return (
      <AuthCard title={authCopy.resetPassword.invalidLinkTitle}>
        <Alert variant="danger">{authCopy.resetPassword.invalidLink}</Alert>
        <p className="text-center mb-0">
          <Link to="/forgot-password">{authCopy.resetPassword.requestNewLink}</Link>
        </p>
      </AuthCard>
    );
  }

  return (
    <AuthCard title={authCopy.resetPassword.title}>
      {formError && <Alert variant="danger">{formError}</Alert>}

      <form onSubmit={(event) => void handleSubmit(event)} noValidate>
        <div className="mb-3">
          <label htmlFor="email" className="form-label">
            {authCopy.resetPassword.email}
          </label>
          <input id="email" type="email" className="form-control" value={email} disabled readOnly />
        </div>

        <div className="mb-3">
          <FormField
            id="password"
            label={authCopy.resetPassword.newPassword}
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
            label={authCopy.resetPassword.confirmNewPassword}
            type="password"
            value={passwordConfirmation}
            onChange={setPasswordConfirmation}
            required
          />
        </div>

        <SubmitButton isLoading={isLoading} className="w-100">
          {authCopy.resetPassword.submit}
        </SubmitButton>
      </form>
    </AuthCard>
  );
}
