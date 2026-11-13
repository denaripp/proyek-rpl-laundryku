/**
 * Authentication client services for LaundryKu (FASE 1 - OTP Only)
 * Conforms to PRD 12.1 Standard API Response Structure
 */

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

export class AuthApiError extends Error {
  status: number;
  code?: string;
  fieldErrors?: Record<string, string[]>;

  constructor(message: string, status: number, code?: string, fieldErrors?: Record<string, string[]>) {
    super(message);
    this.name = 'AuthApiError';
    this.status = status;
    this.code = code;
    this.fieldErrors = fieldErrors;
  }
}

export interface OwnedStoreItem {
  id: string;
  name: string;
  approval_status: 'pending' | 'approved' | 'rejected' | string;
  operational_status: 'active' | 'inactive' | 'suspended' | string;
  rejection_reason?: string | null;
  approved_at?: string | null;
}

export interface AuthUser {
  id: string;
  name: string;
  email: string | null;
  display_email?: string | null;
  has_real_email?: boolean;
  phone: string | null;
  display_phone?: string | null;
  status: string;
  verified_at: string | null;
  avatar_url?: string | null;
  roles: string[] | { id: number; code: string; name: string }[];
  active_roles?: string[];
  stores?: OwnedStoreItem[];
}

export function isPlaceholderEmail(email?: string | null): boolean {
  if (!email) return false;
  const normalized = email.toLowerCase().trim();
  return /^wa_\d+@laundryku\.test$/i.test(normalized);
}

export function formatPhone(phone?: string | null): string {
  if (!phone) return '-';
  const cleaned = phone.replace(/[^0-9]/g, '');
  if (cleaned.startsWith('0')) {
    return '+62' + cleaned.substring(1);
  }
  if (cleaned.startsWith('8')) {
    return '+62' + cleaned;
  }
  if (cleaned.startsWith('62')) {
    return '+' + cleaned;
  }
  return '+' + cleaned;
}

export function getUserDisplayContact(user?: { email?: string | null; phone?: string | null; has_real_email?: boolean; display_email?: string | null; display_phone?: string | null } | null): string {
  if (!user) return '';
  if (user.has_real_email && user.email && !isPlaceholderEmail(user.email)) {
    return user.email;
  }
  if (user.display_email && !isPlaceholderEmail(user.display_email)) {
    return user.display_email;
  }
  if (user.display_phone) {
    return user.display_phone;
  }
  if (user.phone) {
    return formatPhone(user.phone);
  }
  if (user.email && !isPlaceholderEmail(user.email)) {
    return user.email;
  }
  return '-';
}


export interface RegisterResult {
  user_id: string;
  name: string;
  channel: 'email' | 'whatsapp';
  destination: string;
  status: string;
  otp_sent: boolean;
  delivery_status?: 'sent' | 'simulated' | 'failed';
  debug_otp?: string | null;
}

export interface LoginResult {
  user_id: string;
  channel: 'email' | 'whatsapp';
  destination: string;
  otp_sent: boolean;
  delivery_status?: 'sent' | 'simulated' | 'failed';
  debug_otp?: string | null;
}

export interface AuthSuccessResult {
  user: AuthUser;
  token: string;
  token_type: string;
}

export const LOGIN_PATH = '/auth';

/**
 * Validates and returns a safe internal redirect path.
 * Prevents open redirect attacks (e.g. //evil.com, https://evil.com, /\\evil.com).
 */
export function getSafeRedirectPath(target: string | null | undefined, defaultPath: string = '/'): string {
  if (!target || typeof target !== 'string') return defaultPath;
  const trimmed = target.trim();
  // Must start with '/' and not start with '//' or '/\' and not contain protocol '://' or pseudo-protocols
  if (
    trimmed.startsWith('/') &&
    !trimmed.startsWith('//') &&
    !trimmed.startsWith('/\\') &&
    !trimmed.includes('://') &&
    !/^\s*(javascript|data|vbscript):/i.test(trimmed)
  ) {
    return trimmed;
  }
  return defaultPath;
}

/**
 * Generates the login URL with optional internal redirect parameter.
 */
export function getLoginRedirectUrl(redirectPath?: string): string {
  if (!redirectPath) return LOGIN_PATH;
  const safe = getSafeRedirectPath(redirectPath, '');
  if (!safe || safe === '/') return LOGIN_PATH;
  return `${LOGIN_PATH}?redirect=${encodeURIComponent(safe)}`;
}

