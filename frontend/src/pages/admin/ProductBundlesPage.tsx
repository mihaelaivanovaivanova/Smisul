import { useState } from 'react';
import { createBundle, deleteBundle, fetchAdminBundles, updateBundle } from '../../api/admin/bundles';
import type { BundlePayload } from '../../api/admin/bundles';
import { fetchAdminProducts } from '../../api/admin/products';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import { formatPrice } from '../../services/productCatalog';
import { summarizeBundleContents } from '../../services/cartItemDisplay';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import FormModal from '../../components/admin/FormModal';
import ConfirmModal from '../../components/admin/ConfirmModal';
import StatusBadge from '../../components/admin/StatusBadge';
import BundleManager from '../../components/admin/BundleManager';
import FieldError from '../../components/FieldError';
import type { Bundle } from '../../types/bundle';
import type { VariantStatus } from '../../types/product';

const EMPTY_FORM: BundlePayload = {
  slug: '',
  name: '',
  badge: '',
  amount: 0,
  status: 'active',
  sort_order: 0,
  items: [],
};

/**
 * Admin CRUD for kits (1-Person Kit, 2-Person Kit, ...) - see the backend's
 * Admin\ProductBundleController. A kit is sold as ONE product (one cart/
 * order line, one price) but its stock comes entirely from its components'
 * own inventory, so there's no stock field here. Changing a kit's
 * components or deleting it is refused while carts/unconfirmed orders still
 * hold it - the error from the backend says so and suggests deactivating
 * instead; name/price/status/sort changes always go through.
 */
