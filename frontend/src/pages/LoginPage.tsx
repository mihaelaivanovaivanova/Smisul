import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import FormField from '../components/FormField';
import SubmitButton from '../components/SubmitButton';
import Alert from '../components/Alert';
import { useAuth } from '../hooks/useAuth';
import { useFormSubmit } from '../hooks/useFormSubmit';
import { auth as authCopy } from '../content/copy';

interface LocationState {
  message?: string;
  from?: { pathname: string };
}

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const state = (location.state ?? {}) as LocationState;

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const { isLoading, errors, formError, submit } = useFormSubmit();

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    await submit(async () => {
      await login({ email, password, remember });
      navigate(state.from?.pathname ?? '/', { replace: true });
    }, authCopy.login.error);
  }

  return (
    <AuthCard title={authCopy.login.title}>
      {state.message && <Alert variant="success">{state.message}</Alert>}
      {formError && <Alert variant="danger">{formError}</Alert>}

      <form onSubmit={(event) => void handleSubmit(event)} noValidate>
        <div className="mb-3">
          <FormField
            id="email"
            label={authCopy.login.email}
            type="email"
            value={email}
            onChange={setEmail}
            error={errors.email}
            required
          />
        </div>

        <div className="mb-3">
          <FormField
            id="password"
            label={authCopy.login.password}
            type="password"
            value={password}
            onChange={setPassword}
            error={errors.password}
            required
          />
        </div>

        <div className="d-flex justify-content-between align-items-center mb-3">
          <div className="form-check">
            <input
              id="remember"
              type="checkbox"
              className="form-check-input"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
            />
            <label htmlFor="remember" className="form-check-label">
              {authCopy.login.rememberMe}
            </label>
          </div>
          <Link to="/forgot-password">{authCopy.login.forgotPassword}</Link>
        </div>

        <SubmitButton isLoading={isLoading} className="w-100">
          {authCopy.login.submit}
        </SubmitButton>
      </form>

      <p className="text-center mt-3 mb-0">
        {authCopy.login.noAccount} <Link to="/register">{authCopy.login.registerLink}</Link>
      </p>
    </AuthCard>
  );
}
