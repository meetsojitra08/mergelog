export interface User {
  id: number;
  name: string;
  email: string;
}

export interface Repository {
  id: number;
  full_name: string;
  pending_count?: number;
  drafted_count?: number;
  created_at?: string;
  webhook_url?: string;
  webhook_secret?: string;
}

export type Category = 'feature' | 'fix' | 'breaking' | 'other';
export type EntryStatus = 'pending_ai' | 'drafted' | 'failed';
export type ReleaseStatus = 'draft' | 'published';

export interface ChangeEntry {
  id: number;
  category: Category;
  summary: string;
  status: EntryStatus;
}

export interface Release {
  id: number;
  version: string;
  status: ReleaseStatus;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface CreateRepositoryResponse extends Repository {
  webhook_url: string;
  webhook_secret: string;
}

export interface CreateRepositoryInput {
  full_name: string;
  github_token?: string;
}

export interface RegisterInput {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}
