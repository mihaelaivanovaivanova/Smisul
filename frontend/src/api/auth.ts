import { apiClient, ensureCsrfCookie } from './client';
import type { User } from '../types/auth';

interface ApiResource<T> {
  data: T;
}

interface ApiMessage {
  message: string;
}

export interface RegisterPayload {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
  newsletter_subscription?: boolean;
  marketing_consent?: boolean;
  gdpr_consent: boolean;
}

export interface LoginPayload {
  email: string;
  password: string;
  remember?: boolean;
}

export interface ResetPasswordPayload {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

/**
 * Registers a new customer account. Does not log the user in — the backend
 * requires a separate login after registration (and, per the flow, email
 * verification).
 */
export async function register(payload: RegisterPayload): Promise<User> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<ApiResource<User>>('/auth/register', payload);
  return data.data;
}

export async function login(payload: LoginPayload): Promise<User> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<ApiResource<User>>('/auth/login', payload);
  return data.data;
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
}

/** Fetches the currently authenticated user, used to restore session state on load. */
export async function fetchCurrentUser(): Promise<User> {
  const { data } = await apiClient.get<ApiResource<User>>('/auth/user');
  return data.data;
}

export async function forgotPassword(email: string): Promise<string> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<ApiMessage>('/auth/forgot-password', { email });
  return data.message;
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<ApiMessage>('/auth/reset-password', payload);
  return data.message;
}

export async function verifyEmail(
  id: string,
  hash: string,
  params: { expires: string; signature: string },
): Promise<string> {
  const { data } = await apiClient.get<ApiMessage>(`/auth/email/verify/${id}/${hash}`, { params });
  return data.message;
}

export async function resendVerificationEmail(): Promise<string> {
  const { data } = await apiClient.post<ApiMessage>('/auth/email/verification-notification');
  return data.message;
}
