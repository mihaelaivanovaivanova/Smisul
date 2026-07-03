import { states } from '../content/copy';

interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
}

export default function ErrorState({ message, onRetry }: ErrorStateProps) {
  return (
    <div
      className="alert alert-danger d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2"
      role="alert"
    >
      <span>{message}</span>
      {onRetry && (
        <button type="button" className="btn btn-outline-danger btn-sm align-self-start" onClick={onRetry}>
          {states.errorRetry}
        </button>
      )}
    </div>
  );
}
