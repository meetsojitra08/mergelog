import { useState, type FormEvent } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { ErrorMessage } from '../components/ErrorMessage';

export function Login() {
  const { login, token, loading } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  if (loading) return <p className="py-8 text-center text-gray-600">Loading...</p>;
  if (token) return <Navigate to="/repos" replace />;

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault(); setError(''); setSubmitting(true);
    try { await login(email, password); navigate('/repos', { replace: true }); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Login failed.'); }
    finally { setSubmitting(false); }
  }

  return <main className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12"><section className="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h1 className="mb-2 text-2xl font-semibold text-gray-900">Sign in to Mergelog</h1><p className="mb-6 text-sm text-gray-600">Review and publish clear release notes.</p>
    {error && <div className="mb-4"><ErrorMessage message={error} /></div>}
    <form onSubmit={handleSubmit} className="space-y-4">
      <div><label htmlFor="email" className="mb-1 block text-sm font-medium text-gray-700">Email</label><input id="email" name="email" type="email" autoComplete="email" required value={email} onChange={(event) => setEmail(event.target.value)} className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" /></div>
      <div><label htmlFor="password" className="mb-1 block text-sm font-medium text-gray-700">Password</label><input id="password" name="password" type="password" autoComplete="current-password" required value={password} onChange={(event) => setPassword(event.target.value)} className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" /></div>
      <button disabled={submitting} className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 disabled:opacity-60">{submitting ? 'Signing in...' : 'Sign in'}</button>
    </form><p className="mt-5 text-center text-sm text-gray-600">New to Mergelog? <Link className="font-medium text-indigo-700 hover:text-indigo-900" to="/register">Create an account</Link></p>
  </section></main>;
}
