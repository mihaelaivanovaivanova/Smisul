import { useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchAdminOrders } from '../../api/admin/orders';
import type { AdminOrderFilters } from '../../api/admin/orders';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import StatusBadge from '../../components/admin/StatusBadge';
import { formatPrice } from '../../services/productCatalog';

const STATUSES = [
  'pending', 'awaiting_payment', 'paid', 'processing', 'packed', 'shipped',
  'delivered', 'completed', 'cancelled', 'failed', 'refunded',
];

export default function AdminOrdersPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [sort, setSort] = useState<AdminOrderFilters['sort']>('newest');

  const { data, isLoading, error } = useAsync(
    () => fetchAdminOrders({ page, search: search || undefined, status: status || undefined, sort }),
    [page, search, status, sort],
    'Could not load orders.',
  );

  return (
    <div>
      <h1 className="h3 mb-4">Orders</h1>

      <div className="row g-2 mb-3">
        <div className="col-sm-4">
          <input
            type="search"
            className="form-control"
            placeholder="Search order # or email..."
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setPage(1);
            }}
          />
        </div>
        <div className="col-sm-3">
          <select
            className="form-select"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value);
              setPage(1);
            }}
          >
            <option value="">All statuses</option>
            {STATUSES.map((value) => (
              <option key={value} value={value}>
                {value.replace(/_/g, ' ')}
              </option>
            ))}
          </select>
        </div>
        <div className="col-sm-3">
          <select
            className="form-select"
            value={sort}
            onChange={(event) => setSort(event.target.value as AdminOrderFilters['sort'])}
          >
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="total_desc">Total: high to low</option>
            <option value="total_asc">Total: low to high</option>
          </select>
        </div>
      </div>

      {isLoading && <LoadingState message="Loading orders..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No orders found" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Placed</th>
                  <th className="text-end">Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((order) => (
                  <tr key={order.id}>
                    <td>{order.order_number}</td>
                    <td>{order.customer.email}</td>
                    <td>
                      <StatusBadge status={order.status} />
                    </td>
                    <td>{new Date(order.placed_at).toLocaleDateString('bg-BG')}</td>
                    <td className="text-end">{formatPrice(order.totals.grand_total)}</td>
                    <td className="text-end">
                      <Link className="btn btn-outline-secondary btn-sm" to={`/admin/orders/${order.id}`}>
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
