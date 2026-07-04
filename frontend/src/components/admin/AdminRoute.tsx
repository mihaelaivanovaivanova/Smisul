import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

/**
 * Guards /admin/* routes: unauthenticated users are sent to /login (same
 * as ProtectedRoute), but a signed-in non-administrator gets a 404 rather
 * than a redirect to login, since revealing that /admin exists at all to
 * a logged-in customer isn't useful and a 403-ish "not found" is the
 * simpler, safer default for a route that shouldn't be discoverable.
 */
export default function AdminRoute() {
  const { user, isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="d-flex justify-content-center py-5">
        <div className="spinner-border" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (user?.role !== 'administrator') {
    return <Navigate to="/404" replace />;
  }

  return <Outlet />;
}
