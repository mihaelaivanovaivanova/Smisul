import { useState } from 'react';
import { createDiscountCode, deleteDiscountCode, fetchAdminDiscountCodes, updateDiscountCode } from '../../api/admin/discountCodes';
import type { DiscountCodePayload } from '../../api/admin/discountCodes';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import FormModal from '../../components/admin/FormModal';
import ConfirmModal from '../../components/admin/ConfirmModal';
import FieldError from '../../components/FieldError';
import type { AdminDiscountCode } from '../../types/admin';

const EMPTY_FORM: DiscountCodePayload = {
  code: '',
  discount_percent: 10,
  max_uses: 100,
  starts_at: null,
  expires_at: null,
  is_active: true,
};

/** A plain Y-m-d date string (what <input type="date"> both sends and expects) from an ISO timestamp — the list shows whatever the backend already resolved to UTC, but editing re-opens it as the plain date an admin picked, not a time-shifted one. */
function toDateInputValue(iso: string | null): string {
  return iso ? iso.slice(0, 10) : '';
}

export default function DiscountCodesPage() {
  const [page, setPage] = useState(1);
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(
    () => fetchAdminDiscountCodes(page),
    [page, reloadKey],
    'Could not load discount codes.',
  );

  const [editing, setEditing] = useState<AdminDiscountCode | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<DiscountCodePayload>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [deleting, setDeleting] = useState<AdminDiscountCode | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  function openCreate() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setFormError(null);
    setShowForm(true);
  }

  function openEdit(discountCode: AdminDiscountCode) {
    setEditing(discountCode);
    setForm({
      code: discountCode.code,
      discount_percent: discountCode.discount_percent,
      max_uses: discountCode.max_uses,
      starts_at: toDateInputValue(discountCode.starts_at) || null,
      expires_at: toDateInputValue(discountCode.expires_at) || null,
      is_active: discountCode.is_active,
    });
    setFormErrors({});
    setFormError(null);
    setShowForm(true);
  }

  async function handleSubmit() {
    setIsSubmitting(true);
    setFormErrors({});
    setFormError(null);

    try {
      if (editing) {
        await updateDiscountCode(editing.id, form);
      } else {
        await createDiscountCode(form);
      }
      setShowForm(false);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setFormErrors(getValidationErrors(err));
      setFormError(getErrorMessage(err, 'Could not save the discount code.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deleteDiscountCode(deleting.id);
      setDeleting(null);
      setReloadKey((key) => key + 1);
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">Discount codes</h1>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          New discount code
        </button>
      </div>

      {isLoading && <LoadingState message="Loading discount codes..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No discount codes yet" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Discount</th>
                  <th>Window</th>
                  <th>Uses</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((discountCode) => (
                  <tr key={discountCode.id}>
                    <td>
                      <code>{discountCode.code}</code>
                      {discountCode.customer_email && (
                        <div className="small text-muted">Review reward: {discountCode.customer_email}</div>
                      )}
                    </td>
                    <td>{discountCode.discount_percent}%</td>
                    <td className="small">
                      {discountCode.starts_at ? toDateInputValue(discountCode.starts_at) : 'Any time'}
                      {' – '}
                      {discountCode.expires_at ? toDateInputValue(discountCode.expires_at) : 'No end date'}
                    </td>
                    <td>
                      {discountCode.used_count} / {discountCode.max_uses}
                    </td>
                    <td>
                      <span className={`badge text-bg-${discountCode.is_redeemable ? 'success' : 'secondary'}`}>
                        {discountCode.is_redeemable ? 'Redeemable now' : discountCode.is_active ? 'Not active yet/expired' : 'Inactive'}
                      </span>
                    </td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <button type="button" className="btn btn-outline-secondary" onClick={() => openEdit(discountCode)}>
                          Edit
                        </button>
                        <button type="button" className="btn btn-outline-danger" onClick={() => setDeleting(discountCode)}>
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}

      <FormModal
        show={showForm}
        title={editing ? 'Edit discount code' : 'New discount code'}
        isSubmitting={isSubmitting}
        error={formError}
        onSubmit={() => void handleSubmit()}
        onClose={() => setShowForm(false)}
      >
        <div>
          <label className="form-label" htmlFor="discount-code-code">
            Code
          </label>
          <input
            id="discount-code-code"
            className={`form-control ${formErrors.code ? 'is-invalid' : ''}`}
            value={form.code}
            onChange={(event) => setForm({ ...form, code: event.target.value })}
            required
          />
          <div className="form-text">Not case-sensitive — always stored and shown in capitals.</div>
          <FieldError message={formErrors.code} />
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="discount-code-percent">
              Discount (%)
            </label>
            <input
              id="discount-code-percent"
              type="number"
              min="0.01"
              max="100"
              step="0.01"
              className={`form-control ${formErrors.discount_percent ? 'is-invalid' : ''}`}
              value={form.discount_percent}
              onChange={(event) => setForm({ ...form, discount_percent: Number(event.target.value) })}
              required
            />
            <FieldError message={formErrors.discount_percent} />
          </div>
          <div className="col-sm-6">
            <label className="form-label" htmlFor="discount-code-max-uses">
              Max uses
            </label>
            <input
              id="discount-code-max-uses"
              type="number"
              min="1"
              step="1"
              className={`form-control ${formErrors.max_uses ? 'is-invalid' : ''}`}
              value={form.max_uses}
              onChange={(event) => setForm({ ...form, max_uses: Number(event.target.value) })}
              required
            />
            <div className="form-text">Total redemptions across every customer, not a per-customer limit.</div>
            <FieldError message={formErrors.max_uses} />
          </div>
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="discount-code-starts-at">
              Start date (optional)
            </label>
            <input
              id="discount-code-starts-at"
              type="date"
              className={`form-control ${formErrors.starts_at ? 'is-invalid' : ''}`}
              value={form.starts_at ?? ''}
              onChange={(event) => setForm({ ...form, starts_at: event.target.value || null })}
            />
            <div className="form-text">Active from the start of this day — leave blank for no lower bound.</div>
            <FieldError message={formErrors.starts_at} />
          </div>
          <div className="col-sm-6">
            <label className="form-label" htmlFor="discount-code-expires-at">
              End date (optional)
            </label>
            <input
              id="discount-code-expires-at"
              type="date"
              className={`form-control ${formErrors.expires_at ? 'is-invalid' : ''}`}
              value={form.expires_at ?? ''}
              onChange={(event) => setForm({ ...form, expires_at: event.target.value || null })}
            />
            <div className="form-text">Active through the end of this day — leave blank for no end date.</div>
            <FieldError message={formErrors.expires_at} />
          </div>
        </div>

        <div className="form-check">
          <input
            id="discount-code-is-active"
            type="checkbox"
            className="form-check-input"
            checked={form.is_active}
            onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
          />
          <label className="form-check-label" htmlFor="discount-code-is-active">
            Active
          </label>
        </div>
      </FormModal>

      <ConfirmModal
        show={deleting !== null}
        title="Delete discount code"
        message={`Delete "${deleting?.code}"? This cannot be undone. Orders that already redeemed it keep their own record of it.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
