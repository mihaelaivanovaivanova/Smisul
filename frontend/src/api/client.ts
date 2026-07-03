import axios from 'axios';

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api';

// The backend's root domain, e.g. http://localhost:8000, derived from
// VITE_API_URL (http://localhost:8000/api) — needed because Sanctum's
// CSRF cookie route lives outside the /api prefix.
export const appUrl = apiUrl.replace(/\/api\/?$/, '');

// Exposed for the rare case a plain <a href> needs to hit the API directly
// (e.g. a file download) instead of going through apiClient.
export const apiBaseUrl = `${apiUrl}/v1`;

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
});

/**
 * Sanctum's SPA (cookie) authentication requires a CSRF cookie to be
 * present before any state-changing request. Must be called before
 * register/login/forgot-password/reset-password.
 */
export async function ensureCsrfCookie(): Promise<void> {
  await axios.get(`${appUrl}/sanctum/csrf-cookie`, { withCredentials: true });
}
