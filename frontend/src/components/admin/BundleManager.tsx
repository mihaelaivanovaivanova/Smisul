import { useMemo, useState } from 'react';
import SearchableSelect from '../SearchableSelect';
import FieldError from '../FieldError';
import { formatPrice } from '../../services/productCatalog';
import type { BundleItemPayload } from '../../api/admin/bundles';
import type { AdminProduct } from '../../types/admin';

interface BundleManagerProps {
  items: BundleItemPayload[];
  onChange: (items: BundleItemPayload[]) => void;
  /** Every product with its variants (prices included) - the pick list for components and the source of each component's live price. */
  products: AdminProduct[];
  /** The kit's own price, to compare against the components' live separate-purchase total below. */
  kitAmount: number;
  /** Validation errors from the save, keyed as the backend returns them (items, items.0.quantity, ...). */
  errors: Record<string, string>;
  disabled?: boolean;
}

interface VariantInfo {
  label: string;
  price: number | null;
}

/**
 * The kit's components editor inside ProductBundlesPage's form - same
 * table-of-rows + inline add form UX as VariantManager, but controlled
 * (the rows are only sent when the whole kit is saved, since the backend
 * replaces a kit's components as one set). Shows each component's live
 * price and the resulting separate-purchase total, i.e. exactly the
 * "retail value" the storefront's "you save" line is computed from.
 */
export default function BundleManager({ items, onChange, products, kitAmount, errors, disabled = false }: BundleManagerProps) {
  const [newVariantId, setNewVariantId] = useState('');
  const [newQuantity, setNewQuantity] = useState(1);

  const variants = useMemo(() => {
    const map = new Map<number, VariantInfo>();
    products.forEach((product) =>
      product.variants.forEach((variant) => {
        map.set(variant.id, {
          label: `${product.name} — ${variant.name}`,
          price: variant.prices.find((price) => price.currency === 'EUR')?.amount ?? null,
        });
      }),
    );
    return map;
  }, [products]);

  const usedIds = new Set(items.map((item) => item.product_variant_id));
  const options = Array.from(variants.entries())
    .filter(([id]) => !usedIds.has(id))
    .map(([id, info]) => ({
      value: String(id),
      label: `${info.label}${info.price !== null ? ` (${formatPrice(info.price)})` : ''}`,
      searchText: info.label,
    }));

  const retailValue = items.reduce((sum, item) => sum + (variants.get(item.product_variant_id)?.price ?? 0) * item.quantity, 0);
  const savings = retailValue - kitAmount;

  function updateQuantity(index: number, quantity: number) {
    onChange(items.map((item, current) => (current === index ? { ...item, quantity } : item)));
  }

  function removeItem(index: number) {
    onChange(items.filter((_, current) => current !== index));
  }

  function addItem() {
    if (!newVariantId) return;
    onChange([...items, { product_variant_id: Number(newVariantId), quantity: Math.max(1, newQuantity) }]);
    setNewVariantId('');
    setNewQuantity(1);
  }

  return (
    <div>
      <label className="form-label d-block">Components</label>

      {items.length > 0 ? (
        <div className="table-responsive mb-2">
          <table className="table table-sm align-middle">
            <thead>
              <tr>
                <th>Product / variant</th>
                <th style={{ width: 110 }}>Qty per kit</th>
                <th className="text-end">Unit price</th>
                <th className="text-end">Value</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((item, index) => {
                const info = variants.get(item.product_variant_id);
                const quantityError = errors[`items.${index}.quantity`];
                const variantError = errors[`items.${index}.product_variant_id`];

                return (
                  <tr key={item.product_variant_id}>
                    <td>
                      {info?.label ?? `Variant #${item.product_variant_id}`}
                      <FieldError message={variantError} />
                    </td>
                    <td>
                      <input
                        type="number"
                        min="1"
                        step="1"
                        aria-label="Quantity per kit"
                        className={`form-control form-control-sm ${quantityError ? 'is-invalid' : ''}`}
                        value={item.quantity}
                        disabled={disabled}
                        onChange={(event) => updateQuantity(index, Number(event.target.value))}
                      />
                      <FieldError message={quantityError} />
                    </td>
                    <td className="text-end">{info?.price != null ? formatPrice(info.price) : '—'}</td>
                    <td className="text-end">{info?.price != null ? formatPrice(info.price * item.quantity) : '—'}</td>
                    <td className="text-end">
                      <button
                        type="button"
                        className="btn btn-outline-danger btn-sm"
                        disabled={disabled}
                        onClick={() => removeItem(index)}
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={3} className="text-end fw-semibold">
                  Separate-purchase total
                </td>
                <td className="text-end">{formatPrice(retailValue)}</td>
                <td></td>
              </tr>
              <tr>
                <td colSpan={5} className={`text-end small ${savings > 0 ? 'text-success' : 'text-muted'}`}>
                  {savings > 0
                    ? `Storefront shows "you save ${formatPrice(savings)}"`
                    : 'Kit price is not below the separate-purchase total - no saving is shown on the storefront'}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      ) : (
        <div className="text-muted small mb-2">No components yet - a kit needs at least one.</div>
      )}
      <FieldError message={errors.items} />

      <div className="card card-body bg-body-tertiary">
        <div className="row g-2 align-items-end">
          <div className="col-sm-8">
            <label className="form-label" htmlFor="bundle-new-component">
              Add component
            </label>
            <SearchableSelect
              id="bundle-new-component"
              value={newVariantId}
              options={options}
              placeholder="Search product or variant..."
              onChange={setNewVariantId}
              disabled={disabled}
            />
          </div>
          <div className="col-sm-2">
            <label className="form-label" htmlFor="bundle-new-quantity">
              Qty
            </label>
            <input
              id="bundle-new-quantity"
              type="number"
              min="1"
              step="1"
              className="form-control"
              value={newQuantity}
              disabled={disabled}
              onChange={(event) => setNewQuantity(Number(event.target.value))}
            />
          </div>
          <div className="col-sm-2">
            <button
              type="button"
              className="btn btn-outline-secondary w-100"
              disabled={disabled || !newVariantId}
              onClick={addItem}
            >
              Add
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
