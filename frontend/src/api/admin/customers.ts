import { apiClient } from '../client';
import type { Customer, CustomerFilters } from '../../types/admin';
import type { PaginatedResponse } from '../../types/product';

export async function fetchCustomers(filters: CustomerFilters): Promise<PaginatedResponse<Customer>> {
  const { data } = await apiClient.get<PaginatedResponse<Customer>>('/admin/customers', { params: filters });
  return data;
}

export async function fetchCustomer(id: number): Promise<Customer> {
  const { data } = await apiClient.get<{ data: Customer }>(`/admin/customers/${id}`);
  return data.data;
}
