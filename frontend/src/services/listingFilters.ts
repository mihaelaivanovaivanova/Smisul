import type { ProductFilters, ProductSort } from '../types/product';

const VALID_SORTS: ProductSort[] = ['newest', 'price_asc', 'price_desc', 'name'];

/** Reads listing filters back out of the URL's query string, so filtered/sorted/paged views are shareable and back-button-safe. */
export function filtersFromSearchParams(params: URLSearchParams): ProductFilters {
  const filters: ProductFilters = {};

  const search = params.get('q');
  if (search) {
    filters.search = search;
  }

  const sort = params.get('sort');
  if (sort && VALID_SORTS.includes(sort as ProductSort)) {
    filters.sort = sort as ProductSort;
  }

  const minPrice = params.get('min_price');
  if (minPrice && !Number.isNaN(Number(minPrice))) {
    filters.min_price = Number(minPrice);
  }

  const maxPrice = params.get('max_price');
  if (maxPrice && !Number.isNaN(Number(maxPrice))) {
    filters.max_price = Number(maxPrice);
  }

  const page = params.get('page');
  if (page && !Number.isNaN(Number(page))) {
    filters.page = Number(page);
  }

  return filters;
}

export function filtersToSearchParams(filters: ProductFilters): URLSearchParams {
  const params = new URLSearchParams();

  if (filters.search) {
    params.set('q', filters.search);
  }
  if (filters.sort) {
    params.set('sort', filters.sort);
  }
  if (filters.min_price !== undefined) {
    params.set('min_price', String(filters.min_price));
  }
  if (filters.max_price !== undefined) {
    params.set('max_price', String(filters.max_price));
  }
  if (filters.page && filters.page > 1) {
    params.set('page', String(filters.page));
  }

  return params;
}
