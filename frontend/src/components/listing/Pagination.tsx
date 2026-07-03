import type { PaginationMeta } from '../../types/product';
import { listing } from '../../content/copy';

interface PaginationProps {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
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

export default function Pagination({ meta, onPageChange }: PaginationProps) {
  if (meta.last_page <= 1) {
    return null;
  }

  const entries = getPageEntries(meta.current_page, meta.last_page);

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
