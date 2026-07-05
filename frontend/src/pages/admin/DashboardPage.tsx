import { useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchDashboardStats } from '../../api/admin/dashboard';
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

const DEFAULT_FILTERS: OrderFilters = { search: '', status: '', dateFrom: '', dateTo: '', sort: 'newest' };

interface StatCardProps {
  label: string;
  value: string | number;
}

function StatCard({ label, value }: StatCardProps) {
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card h-100">
        <div className="card-body">
          <div className="text-muted small mb-1">{label}</div>
          <div className="fs-4 fw-semibold">{value}</div>
        </div>
      </div>
    </div>
  );
}

export default function DashboardPage() {
  const { data: stats, isLoading: statsLoading, error: statsError } = useAsync(fetchDashboardStats, [], 'Could not load the dashboard.');

  const [ordersPage, setOrdersPage] = useState(1);
  const [orderFilters, setOrderFilters] = useState<OrderFilters>(DEFAULT_FILTERS);

  const {
    data: orders,
    isLoading: ordersLoading,
    error: ordersError,
  } = useAsync(
    () =>
      fetchAdminOrders({
        page: ordersPage,
        search: orderFilters.search || undefined,
        status: orderFilters.status || undefined,
        date_from: orderFilters.dateFrom || undefined,
        date_to: orderFilters.dateTo || undefined,
        sort: orderFilters.sort,
      }),
    [ordersPage, orderFilters],
    'Could not load orders.',
  );

  function handleOrderFiltersChange(next: OrderFilters) {
    setOrderFilters(next);
    setOrdersPage(1);
  }

  return (
    <div>
      <h1 className="h3 mb-4">Dashboard</h1>

      {statsLoading && <LoadingState message="Loading dashboard..." />}
      {!statsLoading && statsError && <ErrorState message={statsError} />}

      {!statsLoading && !statsError && stats && (
        <div className="row g-3 mb-4">
          <StatCard label="Total orders" value={stats.total_orders} />
          <StatCard label="Orders today" value={stats.orders_today} />
          <StatCard label="Revenue today" value={formatPrice(stats.revenue_today)} />
          <StatCard label="Total revenue" value={formatPrice(stats.total_revenue)} />
          <StatCard label="Total customers" value={stats.total_customers} />
          <StatCard label="Total products" value={stats.total_products} />
          <StatCard label="Low stock products" value={stats.low_stock_products} />
          <StatCard label="Out of stock products" value={stats.out_of_stock_products} />
          <StatCard label="Total favorites" value={stats.total_favorites} />
        </div>
      )}

      <h2 className="h5 mb-3">Orders</h2>
      <OrderFilterBar filters={orderFilters} onChange={handleOrderFiltersChange} />

      <div className="card">
        {ordersLoading && <LoadingState message="Loading orders..." />}
        {!ordersLoading && ordersError && <ErrorState message={ordersError} />}
        {!ordersLoading && !ordersError && orders && orders.data.length === 0 && <EmptyState title="No orders found" />}

        {!ordersLoading && !ordersError && orders && orders.data.length > 0 && (
          <>
            <div className="table-responsive">
              <table className="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th className="text-end">Total</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {orders.data.map((order) => (
                    <tr key={order.id}>
                      <td>{order.order_number}</td>
                      <td>{order.customer.email}</td>
                      <td>
                        <StatusBadge status={order.status} />
                      </td>
                      <td className="text-end">{formatPrice(order.totals.grand_total)}</td>
                      <td className="text-end">
                        <Link to={`/admin/orders/${order.id}`}>View</Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="card-body">
              <Pagination meta={orders.meta} onPageChange={setOrdersPage} />
            </div>
          </>
        )}
      </div>
    </div>
  );
}
