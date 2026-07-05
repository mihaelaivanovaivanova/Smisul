import { Link } from 'react-router-dom';
import type { Product } from '../../types/product';
import { getActivePromotion, getDefaultVariant, getDisplayPrice, getPrimaryImage } from '../../services/productCatalog';
import { product as productCopy } from '../../content/copy';
import PriceBlock from './PriceBlock';
import StockStatus from './StockStatus';
import AddToCartButton from './AddToCartButton';
import FavoriteButton from './FavoriteButton';

interface ProductCardProps {
  product: Product;
}

export default function ProductCard({ product }: ProductCardProps) {
  const image = getPrimaryImage(product);
  const variant = getDefaultVariant(product);
  const price = getDisplayPrice(product);
  const promotion = getActivePromotion(product);

  return (
    <div className="col">
      <div className="card h-100 shadow-sm position-relative">
        {/* Add-to-cart and favorite below are siblings, not nested inside
            this link — a <button>/<a> inside an <a> is invalid HTML and
            breaks click handling. */}
        {variant && (
          <div className="position-absolute top-0 end-0 m-2" style={{ zIndex: 1 }}>
            <FavoriteButton productVariantId={variant.id} compact />
          </div>
        )}
        <Link to={`/products/${product.slug}`} className="d-flex flex-column flex-grow-1 text-decoration-none text-body">
          <div className="ratio ratio-1x1 bg-white product-card__image-wrap">
            {image ? (
              <img src={image.url} alt={image.alt_text ?? product.name} className="object-fit-cover" />
            ) : (
              <div className="d-flex align-items-center justify-content-center text-muted small">
                {productCopy.noImage}
              </div>
            )}
          </div>
          <div className="card-body d-flex flex-column flex-grow-1">
            <h3 className="h6 card-title product-card__title mb-2">{product.name}</h3>
            {product.short_description && (
              <p className="card-text text-muted small mb-2 flex-grow-1">{product.short_description}</p>
            )}
            <div className="d-flex flex-column gap-2 mt-auto">
              <PriceBlock price={price} promotion={promotion} />
              <StockStatus inventory={variant?.inventory} />
            </div>
          </div>
        </Link>

        {variant && (
          <div className="card-body pt-0">
            <AddToCartButton productVariantId={variant.id} inventory={variant.inventory} compact />
          </div>
        )}
      </div>
    </div>
  );
}
