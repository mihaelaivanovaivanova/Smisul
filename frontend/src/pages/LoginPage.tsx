import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import FormField from '../components/FormField';
import SubmitButton from '../components/SubmitButton';
import Alert from '../components/Alert';
import { useAuth } from '../hooks/useAuth';
import { useFormSubmit } from '../hooks/useFormSubmit';

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
      navigate(state.from?.pathname ?? '/profile', { replace: true });
    }, 'Unable to log in. Please try again.');
  }

  return (
    <AuthCard title="Log in">
      {state.message && <Alert variant="success">{state.message}</Alert>}
      {formError && <Alert variant="danger">{formError}</Alert>}

      <form onSubmit={(event) => void handleSubmit(event)} noValidate>
        <div className="mb-3">
          <FormField id="email" label="Email address" type="email" value={email} onChange={setEmail} error={errors.email} required />
        </div>

        <div className="mb-3">
          <FormField
            id="password"
            label="Password"
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
              Remember me
            </label>
          </div>
          <Link to="/forgot-password">Forgot password?</Link>
        </div>

        <SubmitButton isLoading={isLoading} className="w-100">
          Log in
        </SubmitButton>
      </form>

      <p className="text-center mt-3 mb-0">
        Don&apos;t have an account? <Link to="/register">Register</Link>
      </p>
    </AuthCard>
  );
}
