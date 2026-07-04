import { useState } from 'react';
import { Link } from 'react-router-dom';
import { createProduct, deleteProduct, fetchAdminProducts, updateProduct } from '../../api/admin/products';
import type { ProductPayload } from '../../api/admin/products';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import FormModal from '../../components/admin/FormModal';
import ConfirmModal from '../../components/admin/ConfirmModal';
import StatusBadge from '../../components/admin/StatusBadge';
import FieldError from '../../components/FieldError';
import type { Product, ProductStatus } from '../../types/product';

const EMPTY_FORM: ProductPayload = { name: '', short_description: '', description: '', status: 'draft', category_ids: [] };

export default function ProductsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(
    () => fetchAdminProducts({ page, search: search || undefined }),
    [page, search, reloadKey],
    'Could not load products.',
  );

  const [editing, setEditing] = useState<Product | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<ProductPayload>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [deleting, setDeleting] = useState<Product | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  function openCreate() {
    setEditing(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setFormError(null);
    setShowForm(true);
  }

  function openEdit(product: Product) {
    setEditing(product);
    setForm({
      name: product.name,
      short_description: product.short_description ?? '',
      description: product.description ?? '',
      status: product.status,
      category_ids: product.categories.map((category) => category.id),
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
        await updateProduct(editing.id, form);
      } else {
        await createProduct(form);
      }
      setShowForm(false);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setFormErrors(getValidationErrors(err));
      setFormError(getErrorMessage(err, 'Could not save the product.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deleteProduct(deleting.id);
      setDeleting(null);
      setReloadKey((key) => key + 1);
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">Products</h1>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          New product
        </button>
      </div>

      <div className="mb-3">
        <input
          type="search"
          className="form-control"
          style={{ maxWidth: 320 }}
          placeholder="Search products..."
          value={search}
          onChange={(event) => {
            setSearch(event.target.value);
            setPage(1);
          }}
        />
      </div>

      {isLoading && <LoadingState message="Loading products..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No products found" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Status</th>
                  <th>Categories</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((product) => (
                  <tr key={product.id}>
                    <td>{product.name}</td>
                    <td>
                      <StatusBadge status={product.status} />
                    </td>
                    <td>{product.categories.map((category) => category.name).join(', ') || '—'}</td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link className="btn btn-outline-secondary" to={`/products/${product.slug}`} target="_blank">
                          View
                        </Link>
                        <button type="button" className="btn btn-outline-secondary" onClick={() => openEdit(product)}>
                          Edit
                        </button>
                        <button type="button" className="btn btn-outline-danger" onClick={() => setDeleting(product)}>
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
        title={editing ? 'Edit product' : 'New product'}
        isSubmitting={isSubmitting}
        error={formError}
        onSubmit={() => void handleSubmit()}
        onClose={() => setShowForm(false)}
      >
        <div>
          <label className="form-label" htmlFor="product-name">
            Name
          </label>
          <input
            id="product-name"
            className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            required
          />
          <FieldError message={formErrors.name} />
        </div>

        <div>
          <label className="form-label" htmlFor="product-short-description">
            Short description
          </label>
          <input
            id="product-short-description"
            className="form-control"
            value={form.short_description ?? ''}
            onChange={(event) => setForm({ ...form, short_description: event.target.value })}
          />
        </div>

        <div>
          <label className="form-label" htmlFor="product-description">
            Description
          </label>
          <textarea
            id="product-description"
            className="form-control"
            rows={4}
            value={form.description ?? ''}
            onChange={(event) => setForm({ ...form, description: event.target.value })}
          />
        </div>

        <div>
          <label className="form-label" htmlFor="product-status">
            Status
          </label>
          <select
            id="product-status"
            className="form-select"
            value={form.status}
            onChange={(event) => setForm({ ...form, status: event.target.value as ProductStatus })}
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </FormModal>

      <ConfirmModal
        show={deleting !== null}
        title="Delete product"
        message={`Delete "${deleting?.name}"? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
