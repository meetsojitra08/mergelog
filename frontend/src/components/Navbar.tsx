import { Link } from 'react-router-dom';
import { useAuth } from '../AuthContext';

export function Navbar() {
  const { user, logout } = useAuth();

  return (
    <header className="border-b border-gray-200 bg-white">
      <nav className="mx-auto flex max-w-5xl items-center gap-6 px-4 py-4" aria-label="Main navigation">
        <Link className="text-lg font-semibold text-gray-900" to="/repos">Mergelog</Link>
        <Link className="text-sm text-gray-600 hover:text-gray-900" to="/repos">Repositories</Link>
        <div className="ml-auto flex items-center gap-4">
          <span className="hidden text-sm text-gray-600 sm:inline">{user?.email}</span>
          <button type="button" onClick={() => void logout()} className="text-sm font-medium text-indigo-700 hover:text-indigo-900 focus:ring-2 focus:ring-indigo-500">Logout</button>
        </div>
      </nav>
    </header>
  );
}
