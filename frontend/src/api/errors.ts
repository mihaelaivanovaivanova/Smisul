import axios from 'axios';
import type { AxiosError } from 'axios';

export interface ValidationErrorBody {
  message: string;
  errors?: Record<string, string[]>;
}

/** True for any Axios error response (not only 422s) shaped like our API's error body. */
function isApiError(error: unknown): error is AxiosError<ValidationErrorBody> {
  return axios.isAxiosError(error);
}

/**
 * Extracts a { field: message } map from a Laravel 422 validation response,
 * taking the first message per field. Returns an empty object for any other
 * kind of error.
 */
export function getValidationErrors(error: unknown): Record<string, string> {
  if (!isApiError(error)) {
    return {};
  }

  const fieldErrors = error.response?.data.errors ?? {};

  return Object.fromEntries(
    Object.entries(fieldErrors).map(([field, messages]) => [field, messages[0] ?? '']),
  );
}

/**
 * Extracts the top-level error message from an API error response, falling
 * back to a generic message for network errors or unexpected shapes.
 */
export function getErrorMessage(error: unknown, fallback: string): string {
  if (isApiError(error) && error.response?.data.message) {
    return error.response.data.message;
  }

  return fallback;
}
