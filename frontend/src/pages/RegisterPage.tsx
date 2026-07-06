import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import AuthCard from '../components/AuthCard';
import FieldError from '../components/FieldError';
import FormField from '../components/FormField';
import SubmitButton from '../components/SubmitButton';
import Alert from '../components/Alert';
import { register as registerRequest } from '../api/auth';
import { useFormSubmit } from '../hooks/useFormSubmit';
import { auth as authCopy } from '../content/copy';

interface FormState {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  password: string;
  password_confirmation: string;
  newsletter_subscription: boolean;
  marketing_consent: boolean;
  gdpr_consent: boolean;
}

const initialState: FormState = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  newsletter_subscription: false,
  marketing_consent: false,
  gdpr_consent: false,
};

export default function RegisterPage() {
  const navigate = useNavigate();
  const [form, setForm] = useState<FormState>(initialState);
  const { isLoading, errors, formError, submit } = useFormSubmit();

  function updateField<K extends keyof FormState>(field: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    await submit(async () => {
      await registerRequest({
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email,
        phone: form.phone || undefined,
        password: form.password,
        password_confirmation: form.password_confirmation,
        newsletter_subscription: form.newsletter_subscription,
        marketing_consent: form.marketing_consent,
        gdpr_consent: form.gdpr_consent,
      });

      navigate('/login', {
        state: {
          message: authCopy.register.success,
        },
      });
    }, authCopy.register.error);
  }

  return (
    <AuthCard title={authCopy.register.title}>
      {formError && <Alert variant="danger">{formError}</Alert>}

      <form onSubmit={(event) => void handleSubmit(event)} noValidate>
        <div className="row">
          <div className="col-12 col-sm-6 mb-3">
            <FormField
              id="first_name"
              label={authCopy.register.firstName}
              value={form.first_name}
              onChange={(value) => updateField('first_name', value)}
              error={errors.first_name}
              required
            />
          </div>
          <div className="col-12 col-sm-6 mb-3">
            <FormField
              id="last_name"
              label={authCopy.register.lastName}
              value={form.last_name}
              onChange={(value) => updateField('last_name', value)}
              error={errors.last_name}
              required
            />
          </div>
        </div>

        <div className="mb-3">
          <FormField
            id="email"
            label={authCopy.register.email}
            type="email"
            value={form.email}
            onChange={(value) => updateField('email', value)}
            error={errors.email}
            required
          />
        </div>

        <div className="mb-3">
          <FormField
            id="phone"
            label={authCopy.register.phoneOptional}
            type="tel"
            value={form.phone}
            onChange={(value) => updateField('phone', value)}
            error={errors.phone}
          />
        </div>

        <div className="row">
          <div className="col-12 col-sm-6 mb-3">
            <FormField
              id="password"
              label={authCopy.register.password}
              type="password"
              value={form.password}
              onChange={(value) => updateField('password', value)}
              error={errors.password}
              required
            />
          </div>
          <div className="col-12 col-sm-6 mb-3">
            <FormField
              id="password_confirmation"
              label={authCopy.register.confirmPassword}
              type="password"
              value={form.password_confirmation}
              onChange={(value) => updateField('password_confirmation', value)}
              required
            />
          </div>
        </div>

        <div className="form-check mb-2">
          <input
            id="newsletter_subscription"
            type="checkbox"
            className="form-check-input"
            checked={form.newsletter_subscription}
            onChange={(event) => updateField('newsletter_subscription', event.target.checked)}
          />
          <label htmlFor="newsletter_subscription" className="form-check-label">
            {authCopy.register.newsletterSubscription}
          </label>
        </div>

        <div className="form-check mb-2">
          <input
            id="marketing_consent"
            type="checkbox"
            className="form-check-input"
            checked={form.marketing_consent}
            onChange={(event) => updateField('marketing_consent', event.target.checked)}
          />
          <label htmlFor="marketing_consent" className="form-check-label">
            {authCopy.register.marketingConsent}
          </label>
        </div>

        <div className="form-check mb-3">
          <input
            id="gdpr_consent"
            type="checkbox"
            className={`form-check-input ${errors.gdpr_consent ? 'is-invalid' : ''}`}
            checked={form.gdpr_consent}
            onChange={(event) => updateField('gdpr_consent', event.target.checked)}
            required
          />
          <label htmlFor="gdpr_consent" className="form-check-label">
            {authCopy.register.gdprConsent}
          </label>
          <FieldError message={errors.gdpr_consent} />
        </div>

        <SubmitButton isLoading={isLoading} className="w-100">
          {authCopy.register.submit}
        </SubmitButton>
      </form>

      <p className="text-center mt-3 mb-0">
        {authCopy.register.haveAccount} <Link to="/login">{authCopy.register.loginLink}</Link>
      </p>
    </AuthCard>
  );
}
