import { useState } from 'react';
import { createPromotion, deletePromotion, fetchAdminPromotions, updatePromotion } from '../../api/admin/promotions';
import type { PromotionPayload } from '../../api/admin/promotions';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import FormModal from '../../components/admin/FormModal';
import ConfirmModal from '../../components/admin/ConfirmModal';
import FieldError from '../../components/FieldError';
import type { Promotion, PromotionType } from '../../types/product';

const EMPTY_FORM: PromotionPayload = { name: '', description: '', type: 'percentage', value: 0, code: '', is_active: true };

export default function PromotionsPage() {
  const [page, setPage] = useState(1);
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(() => fetchAdminPromotions(page), [page, reloadKey], 'Could not load promotions.');

  const [editing, setEditing] = useState<Promotion | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<PromotionPayload>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [deleting, setDeleting] = useState<Promotion | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  function openCreate() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setFormError(null);
    setShowForm(true);
  }

  function openEdit(promotion: Promotion) {
    setEditing(promotion);
    setForm({
      name: promotion.name,
      description: promotion.description ?? '',
      type: promotion.type,
      value: promotion.value,
      code: promotion.code ?? '',
      starts_at: promotion.starts_at,
      ends_at: promotion.ends_at,
      usage_limit: promotion.usage_limit,
      is_active: promotion.is_active,
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
        await updatePromotion(editing.id, form);
      } else {
        await createPromotion(form);
      }
      setShowForm(false);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setFormErrors(getValidationErrors(err));
      setFormError(getErrorMessage(err, 'Could not save the promotion.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deletePromotion(deleting.id);
      setDeleting(null);
      setReloadKey((key) => key + 1);
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">Promotions</h1>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          New promotion
        </button>
      </div>

      {isLoading && <LoadingState message="Loading promotions..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No promotions yet" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Type</th>
                  <th>Value</th>
                  <th>Code</th>
                  <th>Active</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((promotion) => (
                  <tr key={promotion.id}>
                    <td>{promotion.name}</td>
                    <td>{promotion.type === 'percentage' ? 'Percentage' : 'Fixed amount'}</td>
                    <td>{promotion.type === 'percentage' ? `${promotion.value}%` : promotion.value}</td>
                    <td>{promotion.code ?? '—'}</td>
                    <td>
                      <span className={`badge text-bg-${promotion.is_active ? 'success' : 'secondary'}`}>
                        {promotion.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <button type="button" className="btn btn-outline-secondary" onClick={() => openEdit(promotion)}>
                          Edit
                        </button>
                        <button type="button" className="btn btn-outline-danger" onClick={() => setDeleting(promotion)}>
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
        title={editing ? 'Edit promotion' : 'New promotion'}
        isSubmitting={isSubmitting}
        error={formError}
        onSubmit={() => void handleSubmit()}
        onClose={() => setShowForm(false)}
      >
        <div>
          <label className="form-label" htmlFor="promotion-name">
            Name
          </label>
          <input
            id="promotion-name"
            className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            required
          />
          <FieldError message={formErrors.name} />
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="promotion-type">
              Type
            </label>
            <select
              id="promotion-type"
              className="form-select"
              value={form.type}
              onChange={(event) => setForm({ ...form, type: event.target.value as PromotionType })}
            >
              <option value="percentage">Percentage</option>
              <option value="fixed_amount">Fixed amount</option>
            </select>
          </div>
          <div className="col-sm-6">
            <label className="form-label" htmlFor="promotion-value">
              Value
            </label>
            <input
              id="promotion-value"
              type="number"
              step="0.01"
              className={`form-control ${formErrors.value ? 'is-invalid' : ''}`}
              value={form.value}
              onChange={(event) => setForm({ ...form, value: Number(event.target.value) })}
            />
            <FieldError message={formErrors.value} />
          </div>
        </div>

        <div>
          <label className="form-label" htmlFor="promotion-code">
            Code (optional)
          </label>
          <input
            id="promotion-code"
            className={`form-control ${formErrors.code ? 'is-invalid' : ''}`}
            value={form.code ?? ''}
            onChange={(event) => setForm({ ...form, code: event.target.value })}
          />
          <FieldError message={formErrors.code} />
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="promotion-starts-at">
              Starts at
            </label>
            <input
              id="promotion-starts-at"
              type="datetime-local"
              className="form-control"
              value={form.starts_at ?? ''}
              onChange={(event) => setForm({ ...form, starts_at: event.target.value || null })}
            />
          </div>
          <div className="col-sm-6">
            <label className="form-label" htmlFor="promotion-ends-at">
              Ends at
            </label>
            <input
              id="promotion-ends-at"
              type="datetime-local"
              className={`form-control ${formErrors.ends_at ? 'is-invalid' : ''}`}
              value={form.ends_at ?? ''}
              onChange={(event) => setForm({ ...form, ends_at: event.target.value || null })}
            />
            <FieldError message={formErrors.ends_at} />
          </div>
        </div>

        <div>
          <label className="form-label" htmlFor="promotion-description">
            Description
          </label>
          <textarea
            id="promotion-description"
            className="form-control"
            rows={3}
            value={form.description ?? ''}
            onChange={(event) => setForm({ ...form, description: event.target.value })}
          />
        </div>

        <div className="form-check">
          <input
            id="promotion-is-active"
            type="checkbox"
            className="form-check-input"
            checked={form.is_active}
            onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
          />
          <label className="form-check-label" htmlFor="promotion-is-active">
            Active
          </label>
        </div>
      </FormModal>

      <ConfirmModal
        show={deleting !== null}
        title="Delete promotion"
        message={`Delete "${deleting?.name}"? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
