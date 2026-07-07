import { apiClient } from './client';

export interface ContactMessagePayload {
  name: string;
  email: string;
  message: string;
}

/** Public, unauthenticated — the footer's contact form. */
export async function sendContactMessage(payload: ContactMessagePayload): Promise<void> {
  await apiClient.post('/contact', payload);
}
