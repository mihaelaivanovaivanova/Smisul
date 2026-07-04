import { apiClient } from '../client';
import type { AdminLegalDocument, PublishLegalDocumentPayload } from '../../types/admin';

export async function fetchAdminLegalDocuments(): Promise<AdminLegalDocument[]> {
  const { data } = await apiClient.get<{ data: AdminLegalDocument[] }>('/admin/legal-documents');
  return data.data;
}

export async function publishLegalDocument(payload: PublishLegalDocumentPayload): Promise<AdminLegalDocument> {
  const { data } = await apiClient.post<{ data: AdminLegalDocument }>('/admin/legal-documents', payload);
  return data.data;
}
