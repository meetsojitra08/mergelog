import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { api, ApiError } from './api';
import type { User } from './types';

interface AuthContextValue {
  token: string | null;
  user: User | null;
  loading: boolean;
  sessionError: string | null;
  retrySession: () => void;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, confirmation: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('mergelog_token'));
  const [user, setUser] = useState<User | null>(null);
  const currentUser = useRef<User | null>(null);
  const [loading, setLoading] = useState(Boolean(token));
  const [sessionError, setSessionError] = useState<string | null>(null);
  const [retryCount, setRetryCount] = useState(0);

  function updateUser(nextUser: User | null): void {
    currentUser.current = nextUser;
    setUser(nextUser);
  }

  useEffect(() => {
    if (!token) {
      setLoading(false);
      setSessionError(null);
      return;
    }

    let active = true;
    setLoading(true);
    setSessionError(null);
    api.me(token)
      .then((currentUser) => {
        if (active) updateUser(currentUser);
      })
      .catch((reason: unknown) => {
        if (active) {
          if (reason instanceof ApiError && reason.status === 401) {
            localStorage.removeItem('mergelog_token');
            setToken(null);
            updateUser(null);
          } else if (!currentUser.current) {
            setSessionError('Unable to verify your session. Please try again.');
          }
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => { active = false; };
  }, [token, retryCount]);

  function retrySession(): void {
    setLoading(true);
    setSessionError(null);
    setRetryCount((count) => count + 1);
  }

  async function login(email: string, password: string): Promise<void> {
    const result = await api.login(email, password);
    localStorage.setItem('mergelog_token', result.token);
    setToken(result.token);
    updateUser(result.user);
  }

  async function register(name: string, email: string, password: string, confirmation: string): Promise<void> {
    const result = await api.register({
      name,
      email,
      password,
      password_confirmation: confirmation,
    });
    localStorage.setItem('mergelog_token', result.token);
    setToken(result.token);
    updateUser(result.user);
  }

  async function logout(): Promise<void> {
    const currentToken = token;
    localStorage.removeItem('mergelog_token');
    setToken(null);
    updateUser(null);
    if (currentToken) {
      try {
        await api.logout(currentToken);
      } catch {
        // Local logout remains complete if the API is unavailable.
      }
    }
  }

  return (
    <AuthContext.Provider value={{ token, user, loading, sessionError, retrySession, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider.');
  return context;
}