export default function ProductBundlesPage() {
  const [reloadKey, setReloadKey] = useState(0);
  const { data: bundles, isLoading, error } = useAsync(fetchAdminBundles, [reloadKey], 'Could not load kits.');
  const { data: productsPage, error: productsError } = useAsync(
    () => fetchAdminProducts({ per_page: 100 }),
    [],
    'Could not load products for the component picker.',
  );
  const products = productsPage?.data ?? [];

  const [editing, setEditing] = useState<Bundle | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<BundlePayload>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [deleting, setDeleting] = useState<Bundle | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  function openCreate() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setFormError(null);
    setShowForm(true);
  }

  function openEdit(bundle: Bundle) {
    setEditing(bundle);
    setForm({
      slug: bundle.slug,
      name: bundle.name,
      badge: bundle.badge ?? '',
      amount: bundle.amount,
      status: bundle.status,
      sort_order: bundle.sort_order,
      items: bundle.items.map((item) => ({ product_variant_id: item.product_variant_id, quantity: item.quantity })),
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
      const payload = { ...form, badge: (form.badge ?? '').trim() || null };
      if (editing) {
        await updateBundle(editing.id, payload);
      } else {
        await createBundle(payload);
      }
      setShowForm(false);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setFormErrors(getValidationErrors(err));
      setFormError(getErrorMessage(err, 'Could not save the kit.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await deleteBundle(deleting.id);
      setDeleting(null);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setDeleteError(getErrorMessage(err, 'Could not delete the kit.'));
      setDeleting(null);
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">Kits</h1>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          New kit
        </button>
      </div>

      {deleteError && <div className="alert alert-danger">{deleteError}</div>}

      {isLoading && <LoadingState message="Loading kits..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && bundles && bundles.length === 0 && <EmptyState title="No kits yet" />}

      {!isLoading && !error && bundles && bundles.length > 0 && (
        <div className="table-responsive">
          <table className="table align-middle">
            <thead>
              <tr>
                <th>Name</th>
                <th>Contents</th>
                <th className="text-end">Price</th>
                <th className="text-end">Separate total</th>
                <th>Status</th>
                <th>Stock</th>
                <th className="text-end">Sort</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {bundles.map((bundle) => (
                <tr key={bundle.id}>
                  <td>
                    {bundle.name}
                    <div className="small text-muted">
                      <code>{bundle.slug}</code>
                    </div>
                  </td>
                  <td className="small">{summarizeBundleContents(bundle.items) || '—'}</td>
                  <td className="text-end">{formatPrice(bundle.amount, bundle.currency)}</td>
                  <td className="text-end">
                    {formatPrice(bundle.retail_value, bundle.currency)}
                    {bundle.savings > 0 && (
                      <div className="small text-success">saves {formatPrice(bundle.savings, bundle.currency)}</div>
                    )}
                  </td>
                  <td>
                    <StatusBadge status={bundle.status} />
                  </td>
                  <td>
                    <span className={`badge text-bg-${bundle.is_available ? 'success' : 'danger'}`}>
                      {bundle.is_available ? 'Available' : 'Out of stock'}
                    </span>
                  </td>
                  <td className="text-end">{bundle.sort_order}</td>
                  <td className="text-end">
                    <div className="btn-group btn-group-sm">
                      <button type="button" className="btn btn-outline-secondary" onClick={() => openEdit(bundle)}>
                        Edit
                      </button>
                      <button
                        type="button"
                        className="btn btn-outline-danger"
                        onClick={() => {
                          setDeleteError(null);
                          setDeleting(bundle);
                        }}
                      >
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
        title={editing ? 'Edit kit' : 'New kit'}
        isSubmitting={isSubmitting}
        error={formError}
        onSubmit={() => void handleSubmit()}
        onClose={() => setShowForm(false)}
      >
        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="bundle-name">
              Name
            </label>
            <input
              id="bundle-name"
              className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
              required
            />
            <div className="form-text">Shown to customers as the cart/order line name.</div>
            <FieldError message={formErrors.name} />
          </div>
          <div className="col-sm-6">
            <label className="form-label" htmlFor="bundle-slug">
              Slug
            </label>
            <input
              id="bundle-slug"
              className={`form-control ${formErrors.slug ? 'is-invalid' : ''}`}
              value={form.slug}
              onChange={(event) => setForm({ ...form, slug: event.target.value })}
              required
            />
            <div className="form-text">Letters, numbers, dashes. Used as the kit&apos;s SKU on orders and shipping labels.</div>
            <FieldError message={formErrors.slug} />
          </div>
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="bundle-badge">
              Badge (optional)
            </label>
            <input
              id="bundle-badge"
              className={`form-control ${formErrors.badge ? 'is-invalid' : ''}`}
              value={form.badge ?? ''}
              onChange={(event) => setForm({ ...form, badge: event.target.value })}
              placeholder="e.g. НАЙ-ПОПУЛЯРЕН"
            />
            <div className="form-text">Shown as a pill on the kit's own option card. Leave blank for none.</div>
            <FieldError message={formErrors.badge} />
          </div>
        </div>

        <div className="row g-3">
          <div className="col-sm-4">
            <label className="form-label" htmlFor="bundle-amount">
              Kit price (EUR)
            </label>
            <input
              id="bundle-amount"
              type="number"
              min="0.01"
              step="0.01"
              className={`form-control ${formErrors.amount ? 'is-invalid' : ''}`}
              value={form.amount}
              onChange={(event) => setForm({ ...form, amount: Number(event.target.value) })}
              required
            />
            <FieldError message={formErrors.amount} />
          </div>
          <div className="col-sm-4">
            <label className="form-label" htmlFor="bundle-status">
              Status
            </label>
            <select
              id="bundle-status"
              className="form-select"
              value={form.status}
              onChange={(event) => setForm({ ...form, status: event.target.value as VariantStatus })}
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <FieldError message={formErrors.status} />
          </div>
          <div className="col-sm-4">
            <label className="form-label" htmlFor="bundle-sort-order">
              Sort order
            </label>
            <input
              id="bundle-sort-order"
              type="number"
              min="0"
              step="1"
              className={`form-control ${formErrors.sort_order ? 'is-invalid' : ''}`}
              value={form.sort_order}
              onChange={(event) => setForm({ ...form, sort_order: Number(event.target.value) })}
            />
            <FieldError message={formErrors.sort_order} />
          </div>
        </div>

        {productsError && <div className="alert alert-warning mb-0">{productsError}</div>}

        <BundleManager
          items={form.items}
          onChange={(items) => setForm({ ...form, items })}
          products={products}
          kitAmount={form.amount}
          errors={formErrors}
          disabled={isSubmitting}
        />

        {editing && (
          <div className="form-text">
            Components can&apos;t be changed while this kit sits in a cart or an unconfirmed order - deactivate it and create a new kit
            instead if the contents need to change.
          </div>
        )}
      </FormModal>

      <ConfirmModal
        show={deleting !== null}
        title="Delete kit"
        message={`Delete "${deleting?.name}"? This cannot be undone. Past orders keep their own record of it.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
