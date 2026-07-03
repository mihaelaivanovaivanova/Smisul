import type { FormEvent } from 'react';
import type { ProductFilters, ProductSort } from '../../types/product';
import { listing } from '../../content/copy';

interface ListingControlsProps {
  filters: ProductFilters;
  onChange: (patch: Partial<ProductFilters>) => void;
}

const SORT_OPTIONS: { value: ProductSort; label: string }[] = [
  { value: 'newest', label: listing.sortOptions.newest },
  { value: 'price_asc', label: listing.sortOptions.price_asc },
  { value: 'price_desc', label: listing.sortOptions.price_desc },
  { value: 'name', label: listing.sortOptions.name },
];

export default function ListingControls({ filters, onChange }: ListingControlsProps) {
  function handleSearchSubmit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const search = String(formData.get('search') ?? '').trim();
    onChange({ search: search || undefined, page: 1 });
  }

  function handlePriceBlur(field: 'min_price' | 'max_price', value: string): void {
    onChange({ [field]: value === '' ? undefined : Number(value), page: 1 });
  }

  return (
    <div className="row gy-2 gx-2 align-items-end mb-4">
      <div className="col-12 col-lg-4">
        <label className="form-label small mb-1" htmlFor="listing-search">
          {listing.searchLabel}
        </label>
        <form onSubmit={handleSearchSubmit} className="d-flex gap-2">
          <input
            id="listing-search"
            type="search"
            name="search"
            className="form-control"
            placeholder={listing.searchPlaceholder}
            defaultValue={filters.search ?? ''}
          />
          <button type="submit" className="btn btn-outline-primary">
            {listing.searchButton}
          </button>
        </form>
      </div>
      <div className="col-6 col-sm-4 col-lg-3">
        <label className="form-label small mb-1" htmlFor="listing-sort">
          {listing.sortLabel}
        </label>
        <select
          id="listing-sort"
          className="form-select"
          value={filters.sort ?? 'newest'}
          onChange={(event) => onChange({ sort: event.target.value as ProductSort, page: 1 })}
        >
          {SORT_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
      <div className="col-3 col-sm-4 col-lg-2">
        <label className="form-label small mb-1" htmlFor="listing-min-price">
          {listing.minPriceLabel}
        </label>
        <input
          id="listing-min-price"
          type="number"
          min={0}
          step="0.01"
          className="form-control"
          placeholder={listing.anyPricePlaceholder}
          defaultValue={filters.min_price ?? ''}
          onBlur={(event) => handlePriceBlur('min_price', event.target.value)}
        />
      </div>
      <div className="col-3 col-sm-4 col-lg-2">
        <label className="form-label small mb-1" htmlFor="listing-max-price">
          {listing.maxPriceLabel}
        </label>
        <input
          id="listing-max-price"
          type="number"
          min={0}
          step="0.01"
          className="form-control"
          placeholder={listing.anyPricePlaceholder}
          defaultValue={filters.max_price ?? ''}
          onBlur={(event) => handlePriceBlur('max_price', event.target.value)}
        />
      </div>
    </div>
  );
}
