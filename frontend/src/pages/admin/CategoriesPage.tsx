import { useState } from 'react';
import { createCategory, deleteCategory, fetchAdminCategories, updateCategory } from '../../api/admin/categories';
import type { CategoryPayload } from '../../api/admin/categories';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import FormModal from '../../components/admin/FormModal';
import ConfirmModal from '../../components/admin/ConfirmModal';
import FieldError from '../../components/FieldError';
import type { Category } from '../../types/product';

const EMPTY_FORM: CategoryPayload = { name: '', description: '', is_active: true, sort_order: 0, parent_id: null };

function flatten(categories: Category[], depth = 0): { category: Category; depth: number }[] {
  return categories.flatMap((category) => [{ category, depth }, ...flatten(category.children, depth + 1)]);
}

export default function CategoriesPage() {
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(fetchAdminCategories, [reloadKey], 'Could not load categories.');

  const [editing, setEditing] = useState<Category | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<CategoryPayload>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [deleting, setDeleting] = useState<Category | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const rows = data ? flatten(data) : [];

  function openCreate() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setFormError(null);
    setShowForm(true);
  }

  function openEdit(category: Category) {
    setEditing(category);
    setForm({
      name: category.name,
      description: category.description ?? '',
      is_active: category.is_active,
      sort_order: category.sort_order,
      parent_id: category.parent_id,
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
        await updateCategory(editing.id, form);
      } else {
        await createCategory(form);
      }
      setShowForm(false);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setFormErrors(getValidationErrors(err));
      setFormError(getErrorMessage(err, 'Could not save the category.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deleteCategory(deleting.id);
      setDeleting(null);
      setReloadKey((key) => key + 1);
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">Categories</h1>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          New category
        </button>
      </div>

      {isLoading && <LoadingState message="Loading categories..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && rows.length === 0 && <EmptyState title="No categories yet" />}

      {!isLoading && !error && rows.length > 0 && (
        <div className="table-responsive">
          <table className="table align-middle">
            <thead>
              <tr>
                <th>Name</th>
                <th>Active</th>
                <th>Sort order</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {rows.map(({ category, depth }) => (
                <tr key={category.id}>
                  <td style={{ paddingLeft: `${1 + depth * 1.5}rem` }}>{category.name}</td>
                  <td>
                    <span className={`badge text-bg-${category.is_active ? 'success' : 'secondary'}`}>
                      {category.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td>{category.sort_order}</td>
                  <td className="text-end">
                    <div className="btn-group btn-group-sm">
                      <button type="button" className="btn btn-outline-secondary" onClick={() => openEdit(category)}>
                        Edit
                      </button>
                      <button type="button" className="btn btn-outline-danger" onClick={() => setDeleting(category)}>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <FormModal
        show={showForm}
        title={editing ? 'Edit category' : 'New category'}
        isSubmitting={isSubmitting}
        error={formError}
        onSubmit={() => void handleSubmit()}
        onClose={() => setShowForm(false)}
      >
        <div>
          <label className="form-label" htmlFor="category-name">
            Name
          </label>
          <input
            id="category-name"
            className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            required
          />
          <FieldError message={formErrors.name} />
        </div>

        <div>
          <label className="form-label" htmlFor="category-parent">
            Parent category
          </label>
          <select
            id="category-parent"
            className="form-select"
            value={form.parent_id ?? ''}
            onChange={(event) => setForm({ ...form, parent_id: event.target.value ? Number(event.target.value) : null })}
          >
            <option value="">None (top-level)</option>
            {rows
              .filter(({ category }) => category.id !== editing?.id)
              .map(({ category, depth }) => (
                <option key={category.id} value={category.id}>
                  {'—'.repeat(depth)} {category.name}
                </option>
              ))}
          </select>
        </div>

        <div>
          <label className="form-label" htmlFor="category-description">
            Description
          </label>
          <textarea
            id="category-description"
            className="form-control"
            rows={3}
            value={form.description ?? ''}
            onChange={(event) => setForm({ ...form, description: event.target.value })}
          />
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="category-sort-order">
              Sort order
            </label>
            <input
              id="category-sort-order"
              type="number"
              className="form-control"
              value={form.sort_order}
              onChange={(event) => setForm({ ...form, sort_order: Number(event.target.value) })}
            />
          </div>
          <div className="col-sm-6 d-flex align-items-end">
            <div className="form-check">
              <input
                id="category-is-active"
                type="checkbox"
                className="form-check-input"
                checked={form.is_active}
                onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
              />
              <label className="form-check-label" htmlFor="category-is-active">
                Active
              </label>
            </div>
          </div>
        </div>
      </FormModal>

      <ConfirmModal
        show={deleting !== null}
        title="Delete category"
        message={`Delete "${deleting?.name}"? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
