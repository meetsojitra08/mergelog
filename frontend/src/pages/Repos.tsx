import { useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '../AuthContext';
import { api } from '../api';
import { CopyField } from '../components/CopyField';
import { EmptyState } from '../components/EmptyState';
import { ErrorMessage } from '../components/ErrorMessage';
import { Loading } from '../components/Loading';
import type { CreateRepositoryResponse, Repository } from '../types';

export function Repos() {
  const { token } = useAuth();
  const [repositories, setRepositories] = useState<Repository[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [fullName, setFullName] = useState('');
  const [githubToken, setGithubToken] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [created, setCreated] = useState<CreateRepositoryResponse | null>(null);

  async function loadRepositories(): Promise<void> {
    if (!token) return;
    setLoading(true); setError('');
    try { setRepositories(await api.listRepositories(token)); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not load repositories.'); }
    finally { setLoading(false); }
  }

  useEffect(() => { void loadRepositories(); }, [token]);

  async function addRepository(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault(); if (!token) return;
    setSubmitting(true); setError(''); setCreated(null);
    try {
      const repository = await api.createRepository(token, {
        full_name: fullName.trim(),
        ...(githubToken.trim() ? { github_token: githubToken.trim() } : {}),
      });
      setCreated(repository); setFullName(''); setGithubToken(''); await loadRepositories();
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not add repository.'); }
    finally { setSubmitting(false); }
  }

  async function removeRepository(repository: Repository): Promise<void> {
    if (!token || !window.confirm(`Delete ${repository.full_name}?`)) return;
    setError('');
    try {
      await api.deleteRepository(token, repository.id);
      setRepositories((current) => current.filter((item) => item.id !== repository.id));
      if (created?.id === repository.id) setCreated(null);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not delete repository.'); }
  }

  return <main className="mx-auto max-w-5xl space-y-8 px-4 py-8">
    <div><h1 className="text-2xl font-semibold text-gray-900">Repositories</h1><p className="mt-1 text-gray-600">Connect a GitHub repository to collect merged pull requests.</p></div>
    {error && <ErrorMessage message={error} />}
    {created && <section className="space-y-4 rounded-lg border border-green-200 bg-green-50 p-6" aria-live="polite">
      <div><h2 className="text-lg font-semibold text-gray-900">{created.full_name} is connected</h2><p className="text-sm text-gray-600">Add these values as a webhook in your GitHub repository settings.</p></div>
      <CopyField label="Payload URL" value={created.webhook_url} /><CopyField label="Secret" value={created.webhook_secret} />
      <ol className="list-inside list-decimal space-y-1 text-sm text-gray-700"><li>In GitHub, open Settings &gt; Webhooks &gt; Add webhook.</li><li>Set Content type to <code>application/json</code> and paste the payload URL and secret.</li><li>Select the pull request event, then save. Mergelog processes merged pull requests.</li></ol>
    </section>}
    <section className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <h2 className="mb-4 text-lg font-semibold text-gray-900">Add repository</h2>
      <form onSubmit={addRepository} className="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
        <div><label htmlFor="full-name" className="mb-1 block text-sm font-medium text-gray-700">GitHub repository</label><input id="full-name" required placeholder="owner/repo" value={fullName} onChange={(event) => setFullName(event.target.value)} className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" /></div>
        <div><label htmlFor="github-token" className="mb-1 block text-sm font-medium text-gray-700">GitHub token <span className="font-normal text-gray-500">(optional)</span></label><input id="github-token" type="password" autoComplete="off" value={githubToken} onChange={(event) => setGithubToken(event.target.value)} className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" /></div>
        <button disabled={submitting} className="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 disabled:opacity-60">{submitting ? 'Adding...' : 'Add repository'}</button>
      </form>
    </section>
    <section><h2 className="mb-4 text-lg font-semibold text-gray-900">Your repositories</h2>
      {loading ? <Loading /> : repositories.length === 0 ? <EmptyState>Add a repository above to start collecting change entries.</EmptyState> : <ul className="space-y-3">{repositories.map((repository) => <li key={repository.id} className="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <div className="min-w-0 flex-1"><h3 className="truncate font-semibold text-gray-900">{repository.full_name}</h3><p className="mt-1 text-sm text-gray-600"><span className="mr-4">Pending: {repository.pending_count ?? 0}</span><span>Drafted: {repository.drafted_count ?? 0}</span></p></div>
        <button type="button" onClick={() => void removeRepository(repository)} className="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50 focus:ring-2 focus:ring-red-500">Delete</button>
      </li>)}</ul>}
    </section>
  </main>;
}
