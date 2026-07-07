import { useState } from 'react';
import type { FormEvent } from 'react';
import { sendContactMessage } from '../api/contact';
import { useFormSubmit } from '../hooks/useFormSubmit';
import FormField from './FormField';
import FieldError from './FieldError';
import Alert from './Alert';
import { contactForm } from '../content/copy';

interface ContactModalProps {
  show: boolean;
  onClose: () => void;
}

/**
 * A controlled modal rendered with plain conditional markup, like
 * ConfirmModal/CookiePreferencesModal — simpler to drive from Footer's
 * own open/close state than syncing with Bootstrap's imperative Modal JS.
 */
export default function ContactModal({ show, onClose }: ContactModalProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const { isLoading, errors, formError, submit, reset } = useFormSubmit();

  if (!show) {
    return null;
  }

  function handleClose() {
    setName('');
    setEmail('');
    setMessage('');
    setSuccessMessage(null);
    reset();
    onClose();
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSuccessMessage(null);

    await submit(async () => {
      await sendContactMessage({ name, email, message });
      setSuccessMessage(contactForm.success);
      setName('');
      setEmail('');
      setMessage('');
    }, contactForm.error);
  }

  return (
    <>
      <div className="modal d-block" tabIndex={-1} role="dialog">
        <div className="modal-dialog" role="document">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title">{contactForm.title}</h5>
              <button type="button" className="btn-close" aria-label="Close" onClick={handleClose}></button>
            </div>

            <form onSubmit={(event) => void handleSubmit(event)} noValidate>
              <div className="modal-body">
                {successMessage && <Alert variant="success">{successMessage}</Alert>}
                {!successMessage && (
                  <>
                    <p className="text-muted">{contactForm.description}</p>
                    {formError && <Alert variant="danger">{formError}</Alert>}

                    <div className="mb-3">
                      <FormField id="contact-name" label={contactForm.name} value={name} onChange={setName} error={errors.name} required />
                    </div>

                    <div className="mb-3">
                      <FormField
                        id="contact-email"
                        label={contactForm.email}
                        type="email"
                        value={email}
                        onChange={setEmail}
                        error={errors.email}
                        required
                      />
                    </div>

                    <div className="mb-3">
                      <label htmlFor="contact-message" className="form-label">
                        {contactForm.message}
                      </label>
                      <textarea
                        id="contact-message"
                        className={`form-control ${errors.message ? 'is-invalid' : ''}`}
                        rows={4}
                        value={message}
                        onChange={(event) => setMessage(event.target.value)}
                        required
                      />
                      <FieldError message={errors.message} />
                    </div>
                  </>
                )}
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={handleClose}>
                  {contactForm.cancel}
                </button>
                {!successMessage && (
                  <button type="submit" className="btn btn-primary" disabled={isLoading}>
                    {isLoading && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
                    {isLoading ? contactForm.submitting : contactForm.submit}
                  </button>
                )}
              </div>
            </form>
          </div>
        </div>
      </div>
      <div className="modal-backdrop show"></div>
    </>
  );
}
