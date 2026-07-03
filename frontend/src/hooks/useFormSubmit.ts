import { useCallback, useState } from 'react';
import { getValidationErrors, getErrorMessage } from '../api/errors';

interface UseFormSubmitResult {
  isLoading: boolean;
  errors: Record<string, string>;
  formError: string | null;
  /** Runs `action`, capturing validation errors and a top-level error message on failure. */
  submit: (action: () => Promise<void>, fallbackMessage: string) => Promise<void>;
  reset: () => void;
}

/**
 * Shared submit/error-handling logic for auth and profile forms: tracks
 * loading state, per-field validation errors, and a top-level error message,
 * so each form only has to describe its own request and success behavior.
 */
export function useFormSubmit(): UseFormSubmitResult {
  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  const reset = useCallback(() => {
    setErrors({});
    setFormError(null);
  }, []);

  const submit = useCallback(
    async (action: () => Promise<void>, fallbackMessage: string) => {
      reset();
      setIsLoading(true);

      try {
        await action();
      } catch (error) {
        setErrors(getValidationErrors(error));
        setFormError(getErrorMessage(error, fallbackMessage));
      } finally {
        setIsLoading(false);
      }
    },
    [reset],
  );

  return { isLoading, errors, formError, submit, reset };
}
