import { useParams } from 'react-router-dom';
import { fetchLegalDocument } from '../api/legal';
import { useAsync } from '../hooks/useAsync';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import { buildBreadcrumbJsonLd } from '../services/structuredData';
import { breadcrumbLabels, legalPage as legalPageCopy } from '../content/copy';

export default function LegalPage() {
  const { slug } = useParams<{ slug: string }>();
  const { data: document, isLoading, error } = useAsync(
    () => fetchLegalDocument(slug!),
    [slug],
    legalPageCopy.loadError,
  );

  const breadcrumbItems = [{ label: breadcrumbLabels.home, to: '/' }, { label: document?.title ?? legalPageCopy.loading }];

  return (
    <div className="container py-4">
      <Seo
        title={document ? `${document.title}${legalPageCopy.seoTitleSuffix}` : legalPageCopy.loading}
        canonicalPath={`/legal/${slug}`}
        jsonLd={document ? buildBreadcrumbJsonLd(breadcrumbItems) : null}
      />

      <Breadcrumbs items={breadcrumbItems} />

      {isLoading && <LoadingState message={legalPageCopy.loading} />}
      {!isLoading && error && <ErrorState message={legalPageCopy.loadError} />}

      {!isLoading && document && (
        <article className="mt-3" style={{ maxWidth: '48rem' }}>
          <h1 className="mb-1">{document.title}</h1>
          <p className="text-muted small mb-4">
            {legalPageCopy.lastUpdatedLabel} {document.version}
          </p>
          <div style={{ whiteSpace: 'pre-wrap' }}>{document.content}</div>
        </article>
      )}
    </div>
  );
}
