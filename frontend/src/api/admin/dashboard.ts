import { apiClient } from '../client';
import type { DashboardStats } from '../../types/admin';

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const { data } = await apiClient.get<{ data: DashboardStats }>('/admin/dashboard');
  return data.data;
}
