'use client';

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import {
  AuthUser,
  getStoredToken,
  getStoredUser,
  setAuthSession,
  clearAuthSession,
  fetchMe,
  logout,
} from '@/lib/auth';

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  isAuthenticated: boolean;
  setUserSession: (token: string, user: AuthUser) => void;
  logoutUser: () => Promise<void>;
  refreshProfile: () => Promise<AuthUser | null>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState<boolean>(true);

  const syncAuth = useCallback(async () => {
    const storedUser = getStoredUser();
    setUser(storedUser);
    setToken(storedUser ? 'session_active' : null);

    if (storedUser) {
      try {
        const freshUser = await fetchMe();
        if (freshUser) {
          setUser(freshUser);
          setToken('session_active');
        } else {
          setUser(null);
          setToken(null);
        }
      } catch {
        // Keep stored user if offline or network glitch
      }
    } else {
      setUser(null);
      setToken(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    syncAuth();

    function handleStorageChange() {
      syncAuth();
    }

    function handleUserUpdate(e: Event) {
      const customEvent = e as CustomEvent<AuthUser | null>;
      if (customEvent.detail !== undefined) {
        setUser(customEvent.detail);
      } else {
        syncAuth();
      }
    }

    window.addEventListener('storage', handleStorageChange);
    window.addEventListener('laundryku_auth_change', handleStorageChange);
    window.addEventListener('auth:user-updated', handleUserUpdate);

    return () => {
      window.removeEventListener('storage', handleStorageChange);
      window.removeEventListener('laundryku_auth_change', handleStorageChange);
      window.removeEventListener('auth:user-updated', handleUserUpdate);
    };
  }, [syncAuth]);

  const handleSetUserSession = useCallback((newToken: string | null | undefined, newUser: AuthUser) => {
    setAuthSession(null, newUser);
    setToken('session_active');
    setUser(newUser);
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new Event('laundryku_auth_change'));
    }
  }, []);

  const handleLogout = useCallback(async () => {
    try {
      await logout();
    } catch {
      clearAuthSession();
    }
    setToken(null);
    setUser(null);
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new Event('laundryku_auth_change'));
    }
  }, []);

  const handleRefreshProfile = useCallback(async () => {
    const freshUser = await fetchMe();
    if (freshUser) {
      setUser(freshUser);
    }
    return freshUser;
  }, []);

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        loading,
        isAuthenticated: Boolean(user),
        setUserSession: handleSetUserSession,
        logoutUser: handleLogout,
        refreshProfile: handleRefreshProfile,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
