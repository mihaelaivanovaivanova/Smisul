import { states } from '../content/copy';

interface LoadingStateProps {
  message?: string;
}

export default function LoadingState({ message = states.loadingDefault }: LoadingStateProps) {
  return (
    <div className="state-block d-flex flex-column align-items-center justify-content-center">
      <div className="spinner-border state-block__spinner mb-3" role="status">
        <span className="visually-hidden">{message}</span>
      </div>
      <p className="mb-0">{message}</p>
    </div>
  );
}
