import type {
  ApiErrorBody,
  AuthResponse,
  CreateRepositoryInput,
  CreateRepositoryResponse,
  RegisterInput,
  Repository,
  User,
} from './types';

const apiUrl = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace(/\/$/, '');

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (options.body) {
    headers.set('Content-Type', 'application/json');
  }
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  let response: Response;
  try {
    response = await fetch(`${apiUrl}${path}`, { ...options, headers });
  } catch {
    throw new Error(`Could not reach the API at ${apiUrl}. Check that the backend is running and CORS allows this frontend origin.`);
  }

  if (!response.ok) {
    let body: ApiErrorBody = {};
    try {
      const parsed: unknown = await response.json();
      if (parsed !== null && typeof parsed === 'object') body = parsed as ApiErrorBody;
    } catch {
      body.message = response.statusText;
    }
    const validationMessage = body.errors ? Object.values(body.errors).flat()[0] : undefined;
    throw new ApiError(validationMessage || body.message || `Request failed (${response.status}).`, response.status);
  }

  if (response.status === 204) {
    return undefined as T;
  }
  return (await response.json()) as T;
}

export const api = {
  login(email: string, password: string): Promise<AuthResponse> {
    return request<AuthResponse>('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
  },

  register(input: RegisterInput): Promise<AuthResponse> {
    return request<AuthResponse>('/register', {
      method: 'POST',
      body: JSON.stringify(input),
    });
  },

  me(token: string): Promise<User> {
    return request<User>('/me', {}, token);
  },

  logout(token: string): Promise<void> {
    return request<void>('/logout', { method: 'POST' }, token);
  },

  listRepositories(token: string): Promise<Repository[]> {
    return request<Repository[]>('/repositories', {}, token);
  },

  createRepository(token: string, input: CreateRepositoryInput): Promise<CreateRepositoryResponse> {
    return request<CreateRepositoryResponse>('/repositories', {
      method: 'POST',
      body: JSON.stringify(input),
    }, token);
  },

  deleteRepository(token: string, repositoryId: number): Promise<void> {
    return request<void>(`/repositories/${repositoryId}`, { method: 'DELETE' }, token);
  },
};
