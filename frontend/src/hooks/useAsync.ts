import { useEffect, useState } from 'react';
import { getErrorMessage } from '../api/errors';

interface UseAsyncResult<T> {
  data: T | null;
  isLoading: boolean;
  error: string | null;
}

/**
 * Shared data-fetching boilerplate (loading/error state, mount-safety,
 * re-fetch on dependency change) for the product/category hooks below.
 */
export function useAsync<T>(fetcher: () => Promise<T>, deps: unknown[], fallbackErrorMessage: string): UseAsyncResult<T> {
  const [data, setData] = useState<T | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  /* oxlint-disable react-hooks/exhaustive-deps -- deps is a caller-supplied array by design */
  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);
    setError(null);

    fetcher()
      .then((result) => {
        if (isMounted) {
          setData(result);
        }
      })
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, fallbackErrorMessage));
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, deps);
  /* oxlint-enable react-hooks/exhaustive-deps */

  return { data, isLoading, error };
}
