import { apiClient } from '../client';
import type { FunnelLead } from '../../types/admin';
import type { PaginatedResponse } from '../../types/product';

export async function fetchFunnelLeads(page = 1, email = ''): Promise<PaginatedResponse<FunnelLead>> {
  const { data } = await apiClient.get<PaginatedResponse<FunnelLead>>('/admin/funnel/leads', {
    params: { page, email: email || undefined },
  });
  return data;
}

export async function deleteFunnelLead(id: number): Promise<void> {
  await apiClient.delete(`/admin/funnel/leads/${id}`);
}

/**
 * Downloads the CSV export via the authenticated API client (a plain
 * <a href> to the API origin wouldn't carry the Sanctum session over
 * CORS reliably), then hands it to the browser as a file download.
 */
export async function downloadFunnelLeadsCsv(): Promise<void> {
  const { data } = await apiClient.get<Blob>('/admin/funnel/leads/export', { responseType: 'blob' });

  const url = URL.createObjectURL(data);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = 'funnel-leads.csv';
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}
