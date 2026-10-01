import Icon from '../icons/Icon';
import type { PaginationMeta } from '../../types/product';
import { listing } from '../../content/copy';

interface PaginationProps {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  /** 'default' (unchanged) is Bootstrap's own bordered pagination — every admin page and OrdersPage rely on that exact look. 'minimal' is a plain-text/circle-number style (by request, for ReviewsSection specifically) — same component, same page-window logic, just different markup/classNames so it doesn't touch the shared .pagination/.page-item/.page-link Bootstrap classes the default variant still uses. */
  variant?: 'default' | 'minimal';
}

type PageEntry = number | 'ellipsis';

/** Windows page numbers to first, last, and a small range around the current page, so pagination stays usable across many pages. */
function getPageEntries(current: number, last: number): PageEntry[] {
  const delta = 2;
  const entries: PageEntry[] = [];
  let previous: number | undefined;

  for (let page = 1; page <= last; page++) {
    const isEdge = page === 1 || page === last;
    const isNearCurrent = page >= current - delta && page <= current + delta;

    if (!isEdge && !isNearCurrent) {
      continue;
    }

    if (previous !== undefined && page - previous > 1) {
      entries.push('ellipsis');
    }

    entries.push(page);
    previous = page;
  }

  return entries;
}

export default function Pagination({ meta, onPageChange, variant = 'default' }: PaginationProps) {
  if (meta.last_page <= 1) {
    return null;
  }

  const entries = getPageEntries(meta.current_page, meta.last_page);

  if (variant === 'minimal') {
    return (
      <nav aria-label={listing.paginationAria}>
        <ul className="miswak-pagination">
          <li>
            <button
              type="button"
              className="miswak-pagination__arrow"
              onClick={() => onPageChange(meta.current_page - 1)}
              disabled={meta.current_page === 1}
              aria-label={listing.previous}
            >
              <Icon name="chevron-left" />
            </button>
          </li>
          {entries.map((entry, index) =>
            entry === 'ellipsis' ? (
              <li key={`ellipsis-${index}`} className="miswak-pagination__ellipsis" aria-hidden="true">
                &hellip;
              </li>
            ) : (
              <li key={entry}>
                <button
                  type="button"
                  className={`miswak-pagination__page ${entry === meta.current_page ? 'is-active' : ''}`}
                  aria-current={entry === meta.current_page ? 'page' : undefined}
                  onClick={() => onPageChange(entry)}
                >
                  {entry}
                </button>
              </li>
            ),
          )}
          <li>
            <button
              type="button"
              className="miswak-pagination__arrow"
              onClick={() => onPageChange(meta.current_page + 1)}
              disabled={meta.current_page === meta.last_page}
              aria-label={listing.next}
            >
              <Icon name="chevron-right" />
            </button>
          </li>
        </ul>
      </nav>
    );
  }

  return (
    <nav aria-label={listing.paginationAria}>
      <ul className="pagination justify-content-center flex-wrap">
        <li className={`page-item ${meta.current_page === 1 ? 'disabled' : ''}`}>
          <button
            type="button"
            className="page-link"
            onClick={() => onPageChange(meta.current_page - 1)}
            disabled={meta.current_page === 1}
          >
            {listing.previous}
          </button>
        </li>
        {entries.map((entry, index) =>
          entry === 'ellipsis' ? (
            <li key={`ellipsis-${index}`} className="page-item disabled">
              <span className="page-link">&hellip;</span>
            </li>
          ) : (
            <li key={entry} className={`page-item ${entry === meta.current_page ? 'active' : ''}`}>
              <button type="button" className="page-link" onClick={() => onPageChange(entry)}>
                {entry}
              </button>
            </li>
          ),
        )}
        <li className={`page-item ${meta.current_page === meta.last_page ? 'disabled' : ''}`}>
          <button
            type="button"
            className="page-link"
            onClick={() => onPageChange(meta.current_page + 1)}
            disabled={meta.current_page === meta.last_page}
          >
            {listing.next}
          </button>
        </li>
      </ul>
    </nav>
  );
}
