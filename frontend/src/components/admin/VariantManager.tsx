import { useRef, useState } from 'react';
import { fetchAdminProduct } from '../../api/admin/products';
import {
  createVariant,
  deleteVariant,
  deleteVariantPhoto,
  updateVariant,
  updateVariantInventory,
  updateVariantPrice,
  updateVariantPhotoFocus,
  uploadVariantPhoto,
} from '../../api/admin/variants';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import { formatPrice } from '../../services/productCatalog';
import ConfirmModal from './ConfirmModal';
import FieldError from '../FieldError';
import MediaFocusPointModal from './MediaFocusPointModal';
import type { Media, ProductVariant } from '../../types/product';

interface VariantManagerProps {
  productId: number;
  variants: ProductVariant[];
  onChange: (variants: ProductVariant[]) => void;
}

interface VariantFormState {
  sku: string;
  name: string;
  pack_size: number;
  amount: number;
  compare_at_amount: number | null;
  upsell_amount: number | null;
  quantity_on_hand: number;
}

const EMPTY_FORM: VariantFormState = {
  sku: '',
  name: '',
  pack_size: 1,
  amount: 0,
  compare_at_amount: null,
  upsell_amount: null,
  quantity_on_hand: 0,
};

/**
 * Self-contained, same spirit as ProductMediaManager: every action hits the
 * API directly (create/update variant, then price, then stock — three
 * separate endpoints, see api/admin/variants.ts) and reports the resulting
 * variant list back via onChange, rather than routing through the
 * surrounding product form's own save.
 */
