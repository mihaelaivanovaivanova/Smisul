import { Link } from 'react-router-dom';
import { fetchDashboardStats } from '../../api/admin/dashboard';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import StatusBadge from '../../components/admin/StatusBadge';
import { formatPrice } from '../../services/productCatalog';

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
  const { data, isLoading, error } = useAsync(fetchDashboardStats, [], 'Could not load the dashboard.');

  return (
    <div>
      <h1 className="h3 mb-4">Dashboard</h1>

      {isLoading && <LoadingState message="Loading dashboard..." />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && data && (
        <>
          <div className="row g-3 mb-4">
            <StatCard label="Total orders" value={data.total_orders} />
            <StatCard label="Orders today" value={data.orders_today} />
            <StatCard label="Revenue today" value={formatPrice(data.revenue_today)} />
            <StatCard label="Total revenue" value={formatPrice(data.total_revenue)} />
            <StatCard label="Total customers" value={data.total_customers} />
            <StatCard label="Total products" value={data.total_products} />
            <StatCard label="Low stock products" value={data.low_stock_products} />
            <StatCard label="Out of stock products" value={data.out_of_stock_products} />
          </div>

          <div className="card">
            <div className="card-header">Latest orders</div>
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
                  {data.latest_orders.map((order) => (
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
                  {data.latest_orders.length === 0 && (
                    <tr>
                      <td colSpan={5} className="text-center text-muted py-4">
                        No orders yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
