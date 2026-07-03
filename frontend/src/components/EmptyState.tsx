import { states } from '../content/copy';

interface EmptyStateProps {
  title?: string;
  message?: string;
}

export default function EmptyState({ title = states.emptyDefaultTitle, message }: EmptyStateProps) {
  return (
    <div className="state-block">
      <h2 className="h5 state-block__title mb-2">{title}</h2>
      {message && <p className="mb-0">{message}</p>}
    </div>
  );
}