export default function VariantManager({ productId, variants, onChange }: VariantManagerProps) {
  const [formMode, setFormMode] = useState<'closed' | 'create' | 'edit'>('closed');
  const [formVariantId, setFormVariantId] = useState<number | null>(null);
  const [form, setForm] = useState<VariantFormState>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  // Snapshot of the upsell price this variant had when the form opened —
  // the price PUT always resubmits all three price fields together, so
  // saving with this field now blank would silently remove a configured
  // cross-sell price. Compared against form.upsell_amount at submit time
  // to decide whether to confirm first (see handleSubmit/confirmClearUpsell).
  const [originalUpsellAmount, setOriginalUpsellAmount] = useState<number | null>(null);
  const [confirmClearUpsell, setConfirmClearUpsell] = useState(false);

  const [deleting, setDeleting] = useState<ProductVariant | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [photoActionId, setPhotoActionId] = useState<number | null>(null);
  const photoInputRef = useRef<HTMLInputElement | null>(null);
  const photoTargetRef = useRef<number | null>(null);
  const [settingFocusFor, setSettingFocusFor] = useState<{ variantId: number; media: Media } | null>(null);

  async function refresh() {
    const product = await fetchAdminProduct(productId);
    onChange(product.variants);
  }

  function openPhotoPicker(variantId: number) {
    photoTargetRef.current = variantId;
    photoInputRef.current?.click();
  }

  async function handlePhotoSelected(file: File) {
    const variantId = photoTargetRef.current;
    if (!variantId) return;

    setPhotoActionId(variantId);
    try {
      await uploadVariantPhoto(productId, variantId, file);
      await refresh();
    } finally {
      setPhotoActionId(null);
    }
  }

  async function handleClearPhoto(variantId: number) {
    setPhotoActionId(variantId);
    try {
      await deleteVariantPhoto(productId, variantId);
      await refresh();
    } finally {
      setPhotoActionId(null);
    }
  }

  async function handleSaveFocus(focusX: number, focusY: number) {
    if (!settingFocusFor) return;
    await updateVariantPhotoFocus(productId, settingFocusFor.variantId, settingFocusFor.media.id, focusX, focusY);
    setSettingFocusFor(null);
    await refresh();
  }

  function openCreateForm() {
    setFormMode('create');
    setFormVariantId(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setFormError(null);
    setOriginalUpsellAmount(null);
  }

  function openEditForm(variant: ProductVariant) {
    const price = variant.prices.find((candidate) => candidate.currency === 'EUR');

    setFormMode('edit');
    setFormVariantId(variant.id);
    setForm({
      sku: variant.sku,
      name: variant.name,
      pack_size: variant.pack_size,
      amount: price?.amount ?? 0,
      compare_at_amount: price?.compare_at_amount ?? null,
      upsell_amount: price?.upsell_amount ?? null,
      quantity_on_hand: variant.inventory?.available_quantity ?? 0,
    });
    setFormErrors({});
    setFormError(null);
    setOriginalUpsellAmount(price?.upsell_amount ?? null);
  }

  function closeForm() {
    setFormMode('closed');
  }

  /**
   * Saving with the upsell field now blank, when this variant had a real
   * upsell price when the form opened, would silently remove it (the
   * price PUT always resubmits all three fields together) — confirm
   * first instead of just doing it. Anything else (unchanged, newly set,
   * or there was never one to lose) saves immediately.
   */
  async function handleSubmit(): Promise<void> {
    if (formMode === 'edit' && originalUpsellAmount !== null && form.upsell_amount === null) {
      setConfirmClearUpsell(true);
      return;
    }

    await performSubmit();
  }

  async function handleConfirmedClearUpsell(): Promise<void> {
    setConfirmClearUpsell(false);
    await performSubmit();
  }

  async function performSubmit(): Promise<void> {
    setIsSaving(true);
    setFormErrors({});
    setFormError(null);

    try {
      const variantId =
        formMode === 'edit' && formVariantId
          ? (await updateVariant(productId, formVariantId, { sku: form.sku, name: form.name, pack_size: form.pack_size })).id
          : (await createVariant(productId, { sku: form.sku, name: form.name, pack_size: form.pack_size })).id;

      await updateVariantPrice(productId, variantId, {
        currency: 'EUR',
        amount: form.amount,
        compare_at_amount: form.compare_at_amount,
        upsell_amount: form.upsell_amount,
      });
      await updateVariantInventory(productId, variantId, { quantity_on_hand: form.quantity_on_hand });

      await refresh();
      closeForm();
    } catch (err) {
      setFormErrors(getValidationErrors(err));
      setFormError(getErrorMessage(err, 'Could not save this variant.'));
    } finally {
      setIsSaving(false);
    }
  }

  async function handleDelete(): Promise<void> {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deleteVariant(productId, deleting.id);
      setDeleting(null);
      await refresh();
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <label className="form-label d-block">Variants (pack sizes)</label>

      <input
        type="file"
        accept="image/*"
        className="d-none"
        ref={photoInputRef}
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file) {
            void handlePhotoSelected(file);
          }
          event.target.value = '';
        }}
      />

      {variants.length > 0 && (
        <div className="table-responsive mb-2">
          <table className="table table-sm align-middle">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Name</th>
                <th>Pack size</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Photo</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {variants.map((variant) => {
                const price = variant.prices.find((candidate) => candidate.currency === 'EUR');
                const photo = variant.media?.[0];
                const isBusy = photoActionId === variant.id;

                return (
                  <tr key={variant.id}>
                    <td>{variant.sku}</td>
                    <td>{variant.name}</td>
                    <td>{variant.pack_size}</td>
                    <td>{price ? formatPrice(price.amount, price.currency) : '—'}</td>
                    <td>{variant.inventory?.available_quantity ?? '—'}</td>
                    <td>
                      <div className="d-flex align-items-center gap-2">
                        {photo ? (
                          <img
                            src={photo.url}
                            alt=""
                            width={40}
                            height={40}
                            className="rounded object-fit-cover"
                            style={{ objectPosition: `${photo.focus_x * 100}% ${photo.focus_y * 100}%` }}
                          />
                        ) : (
                          <span className="text-muted small">— (uses product gallery)</span>
                        )}
                        <div className="btn-group btn-group-sm">
                          <button
                            type="button"
                            className="btn btn-outline-secondary"
                            disabled={isBusy}
                            onClick={() => openPhotoPicker(variant.id)}
                          >
                            {isBusy ? '…' : photo ? 'Replace' : 'Upload'}
                          </button>
                          {photo && (
                            <button
                              type="button"
                              className="btn btn-outline-secondary"
                              disabled={isBusy}
                              onClick={() => setSettingFocusFor({ variantId: variant.id, media: photo })}
                            >
                              Set focus point
                            </button>
                          )}
                          {photo && (
                            <button
                              type="button"
                              className="btn btn-outline-danger"
                              disabled={isBusy}
                              onClick={() => void handleClearPhoto(variant.id)}
                            >
                              Clear
                            </button>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <button type="button" className="btn btn-outline-secondary" onClick={() => openEditForm(variant)}>
                          Edit
                        </button>
                        <button type="button" className="btn btn-outline-danger" onClick={() => setDeleting(variant)}>
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {formMode === 'closed' ? (
        <button type="button" className="btn btn-outline-secondary btn-sm" onClick={openCreateForm}>
          Add variant
        </button>
      ) : (
        <div className="card card-body bg-body-tertiary">
          {formError && <div className="alert alert-danger py-2">{formError}</div>}

          <div className="row g-2">
            <div className="col-sm-6">
              <label className="form-label" htmlFor="variant-sku">
                SKU
              </label>
              <input
                id="variant-sku"
                className={`form-control ${formErrors.sku ? 'is-invalid' : ''}`}
                value={form.sku}
                onChange={(event) => setForm({ ...form, sku: event.target.value })}
                required
              />
              <FieldError message={formErrors.sku} />
            </div>
            <div className="col-sm-6">
              <label className="form-label" htmlFor="variant-name">
                Name
              </label>
              <input
                id="variant-name"
                className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
                required
              />
              <FieldError message={formErrors.name} />
            </div>
            <div className="col-sm-4">
              <label className="form-label" htmlFor="variant-pack-size">
                Pack size
              </label>
              <input
                id="variant-pack-size"
                type="number"
                min="1"
                step="1"
                className={`form-control ${formErrors.pack_size ? 'is-invalid' : ''}`}
                value={form.pack_size}
                onChange={(event) => setForm({ ...form, pack_size: Number(event.target.value) })}
                required
              />
              <FieldError message={formErrors.pack_size} />
            </div>
            <div className="col-sm-4">
              <label className="form-label" htmlFor="variant-price">
                Price (EUR)
              </label>
              <input
                id="variant-price"
                type="number"
                min="0"
                step="0.01"
                className={`form-control ${formErrors.amount ? 'is-invalid' : ''}`}
                value={form.amount}
                onChange={(event) => setForm({ ...form, amount: Number(event.target.value) })}
                required
              />
              <FieldError message={formErrors.amount} />
            </div>
            <div className="col-sm-4">
              <label className="form-label" htmlFor="variant-stock">
                Stock
              </label>
              <input
                id="variant-stock"
                type="number"
                min="0"
                step="1"
                className={`form-control ${formErrors.quantity_on_hand ? 'is-invalid' : ''}`}
                value={form.quantity_on_hand}
                onChange={(event) => setForm({ ...form, quantity_on_hand: Number(event.target.value) })}
                required
              />
              <FieldError message={formErrors.quantity_on_hand} />
            </div>
            <div className="col-sm-6">
              <label className="form-label" htmlFor="variant-compare-at">
                Compare-at price (EUR, optional)
              </label>
              <input
                id="variant-compare-at"
                type="number"
                min="0"
                step="0.01"
                className="form-control"
                value={form.compare_at_amount ?? ''}
                onChange={(event) => setForm({ ...form, compare_at_amount: event.target.value ? Number(event.target.value) : null })}
              />
            </div>
            <div className="col-sm-6">
              <label className="form-label" htmlFor="variant-upsell-amount">
                Cross-sell upsell price (EUR, optional)
              </label>
              <input
                id="variant-upsell-amount"
                type="number"
                min="0"
                step="0.01"
                className="form-control"
                value={form.upsell_amount ?? ''}
                onChange={(event) => setForm({ ...form, upsell_amount: event.target.value ? Number(event.target.value) : null })}
              />
              <div className="form-text">Only charged when added through a specific cart cross-sell card — never shown on this product&apos;s own page.</div>
            </div>
          </div>

          <div className="d-flex gap-2 mt-3">
            <button type="button" className="btn btn-primary btn-sm" disabled={isSaving} onClick={() => void handleSubmit()}>
              {isSaving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
              Save variant
            </button>
            <button type="button" className="btn btn-outline-secondary btn-sm" disabled={isSaving} onClick={closeForm}>
              Cancel
            </button>
          </div>
        </div>
      )}

      <MediaFocusPointModal
        media={settingFocusFor?.media ?? null}
        onSave={(x, y) => handleSaveFocus(x, y)}
        onClose={() => setSettingFocusFor(null)}
      />

      <ConfirmModal
        show={deleting !== null}
        title="Delete variant"
        message={`Delete "${deleting?.name}"? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />

      <ConfirmModal
        show={confirmClearUpsell}
        title="Remove the cross-sell upsell price?"
        message={`This variant currently has an upsell price of ${originalUpsellAmount !== null ? formatPrice(originalUpsellAmount) : ''}, but the upsell field is now empty. Saving will remove it — the cart cross-sell card will stop offering a discount for this variant. Continue?`}
        confirmLabel="Remove upsell price"
        isLoading={isSaving}
        onConfirm={() => void handleConfirmedClearUpsell()}
        onCancel={() => setConfirmClearUpsell(false)}
      />
    </div>
  );
}
