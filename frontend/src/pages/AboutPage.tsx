import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import { buildBreadcrumbJsonLd } from '../services/structuredData';
import { about, breadcrumbLabels } from '../content/copy';

export default function AboutPage() {
  const breadcrumbItems = [{ label: breadcrumbLabels.home, to: '/' }, { label: about.title }];

  return (
    <div className="container py-4">
      <Seo
        title={about.seoTitle}
        description={about.seoDescription}
        canonicalPath="/about"
        jsonLd={buildBreadcrumbJsonLd(breadcrumbItems)}
      />

      <Breadcrumbs items={breadcrumbItems} />

      <article className="mt-3" style={{ maxWidth: '48rem' }}>
        <h1 className="mb-3">{about.title}</h1>
        <p className="lead">{about.intro}</p>

        {about.sections.map((section) => (
          <section key={section.title} className="mt-4">
            <h2 className="h5">{section.title}</h2>
            <p>{section.text}</p>
          </section>
        ))}
      </article>
    </div>
  );
}
