import { createContext } from 'react';
import type * as authApi from '../api/auth';
import type { User } from '../types/auth';

export interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (payload: authApi.LoginPayload) => Promise<User>;
  logout: () => Promise<void>;
  setUser: (user: User) => void;
}

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);
