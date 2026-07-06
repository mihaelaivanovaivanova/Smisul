import { apiClient } from './client';
import type { LegalDocument } from '../types/legal';

/** Public, unauthenticated — every currently-published legal document. */
export async function fetchLegalDocuments(): Promise<LegalDocument[]> {
  const { data } = await apiClient.get<{ data: LegalDocument[] }>('/legal-documents');
  return data.data;
}

/** Public, unauthenticated — a single document by its URL slug (e.g. "privacy-policy"). */
export async function fetchLegalDocument(slug: string): Promise<LegalDocument> {
  const { data } = await apiClient.get<{ data: LegalDocument }>(`/legal-documents/${slug}`);
  return data.data;
}
