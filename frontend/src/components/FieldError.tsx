interface FieldErrorProps {
  message?: string;
}

export default function FieldError({ message }: FieldErrorProps) {
  if (!message) {
    return null;
  }

  return <div className="invalid-feedback d-block">{message}</div>;
}
