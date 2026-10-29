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

const rawBaseUrl =
  process.env.LARAVEL_INTERNAL_API_URL ||
  process.env.NEXT_PUBLIC_API_URL ||
  'http://127.0.0.1:8000/api/v1';

// Server-side Node -> Laravel internal API base URL.
// Normalizes 'localhost' to '127.0.0.1' so Node.js (BFF) avoids IPv6 (::1) ECONNREFUSED issues on Windows.
export const LARAVEL_API_BASE_URL = rawBaseUrl.replace('://localhost', '://127.0.0.1');

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
  '/store-admin',
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
  '/geocode',
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
  if (normalized === '/admin' || normalized.startsWith('/admin/')) return 'admin';
  if (
    normalized === '/store' ||
    normalized.startsWith('/store/') ||
    normalized.startsWith('/store-') ||
    normalized === '/mitra' ||
    normalized.startsWith('/mitra/')
  ) {
    return 'mitra';
  }
  if (
    normalized === '/courier' ||
    normalized.startsWith('/courier/') ||
    normalized.startsWith('/courier-') ||
    normalized === '/kurir' ||
    normalized.startsWith('/kurir/')
  ) {
    return 'courier';
  }
  if (
    normalized === '/stores' ||
    normalized.startsWith('/stores/') ||
    normalized === '/customer' ||
    normalized.startsWith('/customer/')
  ) {
    return 'customer';
  }

  const referer = request.headers.get('referer');
  if (referer) {
    try {
      const pathname = new URL(referer).pathname;
      if (pathname === '/admin' || pathname.startsWith('/admin/')) return 'admin';
      if (
        pathname === '/store' ||
        pathname.startsWith('/store/') ||
        pathname === '/mitra' ||
        pathname.startsWith('/mitra/')
      ) {
        return 'mitra';
      }
      if (
        pathname === '/courier' ||
        pathname.startsWith('/courier/') ||
        pathname === '/kurir' ||
        pathname.startsWith('/kurir/')
      ) {
        return 'courier';
      }
      if (
        pathname === '/stores' ||
        pathname.startsWith('/stores/') ||
        pathname === '/orders' ||
        pathname.startsWith('/orders/') ||
        pathname === '/chat' ||
        pathname.startsWith('/chat/') ||
        pathname === '/profile' ||
        pathname.startsWith('/profile/') ||
        pathname === '/customer' ||
        pathname.startsWith('/customer/') ||
        pathname === '/'
      ) {
        return 'customer';
      }
    } catch {
      // ignore
    }
  }

  if (request.cookies.has(PORTAL_COOKIE_NAMES.customer) || request.cookies.has('auth_token')) return 'customer';
  if (request.cookies.has(PORTAL_COOKIE_NAMES.mitra)) return 'mitra';
  if (request.cookies.has(PORTAL_COOKIE_NAMES.courier)) return 'courier';
  if (request.cookies.has(PORTAL_COOKIE_NAMES.admin)) return 'admin';

  return 'customer';
}
