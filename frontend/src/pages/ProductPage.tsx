import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useProduct } from '../hooks/useProduct';
import {
  getActivePromotion,
  getDefaultVariant,
  getDownloads,
  getGalleryImages,
  getVideos,
  getVariantPrice,
  sortVariantsByPackSize,
} from '../services/productCatalog';
import Breadcrumbs from '../components/Breadcrumbs';
import LoadingState from '../components/LoadingState';
import Seo from '../components/Seo';
import ProductGallery from '../components/product/ProductGallery';
import { ProductDownloads, ProductVideos } from '../components/product/ProductMediaExtras';
import VariantPicker from '../components/product/VariantPicker';
import PriceBlock from '../components/product/PriceBlock';
import StockStatus from '../components/product/StockStatus';
import AddToCartButton from '../components/product/AddToCartButton';
import FavoriteButton from '../components/product/FavoriteButton';
import NotFoundPage from './NotFoundPage';
import { breadcrumbLabels, product as productCopy, seo } from '../content/copy';
import type { ProductVariant } from '../types/product';

export default function ProductPage() {
  const { slug = '' } = useParams<{ slug: string }>();
  const { product, isLoading, error } = useProduct(slug);
  const [selectedVariant, setSelectedVariant] = useState<ProductVariant | null>(null);

  if (isLoading) {
    return <LoadingState message={productCopy.loading} />;
  }

  // The API returns 404 for unknown/unpublished slugs; useProduct only
  // surfaces a message string (not the HTTP status), so any fetch failure
  // here is treated as "not found" rather than a distinct transient error.
  if (error || !product) {
    return <NotFoundPage />;
  }

  const variants = sortVariantsByPackSize(product.variants);
  const activeVariant = selectedVariant ?? getDefaultVariant(product) ?? variants[0];
  const price = activeVariant ? getVariantPrice(activeVariant) : undefined;
  const promotion = getActivePromotion(product);
  const images = getGalleryImages(product);
  const videos = getVideos(product);
  const downloads = getDownloads(product);
  const category = product.categories[0];

  const seoTitle = product.seo?.meta_title ?? `${product.name}${seo.productTitleSuffix}`;
  const seoDescription = product.seo?.meta_description ?? product.short_description ?? seo.productDescriptionFallback;
  const ogImage = product.seo?.og_image_url ?? images[0]?.url ?? null;

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.short_description ?? product.description ?? undefined,
    image: images.map((image) => image.url),
    sku: activeVariant?.sku,
    inLanguage: 'bg',
    ...(activeVariant &&
      price && {
        offers: {
          '@type': 'Offer',
          priceCurrency: price.currency,
          price: price.amount.toFixed(2),
          availability: activeVariant.inventory?.is_in_stock
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
          url: `${window.location.origin}/products/${product.slug}`,
        },
      }),
  };

  return (
    <div className="container py-4">
      <Seo title={seoTitle} description={seoDescription} ogImage={ogImage} ogType="product" jsonLd={jsonLd} />

      <Breadcrumbs
        items={[
          { label: breadcrumbLabels.home, to: '/' },
          ...(category ? [{ label: category.name, to: `/categories/${category.slug}` }] : []),
          { label: product.name },
        ]}
      />

      <div className="row g-4 mt-1">
        <div className="col-12 col-lg-6">
          <ProductGallery images={images} productName={product.name} />
        </div>
        <div className="col-12 col-lg-6">
          <div className="d-flex align-items-start justify-content-between gap-3 mb-3">
            <h1 className="h3 mb-0">{product.name}</h1>
            {activeVariant && <FavoriteButton productVariantId={activeVariant.id} compact />}
          </div>

          <div className="mb-3">
            <PriceBlock price={price} promotion={promotion} size="lg" />
          </div>

          <div className="mb-3">
            <StockStatus inventory={activeVariant?.inventory} />
          </div>

          {product.short_description && <p className="text-muted">{product.short_description}</p>}

          <VariantPicker variants={variants} selectedId={activeVariant?.id ?? 0} onSelect={setSelectedVariant} />

          {activeVariant && (
            <AddToCartButton
              key={activeVariant.id}
              productVariantId={activeVariant.id}
              inventory={activeVariant.inventory}
            />
          )}
        </div>
      </div>

      <div className="row mt-5">
        <div className="col-12 col-lg-8">
          {product.description && (
            <div className="mb-4">
              <h2 className="h5">{productCopy.descriptionTitle}</h2>
              <p style={{ whiteSpace: 'pre-line' }}>{product.description}</p>
            </div>
          )}

          <ProductVideos videos={videos} />
          <ProductDownloads downloads={downloads} />
        </div>
      </div>
    </div>
  );
}
