import { apiClient } from './client';
import type { LegalDocument } from '../types/legal';

/** Public, unauthenticated — every currently-published legal document. */
export async function fetchLegalDocuments(): Promise<LegalDocument[]> {
  const { data } = await apiClient.get<{ data: LegalDocument[] }>('/legal-documents');
  return data.data;
}

/**
 * Re-accepts every current Terms/Privacy version for the authenticated
 * account — call after the user confirms the re-acceptance prompt shown
 * for User.outstanding_legal_documents. Returns whatever's still
 * outstanding afterwards (normally empty).
 */
export async function acceptOutstandingLegalDocuments(): Promise<LegalDocument[]> {
  const { data } = await apiClient.post<{ data: LegalDocument[] }>('/consent/legal-documents/accept');
  return data.data;
}

/** Public, unauthenticated — a single document by its URL slug (e.g. "privacy-policy"). */
export async function fetchLegalDocument(slug: string): Promise<LegalDocument> {
  const { data } = await apiClient.get<{ data: LegalDocument }>(`/legal-documents/${slug}`);
  return data.data;
}
