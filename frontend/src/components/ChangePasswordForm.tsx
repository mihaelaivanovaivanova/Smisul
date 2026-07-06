import { useState } from 'react';
import type { FormEvent } from 'react';
import FormField from './FormField';
import SubmitButton from './SubmitButton';
import Alert from './Alert';
import { updatePassword } from '../api/profile';
import { useFormSubmit } from '../hooks/useFormSubmit';
import { profile as profileCopy } from '../content/copy';

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
    }, profileCopy.changePassword.error);
  }

  return (
    <div className="card shadow-sm">
      <div className="card-body p-4">
        <h2 className="h5 mb-4">{profileCopy.changePassword.heading}</h2>

        {successMessage && <Alert variant="success">{successMessage}</Alert>}
        {formError && <Alert variant="danger">{formError}</Alert>}

        <form onSubmit={(event) => void handleSubmit(event)} noValidate>
          <div className="mb-3">
            <FormField
              id="current_password"
              label={profileCopy.changePassword.currentPassword}
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
              label={profileCopy.changePassword.newPassword}
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
              label={profileCopy.changePassword.confirmNewPassword}
              type="password"
              value={passwordConfirmation}
              onChange={setPasswordConfirmation}
              required
            />
          </div>

          <SubmitButton isLoading={isLoading}>{profileCopy.changePassword.submit}</SubmitButton>
        </form>
      </div>
    </div>
  );
}
