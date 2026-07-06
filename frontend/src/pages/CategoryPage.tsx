import { useParams, useSearchParams } from 'react-router-dom';
import { useCategory } from '../hooks/useCategory';
import { useCategoryProducts } from '../hooks/useCategoryProducts';
import Breadcrumbs from '../components/Breadcrumbs';
import LoadingState from '../components/LoadingState';
import Seo from '../components/Seo';
import ProductListing from '../components/listing/ProductListing';
import NotFoundPage from './NotFoundPage';
import { filtersFromSearchParams, filtersToSearchParams } from '../services/listingFilters';
import { buildBreadcrumbJsonLd } from '../services/structuredData';
import { breadcrumbLabels, category as categoryCopy, listing, seo } from '../content/copy';
import type { ProductFilters } from '../types/product';

export default function CategoryPage() {
  const { slug = '' } = useParams<{ slug: string }>();
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = filtersFromSearchParams(searchParams);

  const { category, isLoading: isCategoryLoading, error: categoryError } = useCategory(slug);
  const { products, meta, isLoading: areProductsLoading, error: productsError } = useCategoryProducts(slug, filters);

  function handleFiltersChange(patch: Partial<ProductFilters>): void {
    setSearchParams(filtersToSearchParams({ ...filters, ...patch }));
  }

  if (isCategoryLoading) {
    return <LoadingState message={categoryCopy.loading} />;
  }

  // As with the product page, a fetch failure here (unknown slug or a
  // transient error) is treated as "not found" since the hook only exposes
  // a message string rather than the underlying HTTP status.
  if (categoryError || !category) {
    return <NotFoundPage />;
  }

  const breadcrumbItems = [{ label: breadcrumbLabels.home, to: '/' }, { label: category.name }];

  return (
    <div className="container py-4">
      <Seo
        title={`${category.name}${seo.categoryTitleSuffix}`}
        description={category.description ?? seo.categoryDescriptionFallback}
        jsonLd={buildBreadcrumbJsonLd(breadcrumbItems)}
      />
      <Breadcrumbs items={breadcrumbItems} />
      <h1 className="mb-2 mt-3">{category.name}</h1>
      {category.description && <p className="text-muted">{category.description}</p>}

      <ProductListing
        products={products}
        meta={meta}
        isLoading={areProductsLoading}
        error={productsError}
        filters={filters}
        onFiltersChange={handleFiltersChange}
        emptyMessage={listing.categoryEmpty}
      />
    </div>
  );
}
