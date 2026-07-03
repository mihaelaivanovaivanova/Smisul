import FieldError from './FieldError';

interface FormFieldProps {
  id: string;
  label: string;
  type?: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
}

/**
 * The label + input + validation-error triplet repeated across every auth
 * and profile form. Deliberately has no outer wrapper div, so callers
 * control layout (a plain `mb-3` div, or a grid column for side-by-side
 * fields) — see RegisterPage/ProfileDetailsForm for the grid case.
 */
export default function FormField({ id, label, type = 'text', value, onChange, error, required = false }: FormFieldProps) {
  return (
    <>
      <label htmlFor={id} className="form-label">
        {label}
      </label>
      <input
        id={id}
        type={type}
        className={`form-control ${error ? 'is-invalid' : ''}`}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={required}
      />
      <FieldError message={error} />
    </>
  );
}
