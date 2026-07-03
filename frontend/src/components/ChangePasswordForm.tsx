import { useState } from 'react';
import type { FormEvent } from 'react';
import FormField from './FormField';
import SubmitButton from './SubmitButton';
import Alert from './Alert';
import { updatePassword } from '../api/profile';
import { useFormSubmit } from '../hooks/useFormSubmit';

export default function ChangePasswordForm() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const { isLoading, errors, formError, submit } = useFormSubmit();

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSuccessMessage(null);

    await submit(async () => {
      const message = await updatePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      setSuccessMessage(message);
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
    }, 'Unable to update your password.');
  }

  return (
    <div className="card shadow-sm">
      <div className="card-body p-4">
        <h2 className="h5 mb-4">Change password</h2>

        {successMessage && <Alert variant="success">{successMessage}</Alert>}
        {formError && <Alert variant="danger">{formError}</Alert>}

        <form onSubmit={(event) => void handleSubmit(event)} noValidate>
          <div className="mb-3">
            <FormField
              id="current_password"
              label="Current password"
              type="password"
              value={currentPassword}
              onChange={setCurrentPassword}
              error={errors.current_password}
              required
            />
          </div>

          <div className="mb-3">
            <FormField
              id="new_password"
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
              id="new_password_confirmation"
              label="Confirm new password"
              type="password"
              value={passwordConfirmation}
              onChange={setPasswordConfirmation}
              required
            />
          </div>

          <SubmitButton isLoading={isLoading}>Update password</SubmitButton>
        </form>
      </div>
    </div>
  );
}