const USER_KEY = 'laundryku_user';
const MITRA_USER_KEY = 'laundryku_mitra_user';
const COURIER_USER_KEY = 'laundryku_courier_user';
const ADMIN_USER_KEY = 'laundryku_admin_user';

export function getStoredToken(): string | null {
  // Tokens are stored strictly in httpOnly cookies (laundryku_customer_token)
  // and are never accessible or stored in client-side JavaScript / localStorage.
  return null;
}

export type ActivePortalType = 'customer' | 'mitra' | 'courier' | 'admin';

export function detectCurrentPortal(): ActivePortalType {
  if (typeof window === 'undefined') return 'customer';
  const pathname = window.location?.pathname || '';
  if (pathname === '/admin' || pathname.startsWith('/admin/')) return 'admin';
  if (pathname === '/store' || pathname.startsWith('/store/') || pathname === '/mitra' || pathname.startsWith('/mitra/')) {
    return 'mitra';
  }
  if (pathname === '/courier' || pathname.startsWith('/courier/')) return 'courier';

  if (localStorage.getItem(USER_KEY)) return 'customer';
  if (localStorage.getItem(MITRA_USER_KEY)) return 'mitra';
  if (localStorage.getItem(COURIER_USER_KEY)) return 'courier';
  if (localStorage.getItem(ADMIN_USER_KEY)) return 'admin';
  return 'customer';
}

export function isAuthenticated(): boolean {
  if (typeof window === 'undefined') return false;
  return Boolean(
    localStorage.getItem(USER_KEY) ||
    localStorage.getItem(MITRA_USER_KEY) ||
    localStorage.getItem(COURIER_USER_KEY) ||
    localStorage.getItem(ADMIN_USER_KEY)
  );
}

export function getStoredUser(portal?: string): AuthUser | null {
  if (typeof window === 'undefined') return null;
  let key = USER_KEY;
  if (portal) {
    if (portal === 'mitra' || portal === 'store' || portal === 'store_admin') {
      key = MITRA_USER_KEY;
    } else if (portal === 'courier' || portal === 'kurir') {
      key = COURIER_USER_KEY;
    } else if (portal === 'admin' || portal === 'super_admin') {
      key = ADMIN_USER_KEY;
    }
  } else if (typeof window !== 'undefined' && window.location) {
    const pathname = window.location.pathname;
    if (pathname === '/store' || pathname.startsWith('/store/') || pathname === '/mitra' || pathname.startsWith('/mitra/')) {
      key = MITRA_USER_KEY;
    } else if (pathname === '/courier' || pathname.startsWith('/courier/')) {
      key = COURIER_USER_KEY;
    } else if (pathname === '/admin' || pathname.startsWith('/admin/')) {
      key = ADMIN_USER_KEY;
    }
  }

  let user = localStorage.getItem(key);
  if (!user && (key === COURIER_USER_KEY || key === MITRA_USER_KEY)) {
    user = localStorage.getItem(USER_KEY);
  }
  if (!user) return null;
  try {
    return JSON.parse(user);
  } catch {
    return null;
  }
}

export function setAuthSession(token: string | null | undefined, user: AuthUser): void {
  if (typeof window === 'undefined') return;
  // Tokens are stored in httpOnly cookies by the Next.js BFF.
  // We only store the non-sensitive user profile in localStorage for synchronous UI rendering.
  localStorage.setItem(USER_KEY, JSON.stringify(user));
  window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: user }));
}

export function updateStoredUser(updates: Partial<AuthUser>): AuthUser | null {
  if (typeof window === 'undefined') return null;
  const current = getStoredUser();
  if (!current) return null;
  const updated: AuthUser = { ...current, ...updates };
  localStorage.setItem(USER_KEY, JSON.stringify(updated));
  window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: updated }));
  return updated;
}

export function clearAuthSession(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(USER_KEY);
  localStorage.removeItem(MITRA_USER_KEY);
  localStorage.removeItem(COURIER_USER_KEY);
  localStorage.removeItem(ADMIN_USER_KEY);
  localStorage.removeItem('active_store_id');
  localStorage.removeItem('selected_store_id');
  localStorage.removeItem('active_store');
  localStorage.removeItem('lk_active_store');
  sessionStorage.removeItem('active_store_id');
  sessionStorage.removeItem('selected_store_id');
  sessionStorage.removeItem('active_store');
  window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: null }));
}

