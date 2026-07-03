import { cart as cartCopy } from '../../content/copy';

interface QuantityStepperProps {
  quantity: number;
  min?: number;
  max: number;
  disabled?: boolean;
  onChange: (quantity: number) => void;
}

export default function QuantityStepper({ quantity, min = 1, max, disabled = false, onChange }: QuantityStepperProps) {
  return (
    <div className="btn-group" role="group" aria-label={cartCopy.quantityLabel}>
      <button
        type="button"
        className="btn btn-outline-secondary btn-sm"
        onClick={() => onChange(quantity - 1)}
        disabled={disabled || quantity <= min}
        aria-label={cartCopy.decreaseAria}
      >
        &minus;
      </button>
      <span className="btn btn-outline-secondary btn-sm disabled" aria-live="polite">
        {quantity}
      </span>
      <button
        type="button"
        className="btn btn-outline-secondary btn-sm"
        onClick={() => onChange(quantity + 1)}
        disabled={disabled || quantity >= max}
        aria-label={cartCopy.increaseAria}
      >
        +
      </button>
    </div>
  );
}
