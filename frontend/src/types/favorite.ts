import type { ProductVariant } from './product';

export interface Favorite {
  id: number;
  created_at: string;
  product_variant: ProductVariant;
}
