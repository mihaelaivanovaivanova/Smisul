import type { ReactNode } from 'react';

interface AlertProps {
  variant: 'success' | 'danger' | 'info' | 'warning';
  children: ReactNode;
}

export default function Alert({ variant, children }: AlertProps) {
  return (
    <div className={`alert alert-${variant}`} role="alert">
      {children}
    </div>
  );
}
