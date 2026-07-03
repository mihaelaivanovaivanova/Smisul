import { useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchOrders } from '../api/checkout';
import { useAsync } from '../hooks/useAsync';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import EmptyState from '../components/EmptyState';
import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import Pagination from '../components/listing/Pagination';
import { formatPrice } from '../services/productCatalog';
import { breadcrumbLabels, orders as ordersCopy } from '../content/copy';

export default function OrdersPage() {
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useAsync(() => fetchOrders(page), [page], ordersCopy.loadError);

  return (
    <div className="container py-4">
      <Seo title={ordersCopy.seoTitle} description={ordersCopy.seoDescription} />
      <Breadcrumbs items={[{ label: breadcrumbLabels.home, to: '/' }, { label: ordersCopy.title }]} />
      <h1 className="mb-4 mt-3">{ordersCopy.title}</h1>

      {isLoading && <LoadingState message={ordersCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && data && data.data.length === 0 && (
        <EmptyState title={ordersCopy.emptyTitle} message={ordersCopy.emptyMessage} />
      )}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>{ordersCopy.orderNumberHeading}</th>
                  <th>{ordersCopy.dateHeading}</th>
                  <th>{ordersCopy.statusHeading}</th>
                  <th className="text-end">{ordersCopy.totalHeading}</th>
                  <th className="text-end">{ordersCopy.viewOrder}</th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((order) => (
                  <tr key={order.id}>
                    <td>{order.order_number}</td>
                    <td>{new Date(order.placed_at).toLocaleDateString('bg-BG')}</td>
                    <td>{ordersCopy.status[order.status] ?? order.status}</td>
                    <td className="text-end">{formatPrice(order.totals.grand_total)}</td>
                    <td className="text-end">
                      <Link to={`/order-confirmation/${order.id}`}>{ordersCopy.viewOrder}</Link>
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
