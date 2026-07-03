interface StepIndicatorProps {
  steps: string[];
  currentStep: number;
}

export default function StepIndicator({ steps, currentStep }: StepIndicatorProps) {
  return (
    <ol className="d-flex list-unstyled gap-2 gap-sm-4 mb-4 flex-wrap">
      {steps.map((step, index) => {
        const isActive = index === currentStep;
        const isDone = index < currentStep;

        return (
          <li key={step} className="d-flex align-items-center gap-2">
            <span
              className={`d-inline-flex align-items-center justify-content-center rounded-circle ${
                isActive ? 'bg-primary text-white' : isDone ? 'bg-success text-white' : 'bg-light text-muted border'
              }`}
              style={{ width: '1.75rem', height: '1.75rem', fontSize: '0.85rem' }}
              aria-hidden="true"
            >
              {isDone ? '✓' : index + 1}
            </span>
            <span className={isActive ? 'fw-semibold' : 'text-muted'}>{step}</span>
          </li>
        );
      })}
    </ol>
  );
}
