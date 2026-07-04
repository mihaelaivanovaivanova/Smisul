import type { FormEvent, ReactNode } from 'react';

interface FormModalProps {
  show: boolean;
  title: string;
  isSubmitting?: boolean;
  error?: string | null;
  onSubmit: () => void;
  onClose: () => void;
  children: ReactNode;
}

/** Same controlled-modal approach as ConfirmModal, wrapping a <form> for create/edit dialogs. */
export default function FormModal({ show, title, isSubmitting = false, error, onSubmit, onClose, children }: FormModalProps) {
  if (!show) {
    return null;
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    onSubmit();
  }

  return (
    <>
      <div className="modal d-block" tabIndex={-1} role="dialog">
        <div className="modal-dialog modal-lg" role="document">
          <form className="modal-content" onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">{title}</h5>
              <button type="button" className="btn-close" aria-label="Close" onClick={onClose}></button>
            </div>
            <div className="modal-body d-flex flex-column gap-3">
              {error && <div className="alert alert-danger mb-0">{error}</div>}
              {children}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose} disabled={isSubmitting}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
                {isSubmitting && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
                Save
              </button>
            </div>
          </form>
        </div>
      </div>
      <div className="modal-backdrop show"></div>
    </>
  );
}
