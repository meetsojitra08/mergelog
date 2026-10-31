import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { Loading } from './Loading';
import { Navbar } from './Navbar';
import { ErrorMessage } from './ErrorMessage';

export function ProtectedRoute() {
  const { token, user, loading, sessionError, retrySession } = useAuth();
  if (loading) return <Loading />;
  if (token && sessionError) return <main className="mx-auto max-w-md p-6">
    <ErrorMessage message={sessionError} />
    <button type="button" onClick={retrySession} className="mt-4 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Retry</button>
  </main>;
  if (!token || !user) return <Navigate to="/login" replace />;
  return <><Navbar /><Outlet /></>;
}
