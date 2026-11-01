import type { OrderItem } from '../types/checkout';

/**
 * One-line label for an order line: "Product (variant)" for an ordinary
 * line, the kit's snapshotted name for a kit line (bundle_name - its
 * product_variant_id is null and it has no variant_name). bundle_name
 * survives the kit itself being deleted later (bundle_id is then null).
 */
export function getOrderItemLabel(item: OrderItem): string {
  if (item.bundle_name) {
    return item.bundle_name;
  }

  return `${item.product_name}${item.variant_name ? ` (${item.variant_name})` : ''}`;
}
