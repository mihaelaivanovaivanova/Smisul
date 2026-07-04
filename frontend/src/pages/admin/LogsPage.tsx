import { useState } from 'react';
import { fetchLogs } from '../../api/admin/logs';
import type { LogType } from '../../types/admin';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';

const TABS: { key: LogType; label: string }[] = [
  { key: 'orders', label: 'Order events' },
  { key: 'payments', label: 'Payment events' },
  { key: 'shipments', label: 'Shipping events' },
  { key: 'authentication', label: 'Authentication' },
  { key: 'admin_actions', label: 'Admin actions' },
];

export default function LogsPage() {
  const [type, setType] = useState<LogType>('orders');
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useAsync(() => fetchLogs(type, page), [type, page], 'Could not load logs.');

  function switchTab(nextType: LogType) {
    setType(nextType);
    setPage(1);
  }

  return (
    <div>
      <h1 className="h3 mb-4">Logs</h1>

      <ul className="nav nav-tabs mb-4">
        {TABS.map((tab) => (
          <li className="nav-item" key={tab.key}>
            <button type="button" className={`nav-link ${type === tab.key ? 'active' : ''}`} onClick={() => switchTab(tab.key)}>
              {tab.label}
            </button>
          </li>
        ))}
      </ul>

      {isLoading && <LoadingState message="Loading logs..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No entries yet" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Message</th>
                  <th>User</th>
                  <th>When</th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((entry) => (
                  <tr key={entry.id}>
                    <td>{entry.message}</td>
                    <td>{entry.user ? `${entry.user.name} (${entry.user.email})` : '—'}</td>
                    <td>{new Date(entry.created_at).toLocaleString('bg-BG')}</td>
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
