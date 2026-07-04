import { Link, useParams } from 'react-router-dom';
import { fetchCustomer } from '../../api/admin/customers';
import { fetchAdminOrders } from '../../api/admin/orders';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import StatusBadge from '../../components/admin/StatusBadge';
import { formatPrice } from '../../services/productCatalog';

export default function CustomerDetailPage() {
  const { customerId } = useParams<{ customerId: string }>();
  const id = Number(customerId);

  const { data: customer, isLoading, error } = useAsync(() => fetchCustomer(id), [id], 'Could not load the customer.');
  const { data: orders, isLoading: ordersLoading } = useAsync(
    () => fetchAdminOrders({ user_id: id }),
    [id],
    'Could not load order history.',
  );

  if (isLoading) return <LoadingState message="Loading customer..." />;
  if (error) return <ErrorState message={error} />;
  if (!customer) return null;

  return (
    <div>
      <h1 className="h3 mb-4">{customer.full_name}</h1>

      <div className="row g-4">
        <div className="col-lg-4">
          <div className="card mb-4">
            <div className="card-header">Details</div>
            <div className="card-body">
              <p className="mb-1">{customer.email}</p>
              <p className="mb-1">{customer.phone ?? '—'}</p>
              <p className="mb-1">Joined {new Date(customer.created_at).toLocaleDateString('bg-BG')}</p>
              <p className="mb-0">
                Email verified: {customer.email_verified_at ? 'Yes' : 'No'}
              </p>
            </div>
          </div>
        </div>

        <div className="col-lg-8">
          <div className="card">
            <div className="card-header">Order history</div>
            {ordersLoading && <LoadingState message="Loading orders..." />}
            {!ordersLoading && orders && orders.data.length === 0 && <EmptyState title="No orders yet" />}
            {!ordersLoading && orders && orders.data.length > 0 && (
              <div className="table-responsive">
                <table className="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Order #</th>
                      <th>Status</th>
                      <th>Placed</th>
                      <th className="text-end">Total</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {orders.data.map((order) => (
                      <tr key={order.id}>
                        <td>{order.order_number}</td>
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
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
