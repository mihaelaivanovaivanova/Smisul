import { useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchAdminOrders } from '../../api/admin/orders';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import StatusBadge from '../../components/admin/StatusBadge';
import OrderFilterBar from '../../components/admin/OrderFilterBar';
import type { OrderFilters } from '../../components/admin/OrderFilterBar';
import { formatPrice } from '../../services/productCatalog';

const DEFAULT_FILTERS: OrderFilters = { search: '', status: '', hideCancelled: false, dateFrom: '', dateTo: '', sort: 'newest' };

export default function AdminOrdersPage() {
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<OrderFilters>(DEFAULT_FILTERS);

  const { data, isLoading, error } = useAsync(
    () =>
      fetchAdminOrders({
        page,
        search: filters.search || undefined,
        status: filters.status || undefined,
        hide_cancelled: filters.hideCancelled || undefined,
        date_from: filters.dateFrom || undefined,
        date_to: filters.dateTo || undefined,
        sort: filters.sort,
      }),
    [page, filters],
    'Could not load orders.',
  );

  function handleFiltersChange(next: OrderFilters) {
    setFilters(next);
    setPage(1);
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h1 className="h3 mb-0">Orders</h1>
        <Link className="btn btn-primary" to="/admin/orders/new">
          Create order
        </Link>
      </div>

      <OrderFilterBar filters={filters} onChange={handleFiltersChange} />

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
                  <th>Items</th>
                  <th>Tracking #</th>
                  <th className="text-end">Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((order) => (
                  <tr key={order.id}>
                    <td>{order.order_number}</td>
                    <td>{order.customer.email ?? `${order.customer.first_name} ${order.customer.last_name} (manual)`}</td>
                    <td>
                      <StatusBadge status={order.status} />
                    </td>
                    <td>{new Date(order.placed_at).toLocaleDateString('bg-BG')}</td>
                    <td>
                      {order.items.map((item) => `${item.product_name}${item.variant_name ? ` (${item.variant_name})` : ''} x${item.quantity}`).join(', ')}
                    </td>
                    <td>{order.shipment?.tracking_number ?? ''}</td>
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
