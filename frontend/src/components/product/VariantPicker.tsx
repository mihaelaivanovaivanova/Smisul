import type { ProductVariant } from '../../types/product';
import { formatPrice, getVariantPrice } from '../../services/productCatalog';
import { product as productCopy } from '../../content/copy';

interface VariantPickerProps {
  variants: ProductVariant[];
  selectedId: number;
  onSelect: (variant: ProductVariant) => void;
}

export default function VariantPicker({ variants, selectedId, onSelect }: VariantPickerProps) {
  if (variants.length <= 1) {
    return null;
  }

  return (
    <div className="mb-3">
      <span className="form-label d-block">{productCopy.packageLabel}</span>
      <div className="d-flex flex-wrap gap-2">
        {variants.map((variant) => {
          const price = getVariantPrice(variant);
          const isActive = variant.id === selectedId;

          return (
            <button
              key={variant.id}
              type="button"
              className={`btn btn-sm variant-picker__option ${isActive ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => onSelect(variant)}
              disabled={variant.status !== 'active'}
              aria-pressed={isActive}
            >
              {variant.name}
              {price && <span className="ms-1">({formatPrice(price.amount, price.currency)})</span>}
            </button>
          );
        })}
      </div>
    </div>
  );
}
