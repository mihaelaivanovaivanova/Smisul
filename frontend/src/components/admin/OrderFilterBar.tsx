import { ORDER_STATUSES } from '../../constants/orderStatus';
import type { AdminOrderFilters } from '../../api/admin/orders';
import type { ShippingCarrier } from '../../types/checkout';

export interface OrderFilters {
  search: string;
  status: string;
  carrier: ShippingCarrier | '';
  hideCancelled: boolean;
  dateFrom: string;
  dateTo: string;
  sort: AdminOrderFilters['sort'];
}

const CARRIER_LABELS: Record<ShippingCarrier, string> = {
  speedy: 'Speedy',
  box_now: 'BOX NOW',
};

interface OrderFilterBarProps {
  filters: OrderFilters;
  onChange: (filters: OrderFilters) => void;
}

/**
 * Shared by the dedicated Orders page and the Dashboard's orders table, so
 * both search by the same criteria (order # / email / tracking number,
 * status, carrier, date placed, sort) instead of drifting into two different
 * filter sets. The search field also matches a shipment's tracking number
 * (see OrderService::listForAdmin()) - the backend is the sole source of
 * what actually matches, this is just the input for it.
 */
export default function OrderFilterBar({ filters, onChange }: OrderFilterBarProps) {
  function set<K extends keyof OrderFilters>(key: K, value: OrderFilters[K]) {
    onChange({ ...filters, [key]: value });
  }

  return (
    <div className="row g-2 mb-3">
      <div className="col-sm-4 col-lg-3">
        <input
          type="search"
          className="form-control"
          placeholder="Search order #, email, or tracking #..."
          value={filters.search}
          onChange={(event) => set('search', event.target.value)}
        />
      </div>
      <div className="col-sm-3 col-lg-2">
        <select className="form-select" value={filters.status} onChange={(event) => set('status', event.target.value)}>
          <option value="">All statuses</option>
          {ORDER_STATUSES.map((status) => (
            <option key={status} value={status}>
              {status.replace(/_/g, ' ')}
            </option>
          ))}
        </select>
      </div>
      <div className="col-sm-3 col-lg-2">
        <select
          className="form-select"
          value={filters.carrier}
          onChange={(event) => set('carrier', event.target.value as OrderFilters['carrier'])}
        >
          <option value="">All carriers</option>
          {Object.entries(CARRIER_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>
      <div className="col-sm-3 col-lg-2">
        <input
          type="date"
          className="form-control"
          aria-label="From date"
          value={filters.dateFrom}
          onChange={(event) => set('dateFrom', event.target.value)}
        />
      </div>
      <div className="col-sm-3 col-lg-2">
        <input
          type="date"
          className="form-control"
          aria-label="To date"
          value={filters.dateTo}
          onChange={(event) => set('dateTo', event.target.value)}
        />
      </div>
      <div className="col-sm-3 col-lg-2">
        <select
          className="form-select"
          value={filters.sort}
          onChange={(event) => set('sort', event.target.value as OrderFilters['sort'])}
        >
          <option value="newest">Newest first</option>
          <option value="oldest">Oldest first</option>
          <option value="total_desc">Total: high to low</option>
          <option value="total_asc">Total: low to high</option>
        </select>
      </div>
      <div className="col-sm-auto d-flex align-items-center">
        <div className="form-check">
          <input
            className="form-check-input"
            type="checkbox"
            id="hide-cancelled-orders"
            checked={filters.hideCancelled}
            onChange={(event) => set('hideCancelled', event.target.checked)}
          />
          <label className="form-check-label" htmlFor="hide-cancelled-orders">
            Hide cancelled
          </label>
        </div>
      </div>
    </div>
  );
}
