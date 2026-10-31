import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { Loading } from './Loading';
import { Navbar } from './Navbar';

export function ProtectedRoute() {
  const { token, user, loading } = useAuth();
  if (loading) return <Loading />;
  if (!token || !user) return <Navigate to="/login" replace />;
  return <><Navbar /><Outlet /></>;
}
