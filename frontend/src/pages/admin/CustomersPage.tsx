import { useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchCustomers } from '../../api/admin/customers';
import type { CustomerFilters } from '../../types/admin';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';

export default function CustomersPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState<CustomerFilters['sort']>('newest');

  const { data, isLoading, error } = useAsync(
    () => fetchCustomers({ page, search: search || undefined, sort }),
    [page, search, sort],
    'Could not load customers.',
  );

  return (
    <div>
      <h1 className="h3 mb-4">Customers</h1>

      <div className="row g-2 mb-3">
        <div className="col-sm-5">
          <input
            type="search"
            className="form-control"
            placeholder="Search by name, email, or phone..."
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setPage(1);
            }}
          />
        </div>
        <div className="col-sm-3">
          <select className="form-select" value={sort} onChange={(event) => setSort(event.target.value as CustomerFilters['sort'])}>
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="name">Name</option>
          </select>
        </div>
      </div>

      {isLoading && <LoadingState message="Loading customers..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No customers found" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th className="text-end">Orders</th>
                  <th>Joined</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((customer) => (
                  <tr key={customer.id}>
                    <td>{customer.full_name}</td>
                    <td>{customer.email}</td>
                    <td>{customer.phone ?? '—'}</td>
                    <td className="text-end">{customer.orders_count ?? 0}</td>
                    <td>{new Date(customer.created_at).toLocaleDateString('bg-BG')}</td>
                    <td className="text-end">
                      <Link className="btn btn-outline-secondary btn-sm" to={`/admin/customers/${customer.id}`}>
                        View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  );
}
