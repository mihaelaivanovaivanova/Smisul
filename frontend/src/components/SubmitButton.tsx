import type { ReactNode } from 'react';

interface SubmitButtonProps {
  isLoading: boolean;
  children: ReactNode;
  className?: string;
}

export default function SubmitButton({ isLoading, children, className = '' }: SubmitButtonProps) {
  return (
    <button type="submit" className={`btn btn-primary ${className}`} disabled={isLoading}>
      {isLoading && (
        <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
      )}
      {children}
    </button>
  );
}
