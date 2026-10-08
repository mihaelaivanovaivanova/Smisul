import { useEffect, useState } from 'react';
import type { OrderFilters } from '../components/admin/OrderFilterBar';

interface OrderListState {
  page: number;
  filters: OrderFilters;
}

/**
 * The admin order lists' page and filters, kept across navigation: opening
 * an order and coming back restores the list exactly as it was left, instead
 * of resetting to every order. Stored per list in sessionStorage (so the
 * dashboard and the Orders page don't share state), as a per-browser
 * convenience only - every storage access is guarded, and the list falls back
 * to its defaults if storage is unavailable or holds something unreadable.
 */
export function usePersistedOrderListState(storageKey: string, defaultFilters: OrderFilters) {
  const [state, setState] = useState<OrderListState>(() => readState(storageKey, defaultFilters));

  useEffect(() => {
    writeState(storageKey, state);
  }, [storageKey, state]);

  function setPage(page: number) {
    setState((previous) => ({ ...previous, page }));
  }

  // A filter change always starts again from the first page.
  function changeFilters(filters: OrderFilters) {
    setState({ page: 1, filters });
  }

  return { page: state.page, filters: state.filters, setPage, changeFilters };
}

function readState(storageKey: string, defaultFilters: OrderFilters): OrderListState {
  const fallback: OrderListState = { page: 1, filters: defaultFilters };

  try {
    const raw = window.sessionStorage.getItem(storageKey);
    if (raw === null) {
      return fallback;
    }

    const parsed = JSON.parse(raw) as Partial<OrderListState>;
    const page = Number(parsed.page);

    return {
      page: Number.isInteger(page) && page >= 1 ? page : 1,
      filters: { ...defaultFilters, ...parsed.filters },
    };
  } catch {
    return fallback;
  }
}

function writeState(storageKey: string, state: OrderListState): void {
  try {
    window.sessionStorage.setItem(storageKey, JSON.stringify(state));
  } catch {
    // Storage unavailable (private mode, blocked site data) - the list still
    // works, it just won't be remembered.
  }
}
