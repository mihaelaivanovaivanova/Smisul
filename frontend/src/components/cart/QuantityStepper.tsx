import { cart as cartCopy } from '../../content/copy';

interface QuantityStepperProps {
  quantity: number;
  min?: number;
  max: number;
  disabled?: boolean;
  onChange: (quantity: number) => void;
  /** 'bordered' (default) is the existing Bootstrap button-group look used on the full cart page; 'flat' is a borderless, tighter +/− used by CartDrawer's compact rows. */
  variant?: 'bordered' | 'flat';
}

export default function QuantityStepper({ quantity, min = 1, max, disabled = false, onChange, variant = 'bordered' }: QuantityStepperProps) {
  if (variant === 'flat') {
    return (
      <div className="quantity-stepper-flat" role="group" aria-label={cartCopy.quantityLabel}>
        <button
          type="button"
          className="quantity-stepper-flat__btn"
          onClick={() => onChange(quantity - 1)}
          disabled={disabled || quantity <= min}
          aria-label={cartCopy.decreaseAria}
        >
          &minus;
        </button>
        <span className="quantity-stepper-flat__value" aria-live="polite">
          {quantity}
        </span>
        <button
          type="button"
          className="quantity-stepper-flat__btn"
          onClick={() => onChange(quantity + 1)}
          disabled={disabled || quantity >= max}
          aria-label={cartCopy.increaseAria}
        >
          +
        </button>
      </div>
    );
  }

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
