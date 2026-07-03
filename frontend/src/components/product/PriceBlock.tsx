import type { Price, Promotion } from '../../types/product';
import { formatPrice, formatPromotionValue } from '../../services/productCatalog';
import { price as priceCopy } from '../../content/copy';

interface PriceBlockProps {
  price?: Price;
  promotion?: Promotion;
  size?: 'sm' | 'lg';
}

export default function PriceBlock({ price, promotion, size = 'sm' }: PriceBlockProps) {
  if (!price) {
    return <span className="price__unavailable">{priceCopy.unavailable}</span>;
  }

  return (
    <div className={`price price--${size}`}>
      <span className={`price__current${price.is_on_sale ? ' is-sale' : ''}`}>
        {formatPrice(price.amount, price.currency)}
      </span>
      {price.is_on_sale && price.compare_at_amount !== null && (
        <span className="price__compare">{formatPrice(price.compare_at_amount, price.currency)}</span>
      )}
      {promotion && (
        <span className="badge badge-promotion" title={promotion.name}>
          {formatPromotionValue(promotion)}
        </span>
      )}
    </div>
  );
}
