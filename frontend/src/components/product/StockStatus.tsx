import type { Inventory } from '../../types/product';
import { stock } from '../../content/copy';

interface StockStatusProps {
  inventory: Inventory | null | undefined;
}

export default function StockStatus({ inventory }: StockStatusProps) {
  if (!inventory) {
    return <span className="badge badge-stock badge-stock--unknown">{stock.unknown}</span>;
  }

  if (!inventory.is_in_stock) {
    return <span className="badge badge-stock badge-stock--out">{stock.outOfStock}</span>;
  }

  if (inventory.is_low_stock) {
    return (
      <span className="badge badge-stock badge-stock--low">{stock.lowStock(inventory.available_quantity)}</span>
    );
  }

  return <span className="badge badge-stock badge-stock--in">{stock.inStock}</span>;
}
