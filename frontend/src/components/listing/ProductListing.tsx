import type { PaginationMeta, Product, ProductFilters } from '../../types/product';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import EmptyState from '../EmptyState';
import ProductGrid from '../product/ProductGrid';
import Pagination from './Pagination';
import ListingControls from './ListingControls';
import { listing } from '../../content/copy';

interface ProductListingProps {
  products: Product[];
  meta: PaginationMeta | null;
  isLoading: boolean;
  error: string | null;
  filters: ProductFilters;
  onFiltersChange: (patch: Partial<ProductFilters>) => void;
  emptyMessage?: string;
}

/**
 * Shared listing UI (search/sort/filter controls + grid + pagination +
 * loading/error/empty states) used identically by the category and search
 * pages, which differ only in the data-fetching hook that feeds it.
 */
export default function ProductListing({
  products,
  meta,
  isLoading,
  error,
  filters,
  onFiltersChange,
  emptyMessage,
}: ProductListingProps) {
  return (
    <div>
      <ListingControls filters={filters} onChange={onFiltersChange} />

      {isLoading && <LoadingState message={listing.loading} />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && products.length === 0 && (
        <EmptyState title={listing.emptyTitle} message={emptyMessage} />
      )}
      {!isLoading && !error && products.length > 0 && (
        <>
          <ProductGrid products={products} />
          {meta && (
            <div className="mt-4">
              <Pagination meta={meta} onPageChange={(page) => onFiltersChange({ page })} />
            </div>
          )}
        </>
      )}
    </div>
  );
}