export async function register(data: {
  name: string;
  channel: 'email' | 'whatsapp';
  destination: string;
  password: string;
  password_confirmation: string;
}): Promise<RegisterResult> {
  let res: Response;
  try {
    res = await fetch('/api/auth/customer/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });
  } catch {
    throw new AuthApiError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 0, 'NETWORK_ERROR');
  }

  const json = await res.json().catch(() => null);
  if (!res.ok || !json || json.success === false) {
    const errorMsg =
      json?.message ||
      json?.error?.message ||
      (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
      (res.status === 429 ? 'Terlalu banyak percobaan. Silakan tunggu sebentar.' : 'Registrasi gagal.');
    throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
  }

  return json.data;
}

export async function login(data: {
  channel: 'email' | 'whatsapp';
  destination: string;
  password: string;
}): Promise<AuthSuccessResult> {
  let res: Response;
  try {
    res = await fetch('/api/auth/customer/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });
  } catch {
    throw new AuthApiError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 0, 'NETWORK_ERROR');
  }

  const json = await res.json().catch(() => null);
  if (!res.ok || !json || json.success === false) {
    const errorMsg =
      json?.message ||
      json?.error?.message ||
      (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
      (res.status === 401 ? 'Kontak atau password salah.' : (res.status === 429 ? 'Terlalu banyak percobaan. Silakan tunggu sebentar.' : 'Login gagal.'));
    throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
  }

  setAuthSession(null, json.data.user);
  return {
    user: json.data.user,
    token: '',
    token_type: 'Bearer',
  };
}

export async function verifyOtp(data: {
  user_id?: string;
  channel?: 'email' | 'whatsapp';
  destination?: string;
  otp: string;
}): Promise<AuthSuccessResult> {
  let res: Response;
  try {
    res = await fetch('/api/auth/customer/otp-verify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });
  } catch {
    throw new AuthApiError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 0, 'NETWORK_ERROR');
  }

  const json = await res.json().catch(() => null);
  if (!res.ok || !json || json.success === false) {
    const errorMsg =
      json?.message ||
      json?.error?.message ||
      (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
      'Verifikasi OTP gagal.';
    throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
  }

  setAuthSession(null, json.data.user);
  return {
    user: json.data.user,
    token: '',
    token_type: 'Bearer',
  };
}

export async function sendOtp(data: {
  user_id?: string;
  channel?: 'email' | 'whatsapp';
  destination?: string;
}): Promise<{ otp_sent: boolean; delivery_status?: string; debug_otp?: string }> {
  let res: Response;
  try {
    res = await fetch('/api/auth/customer/otp-send', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });
  } catch {
    throw new AuthApiError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 0, 'NETWORK_ERROR');
  }

  const json = await res.json().catch(() => null);
  if (!res.ok || !json || json.success === false) {
    const errorMsg =
      json?.message ||
      json?.error?.message ||
      (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
      'Gagal mengirim OTP.';
    throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
  }

  return json.data;
}

export async function requestPasswordSetup(data: {
  channel: 'email' | 'whatsapp';
  destination: string;
}): Promise<{ destination: string; channel: string; debug_otp?: string | null }> {
  let res: Response;
  try {
    res = await fetch(`${API_URL}/auth/password/request-setup`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });
  } catch {
    throw new AuthApiError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 0, 'NETWORK_ERROR');
  }

  const json = await res.json().catch(() => null);
  if (!res.ok || !json || json.success === false) {
    const errorMsg =
      json?.message ||
      json?.error?.message ||
      (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
      'Permintaan OTP atur kata sandi gagal.';
    throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
  }

  return json.data;
}

export async function setupPassword(data: {
  channel: 'email' | 'whatsapp';
  destination: string;
  otp: string;
  password: string;
  password_confirmation: string;
}): Promise<AuthSuccessResult> {
  let res: Response;
  try {
    res = await fetch(`${API_URL}/auth/password/setup`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    });
  } catch {
    throw new AuthApiError('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 0, 'NETWORK_ERROR');
  }

  const json = await res.json().catch(() => null);
  if (!res.ok || !json || json.success === false) {
    const errorMsg =
      json?.message ||
      json?.error?.message ||
      (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
      'Pengaturan kata sandi gagal.';
    throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
  }

  setAuthSession(json.data.token, json.data.user);
  return json.data;
}

export async function logout(): Promise<void> {
  try {
    await fetch('/api/auth/customer/logout', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
      },
    });
  } catch {
    // Ignore network errors on logout
  }
  clearAuthSession();
}

