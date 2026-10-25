import { NextRequest, NextResponse } from 'next/server';

export type PortalType = 'customer' | 'mitra' | 'courier' | 'admin';

export const PORTAL_COOKIE_NAMES: Record<PortalType, string> = {
  customer: 'laundryku_customer_token',
  mitra: 'laundryku_mitra_token',
  courier: 'laundryku_courier_token',
  admin: 'laundryku_admin_token',
};

export const TEMP_2FA_COOKIE_NAMES: Record<'mitra' | 'admin', string> = {
  mitra: 'laundryku_mitra_temp_token',
  admin: 'laundryku_admin_temp_token',
};

export const LARAVEL_API_BASE_URL =
  process.env.LARAVEL_INTERNAL_API_URL ||
  process.env.NEXT_PUBLIC_API_URL ||
  'http://127.0.0.1:8000/api/v1';

export function getCookieOptions(maxAge: number = 2592000) {
  return {
    httpOnly: true,
    sameSite: 'lax' as const,
    path: '/',
    secure: process.env.NODE_ENV === 'production',
    maxAge,
  };
}

/**
 * Validate request Origin / Referer to prevent CSRF attacks on mutation endpoints.
 */
export function validateCsrfOrigin(request: NextRequest): boolean {
  const method = request.method.toUpperCase();
  if (['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    return true;
  }

  const origin = request.headers.get('origin');
  const referer = request.headers.get('referer');
  const host = request.headers.get('host');

  if (!host) return false;

  if (origin) {
    try {
      const originUrl = new URL(origin);
      if (originUrl.host !== host) {
        return false;
      }
      return true;
    } catch {
      return false;
    }
  }

  if (referer) {
    try {
      const refererUrl = new URL(referer);
      if (refererUrl.host !== host) {
        return false;
      }
      return true;
    } catch {
      return false;
    }
  }

  // Same-origin browser fetch without Origin/Referer in local dev can be allowed
  return true;
}

export const ALLOWED_PROXY_PREFIXES = [
  '/orders',
  '/customer',
  '/stores',
  '/store',
  '/store-owner',
  '/mitra',
  '/courier',
  '/courier-tasks',
  '/kurir',
  '/admin',
  '/profile',
  '/addresses',
  '/payments',
  '/refunds',
  '/disputes',
  '/settlements',
  '/pricing',
  '/service-categories',
  '/notifications',
  '/reviews',
  '/me',
  '/health',
  '/upgrades',
  '/conversations',
  '/chatbot',
];

export function isPathAllowedInProxy(path: string): boolean {
  const normalized = path.startsWith('/') ? path : `/${path}`;
  return ALLOWED_PROXY_PREFIXES.some(
    (prefix) => normalized === prefix || normalized.startsWith(`${prefix}/`) || normalized.startsWith(`${prefix}?`)
  );
}

export function detectPortalFromRequest(request: NextRequest, targetPath: string): PortalType {
  const headerPortal = request.headers.get('x-portal')?.toLowerCase();
  if (headerPortal === 'admin') return 'admin';
  if (headerPortal === 'mitra' || headerPortal === 'store') return 'mitra';
  if (headerPortal === 'courier' || headerPortal === 'kurir') return 'courier';
  if (headerPortal === 'customer') return 'customer';

  const normalized = targetPath.startsWith('/') ? targetPath : `/${targetPath}`;
  if (normalized.startsWith('/admin')) return 'admin';
  if (normalized.startsWith('/store') || normalized.startsWith('/mitra')) return 'mitra';
  if (normalized.startsWith('/courier') || normalized.startsWith('/kurir')) return 'courier';

  if (request.cookies.has(PORTAL_COOKIE_NAMES.customer)) return 'customer';
  if (request.cookies.has(PORTAL_COOKIE_NAMES.mitra)) return 'mitra';
  if (request.cookies.has(PORTAL_COOKIE_NAMES.courier)) return 'courier';
  if (request.cookies.has(PORTAL_COOKIE_NAMES.admin)) return 'admin';

  return 'customer';
}
