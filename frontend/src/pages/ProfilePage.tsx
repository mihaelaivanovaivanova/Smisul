import { Link } from 'react-router-dom';
import ProfileDetailsForm from '../components/ProfileDetailsForm';
import ChangePasswordForm from '../components/ChangePasswordForm';
import { useAuth } from '../hooks/useAuth';
import { orders as ordersCopy, favorites as favoritesCopy, reviews as reviewsCopy } from '../content/copy';

export default function ProfilePage() {
  const { user, setUser } = useAuth();

  // ProtectedRoute guarantees a user is present by the time this renders.
  if (!user) {
    return null;
  }

  return (
    <div className="container my-5">
      <div className="row justify-content-center g-4">
        <div className="col-12 col-lg-8">
          <ProfileDetailsForm user={user} onUpdated={setUser} />
        </div>
        <div className="col-12 col-lg-8">
          <ChangePasswordForm />
        </div>
        <div className="col-12 col-lg-8 d-flex gap-2">
          <Link to="/profile/orders" className="btn btn-outline-primary">
            {ordersCopy.title}
          </Link>
          <Link to="/profile/favorites" className="btn btn-outline-primary">
            {favoritesCopy.title}
          </Link>
          <Link to="/profile/reviews" className="btn btn-outline-primary">
            {reviewsCopy.myReviews.title}
          </Link>
        </div>
      </div>
    </div>
  );
}