export async function logoutAndRedirectHome(
  portal?: 'customer' | 'mitra' | 'courier' | 'admin'
): Promise<void> {
  try {
    const targetPortal =
      portal ||
      (typeof window !== 'undefined' && window.location.pathname.startsWith('/admin')
        ? 'admin'
        : typeof window !== 'undefined' && window.location.pathname.startsWith('/courier')
        ? 'courier'
        : typeof window !== 'undefined' && (window.location.pathname.startsWith('/store') || window.location.pathname.startsWith('/mitra'))
        ? 'mitra'
        : 'customer');

    if (targetPortal === 'admin') {
      await fetch('/api/auth/admin/logout', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).catch(() => null);
    } else if (targetPortal === 'mitra') {
      await fetch('/api/auth/mitra/logout', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).catch(() => null);
    } else if (targetPortal === 'courier') {
      await fetch('/api/auth/courier/logout', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).catch(() => null);
    } else {
      await logout();
    }
  } catch {
    // Ignore network errors on logout
  } finally {
    clearAuthSession();
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new Event('laundryku_auth_change'));
      window.location.replace('/');
    }
  }
}

export async function fetchPortalSession(
  portal: 'customer' | 'mitra' | 'courier' | 'admin' = 'customer'
): Promise<AuthUser | null> {
  try {
    const res = await fetch(`/api/auth/${portal}/session`, {
      headers: {
        'Accept': 'application/json',
      },
      cache: 'no-store',
    });

    const key =
      portal === 'mitra'
        ? MITRA_USER_KEY
        : portal === 'courier'
        ? COURIER_USER_KEY
        : portal === 'admin'
        ? ADMIN_USER_KEY
        : USER_KEY;

    if (!res.ok) {
      if (typeof window !== 'undefined') {
        localStorage.removeItem(key);
        if (portal === 'customer') {
          window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: null }));
        }
      }
      return null;
    }

    const json = await res.json();
    const user = json.user || null;
    if (typeof window !== 'undefined' && user) {
      localStorage.setItem(key, JSON.stringify(user));
      if (portal === 'customer') {
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: user }));
      }
    }
    return user;
  } catch {
    return null;
  }
}

export async function fetchMe(): Promise<AuthUser | null> {
  return fetchPortalSession('customer');
}

export function hasRole(user: AuthUser | null, roleCode: string): boolean {
  if (!user) return false;

  const isRoleMatch = (code: string) => {
    if (code === roleCode) return true;
    if (roleCode === 'super_admin' && code === 'platform_owner') return true;
    if (roleCode === 'platform_owner' && code === 'super_admin') return true;
    return false;
  };

  const anyUser = user as unknown as { role?: string };
  if (anyUser.role && isRoleMatch(anyUser.role)) {
    return true;
  }

  if (!user.roles) return false;
  return user.roles.some((r) => {
    const code = typeof r === 'string' ? r : r.code;
    return isRoleMatch(code);
  });
}

export function getApprovedStores(user: AuthUser | null): OwnedStoreItem[] {
  if (!user || !user.stores || !Array.isArray(user.stores)) return [];
  return user.stores.filter((s) => s.approval_status === 'approved');
}

export function hasApprovedStore(user: AuthUser | null): boolean {
  return getApprovedStores(user).length > 0;
}

export function getActiveStoreId(user?: AuthUser | null): string | null {
  if (typeof window === 'undefined') return null;
  const stored = sessionStorage.getItem('laundryku_active_store_id');
  const u = user || getStoredUser();
  const approved = getApprovedStores(u);

  if (stored && approved.some((s) => s.id === stored)) {
    return stored;
  }

  if (approved.length > 0) {
    const defaultStore = approved[0];
    sessionStorage.setItem('laundryku_active_store_id', defaultStore.id);
    return defaultStore.id;
  }

  return null;
}

export function setActiveStoreId(storeId: string): void {
  if (typeof window === 'undefined') return;
  sessionStorage.setItem('laundryku_active_store_id', storeId);
  window.dispatchEvent(new CustomEvent('laundryku_active_store_change', { detail: storeId }));
}

/**
 * Resolves the primary home path based on user role and store ownership.
 * - super_admin -> /admin
 * - store_admin or owner with approved store -> /store
 * - courier -> /courier
 * - customer / other -> /stores (Marketplace)
 * - unauthenticated -> /
 */
export function getHomePathForRole(user: AuthUser | null): string {
  if (!user) return '/';
  if (hasRole(user, 'super_admin')) return '/admin';
  if (hasRole(user, 'store_admin') || hasApprovedStore(user)) return '/store';
  if (hasRole(user, 'courier')) return '/courier';
  return '/stores';
}

