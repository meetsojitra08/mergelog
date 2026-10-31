import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { api } from './api';
import type { User } from './types';

interface AuthContextValue {
  token: string | null;
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, confirmation: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('mergelog_token'));
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(Boolean(token));

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }

    let active = true;
    api.me(token)
      .then((currentUser) => {
        if (active) setUser(currentUser);
      })
      .catch(() => {
        if (active) {
          localStorage.removeItem('mergelog_token');
          setToken(null);
          setUser(null);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => { active = false; };
  }, [token]);

  async function login(email: string, password: string): Promise<void> {
    const result = await api.login(email, password);
    localStorage.setItem('mergelog_token', result.token);
    setToken(result.token);
    setUser(result.user);
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
    setUser(result.user);
  }

  async function logout(): Promise<void> {
    const currentToken = token;
    localStorage.removeItem('mergelog_token');
    setToken(null);
    setUser(null);
    if (currentToken) {
      try {
        await api.logout(currentToken);
      } catch {
        // Local logout remains complete if the API is unavailable.
      }
    }
  }

  return (
    <AuthContext.Provider value={{ token, user, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider.');
  return context;
}
