import { apiClient } from './client';
import type { User } from '../types/auth';

interface ApiResource<T> {
  data: T;
}

interface ApiMessage {
  message: string;
}

export interface UpdateProfilePayload {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string | null;
  newsletter_subscription?: boolean;
  marketing_consent?: boolean;
}

export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export async function fetchProfile(): Promise<User> {
  const { data } = await apiClient.get<ApiResource<User>>('/profile');
  return data.data;
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
  const { data } = await apiClient.put<ApiResource<User>>('/profile', payload);
  return data.data;
}

export async function updatePassword(payload: UpdatePasswordPayload): Promise<string> {
  const { data } = await apiClient.put<ApiMessage>('/profile/password', payload);
  return data.message;
}
