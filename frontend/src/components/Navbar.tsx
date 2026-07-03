import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export default function Navbar() {
  const { user, isAuthenticated, isLoading, logout } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  return (
    <nav className="navbar navbar-expand-sm navbar-light bg-white border-bottom">
      <div className="container">
        <Link className="navbar-brand fw-bold" to="/">
          Smisul
        </Link>

        {!isLoading && (
          <div className="d-flex align-items-center gap-2">
            {isAuthenticated ? (
              <>
                <span className="text-muted d-none d-sm-inline me-1">Hi, {user?.first_name}</span>
                <Link className="btn btn-outline-secondary btn-sm" to="/profile">
                  Profile
                </Link>
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  onClick={() => void handleLogout()}
                >
                  Log out
                </button>
              </>
            ) : (
              <>
                <Link className="btn btn-outline-primary btn-sm" to="/login">
                  Log in
                </Link>
                <Link className="btn btn-primary btn-sm" to="/register">
                  Register
                </Link>
              </>
            )}
          </div>
        )}
      </div>
    </nav>
  );
}
