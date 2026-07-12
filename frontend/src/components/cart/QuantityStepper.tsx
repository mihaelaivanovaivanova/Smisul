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
      {/* pe-none, not the `disabled` utility: `disabled` also dims this to
          --bs-btn-disabled-opacity, which made it (and the "−" button
          whenever quantity is at its min) look visually faded next to a
          full-opacity "+" — this is a static display, not a real control,
          so it should always render at the same solid border/text style
          as an enabled button either side of it. */}
      <span className="btn btn-outline-secondary btn-sm pe-none" aria-live="polite">
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
