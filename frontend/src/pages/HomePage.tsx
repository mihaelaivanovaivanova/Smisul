import { useAuth } from '../hooks/useAuth';

export default function HomePage() {
  const { user, isAuthenticated } = useAuth();

  return (
    <div className="container py-5 text-center">
      <h1 className="mb-3">Welcome to Smisul</h1>
      {isAuthenticated ? (
        <p className="lead">You are logged in as {user?.full_name}.</p>
      ) : (
        <p className="lead">Please log in or create an account to get started.</p>
      )}
    </div>
  );
}
