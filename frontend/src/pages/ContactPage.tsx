import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import { buildBreadcrumbJsonLd } from '../services/structuredData';
import { breadcrumbLabels, contact } from '../content/copy';

export default function ContactPage() {
  const breadcrumbItems = [{ label: breadcrumbLabels.home, to: '/' }, { label: contact.title }];

  return (
    <div className="container py-4">
      <Seo
        title={contact.seoTitle}
        description={contact.seoDescription}
        canonicalPath="/contact"
        jsonLd={buildBreadcrumbJsonLd(breadcrumbItems)}
      />

      <Breadcrumbs items={breadcrumbItems} />

      <article className="mt-3" style={{ maxWidth: '32rem' }}>
        <h1 className="mb-3">{contact.title}</h1>
        <p className="lead">{contact.intro}</p>

        <dl className="row mt-4">
          <dt className="col-sm-4">{contact.companyLabel}</dt>
          <dd className="col-sm-8">{contact.companyName}</dd>

          <dt className="col-sm-4">{contact.emailLabel}</dt>
          <dd className="col-sm-8">{contact.email}</dd>

          <dt className="col-sm-4">{contact.phoneLabel}</dt>
          <dd className="col-sm-8">{contact.phone}</dd>

          <dt className="col-sm-4">{contact.addressLabel}</dt>
          <dd className="col-sm-8">{contact.address}</dd>
        </dl>
      </article>
    </div>
  );
}
