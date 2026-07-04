import { apiClient } from '../client';
import type { LogEntry, LogType } from '../../types/admin';
import type { PaginatedResponse } from '../../types/product';

export async function fetchLogs(type: LogType, page = 1): Promise<PaginatedResponse<LogEntry>> {
  const { data } = await apiClient.get<PaginatedResponse<LogEntry>>('/admin/logs', { params: { type, page } });
  return data;
}
