import FieldError from './FieldError';

interface PhoneFieldProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
}

const COUNTRY_CODE = '+359';

/**
 * An inline SVG rather than the 🇧🇬 emoji — Windows doesn't render
 * regional-indicator flag emoji as pictures at all (Segoe UI Emoji has no
 * flag glyphs), it just shows the two letters, so the emoji rendered as
 * literal "BG" text instead of a flag on Windows/Chrome.
 */
function BulgarianFlagIcon() {
  return (
    <svg width="18" height="12" viewBox="0 0 18 12" aria-hidden="true" style={{ flexShrink: 0 }}>
      <rect width="18" height="4" y="0" fill="#fff" />
      <rect width="18" height="4" y="4" fill="#00966E" />
      <rect width="18" height="4" y="8" fill="#D62612" />
    </svg>
  );
}

/**
 * The input only ever shows the local part the customer actually types —
 * this strips a leading +359 / 359 / 0 so a value round-tripped back in
 * (e.g. navigating back a checkout step) doesn't duplicate the prefix.
 */
function toLocalDigits(value: string): string {
  const trimmed = value.trimStart();
  const digits = trimmed.replace(/\D/g, '');

  if (trimmed.startsWith('+359')) return digits.slice(3);
  if (digits.startsWith('359')) return digits.slice(3);
  if (digits.startsWith('0')) return digits.slice(1);

  return digits;
}

/** Fixed +359/BG prefix — the customer only types the local number, and the full E.164-ish value (what the backend expects) is reassembled here. */
export default function PhoneField({ id, label, value, onChange, error, required = false }: PhoneFieldProps) {
  const localValue = toLocalDigits(value);

  function handleChange(next: string): void {
    const digits = next.replace(/\D/g, '');
    onChange(digits ? `${COUNTRY_CODE}${digits}` : '');
  }

  return (
    <>
      <label htmlFor={id} className="form-label">
        {label}
      </label>
      <div className="input-group">
        <span className="input-group-text d-flex align-items-center gap-2">
          <BulgarianFlagIcon />
          {COUNTRY_CODE}
        </span>
        <input
          id={id}
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          maxLength={9}
          placeholder="8xxxxxxxx"
          className={`form-control ${error ? 'is-invalid' : ''}`}
          value={localValue}
          onChange={(event) => handleChange(event.target.value)}
          required={required}
        />
      </div>
      <FieldError message={error} />
    </>
  );
}
