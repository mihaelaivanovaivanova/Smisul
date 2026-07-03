import { useState } from 'react';
import type { FormEvent } from 'react';
import FormField from './FormField';
import SubmitButton from './SubmitButton';
import Alert from './Alert';
import { updateProfile } from '../api/profile';
import { resendVerificationEmail } from '../api/auth';
import { getErrorMessage } from '../api/errors';
import { useFormSubmit } from '../hooks/useFormSubmit';
import type { User } from '../types/auth';

interface ProfileDetailsFormProps {
  user: User;
  onUpdated: (user: User) => void;
}

export default function ProfileDetailsForm({ user, onUpdated }: ProfileDetailsFormProps) {
  const [firstName, setFirstName] = useState(user.first_name);
  const [lastName, setLastName] = useState(user.last_name);
  const [email, setEmail] = useState(user.email);
  const [phone, setPhone] = useState(user.phone ?? '');
  const [newsletter, setNewsletter] = useState(user.newsletter_subscription);
  const [marketing, setMarketing] = useState(user.marketing_consent);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const { isLoading, errors, formError, submit } = useFormSubmit();

  const [isResending, setIsResending] = useState(false);
  const [resendMessage, setResendMessage] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSuccessMessage(null);

    await submit(async () => {
      const updated = await updateProfile({
        first_name: firstName,
        last_name: lastName,
        email,
        phone: phone || null,
        newsletter_subscription: newsletter,
        marketing_consent: marketing,
      });
      onUpdated(updated);
      setSuccessMessage(
        updated.email !== user.email
          ? 'Profile updated. Since you changed your email, please verify it again.'
          : 'Profile updated successfully.',
      );
    }, 'Unable to update your profile.');
  }

  async function handleResendVerification() {
    setIsResending(true);
    setResendMessage(null);

    try {
      const message = await resendVerificationEmail();
      setResendMessage(message);
    } catch (error) {
      setResendMessage(getErrorMessage(error, 'Unable to resend the verification email.'));
    } finally {
      setIsResending(false);
    }
  }

  return (
    <div className="card shadow-sm">
      <div className="card-body p-4">
        <h2 className="h5 mb-4">Profile details</h2>

        {successMessage && <Alert variant="success">{successMessage}</Alert>}
        {formError && <Alert variant="danger">{formError}</Alert>}

        {!user.email_verified_at && (
          <Alert variant="warning">
            Your email address is not verified.{' '}
            <button
              type="button"
              className="btn btn-link p-0 align-baseline"
              onClick={() => void handleResendVerification()}
              disabled={isResending}
            >
              Resend verification email
            </button>
            {resendMessage && <div className="small mt-1">{resendMessage}</div>}
          </Alert>
        )}

        <form onSubmit={(event) => void handleSubmit(event)} noValidate>
          <div className="row">
            <div className="col-12 col-sm-6 mb-3">
              <FormField
                id="first_name"
                label="First name"
                value={firstName}
                onChange={setFirstName}
                error={errors.first_name}
                required
              />
            </div>
            <div className="col-12 col-sm-6 mb-3">
              <FormField id="last_name" label="Last name" value={lastName} onChange={setLastName} error={errors.last_name} required />
            </div>
          </div>

          <div className="mb-3">
            <FormField id="email" label="Email address" type="email" value={email} onChange={setEmail} error={errors.email} required />
          </div>

          <div className="mb-3">
            <FormField id="phone" label="Phone" type="tel" value={phone} onChange={setPhone} error={errors.phone} />
          </div>

          <div className="form-check mb-2">
            <input
              id="newsletter_subscription"
              type="checkbox"
              className="form-check-input"
              checked={newsletter}
              onChange={(event) => setNewsletter(event.target.checked)}
            />
            <label htmlFor="newsletter_subscription" className="form-check-label">
              Subscribe to the newsletter
            </label>
          </div>

          <div className="form-check mb-3">
            <input
              id="marketing_consent"
              type="checkbox"
              className="form-check-input"
              checked={marketing}
              onChange={(event) => setMarketing(event.target.checked)}
            />
            <label htmlFor="marketing_consent" className="form-check-label">
              I agree to receive marketing communications
            </label>
          </div>

          <SubmitButton isLoading={isLoading}>Save changes</SubmitButton>
        </form>
      </div>
    </div>
  );
}
