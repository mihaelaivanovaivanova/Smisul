import { Link } from 'react-router-dom';
import Seo from '../components/Seo';
import { notFound } from '../content/copy';

export default function NotFoundPage() {
  return (
    <div className="container py-5 text-center">
      <Seo title={notFound.seoTitle} description={notFound.seoDescription} />
      <h1 className="display-5 mb-3">{notFound.title}</h1>
      <p className="lead mb-4">{notFound.lead}</p>
      <Link to="/" className="btn btn-primary">
        {notFound.cta}
      </Link>
    </div>
  );
}
