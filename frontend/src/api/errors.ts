import axios from 'axios';
import type { AxiosError } from 'axios';

export interface ValidationErrorBody {
  message: string;
  errors?: Record<string, string[]>;
  /** Machine-readable discriminator some 403 responses carry (see AuthorizesOrderAccess on the backend) - lets a caller tell apart causes that look identical as a bare status code, e.g. "log in" vs "this guest link is broken". */
  reason?: string;
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

/** The HTTP status code of an API error response, or undefined for anything else (network error, unexpected shape). */
export function getErrorStatus(error: unknown): number | undefined {
  return isApiError(error) ? error.response?.status : undefined;
}

/** See ValidationErrorBody.reason. */
export function getErrorReason(error: unknown): string | undefined {
  return isApiError(error) ? error.response?.data.reason : undefined;
}
