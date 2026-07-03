export type ProductStatus = 'draft' | 'published' | 'archived';
export type VariantStatus = 'active' | 'inactive';
export type PromotionType = 'percentage' | 'fixed_amount';
export type Currency = 'BGN';

export interface Price {
  currency: Currency;
  amount: number;
  compare_at_amount: number | null;
  is_on_sale: boolean;
}

export interface Inventory {
  available_quantity: number;
  is_in_stock: boolean;
  is_low_stock: boolean;
}

export interface Media {
  id: number;
  url: string;
  mime_type: string | null;
  alt_text: string | null;
  is_primary: boolean;
  sort_order: number;
}

export interface ProductVariant {
  id: number;
  sku: string;
  name: string;
  pack_size: number;
  is_default: boolean;
  status: VariantStatus;
  prices: Price[];
  inventory: Inventory | null;
}

export interface Seo {
  meta_title: string | null;
  meta_description: string | null;
  meta_keywords: string | null;
  og_title: string | null;
  og_description: string | null;
  og_image_url: string | null;
  canonical_url: string | null;
}

export interface Category {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  children: Category[];
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  status: ProductStatus;
  published_at: string | null;
  categories: Category[];
  variants: ProductVariant[];
  media: Media[];
  seo: Seo | null;
  active_promotions?: Promotion[];
}

export interface Promotion {
  id: number;
  name: string;
  description: string | null;
  type: PromotionType;
  value: number;
  code: string | null;
  starts_at: string | null;
  ends_at: string | null;
  usage_limit: number | null;
  used_count: number;
  is_active: boolean;
  is_currently_valid: boolean;
  products: Product[];
  categories: Category[];
}

export type ProductSort = 'newest' | 'price_asc' | 'price_desc' | 'name';

export interface ProductFilters {
  search?: string;
  category?: string;
  min_price?: number;
  max_price?: number;
  sort?: ProductSort;
  page?: number;
  per_page?: number;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface ApiResource<T> {
  data: T;
}
