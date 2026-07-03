import { useSearchParams } from 'react-router-dom';
import { useProducts } from '../hooks/useProducts';
import Breadcrumbs from '../components/Breadcrumbs';
import Seo from '../components/Seo';
import ProductListing from '../components/listing/ProductListing';
import { filtersFromSearchParams, filtersToSearchParams } from '../services/listingFilters';
import { breadcrumbLabels, listing, seo } from '../content/copy';
import type { ProductFilters } from '../types/product';

export default function SearchPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = filtersFromSearchParams(searchParams);

  const { products, meta, isLoading, error } = useProducts(filters);

  function handleFiltersChange(patch: Partial<ProductFilters>): void {
    setSearchParams(filtersToSearchParams({ ...filters, ...patch }));
  }

  const heading = filters.search ? seo.searchHeadingQuery(filters.search) : seo.searchHeadingAll;
  const pageTitle = filters.search ? seo.searchTitleQuery(filters.search) : seo.searchTitleAll;

  return (
    <div className="container py-4">
      <Seo title={pageTitle} description={seo.searchDescription} />
      <Breadcrumbs items={[{ label: breadcrumbLabels.home, to: '/' }, { label: breadcrumbLabels.search }]} />
      <h1 className="mb-4 mt-3">{heading}</h1>

      <ProductListing
        products={products}
        meta={meta}
        isLoading={isLoading}
        error={error}
        filters={filters}
        onFiltersChange={handleFiltersChange}
        emptyMessage={listing.searchEmpty}
      />
    </div>
  );
}
