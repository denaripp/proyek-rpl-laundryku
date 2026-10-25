import { NextRequest, NextResponse } from 'next/server';
import {
  LARAVEL_API_BASE_URL,
  PORTAL_COOKIE_NAMES,
  TEMP_2FA_COOKIE_NAMES,
  PortalType,
  getCookieOptions,
  validateCsrfOrigin,
} from '@/lib/bff';

interface RouteContext {
  params: Promise<{
    portal: string;
    action: string;
  }>;
}

export async function POST(request: NextRequest, { params }: RouteContext) {
  if (!validateCsrfOrigin(request)) {
    return NextResponse.json(
      { success: false, error: { code: 'CSRF_FAILED', message: 'Validasi asal request (CSRF) gagal.' } },
      { status: 403 }
    );
  }

  const { portal: rawPortal, action } = await params;
  const portal = rawPortal.toLowerCase() as PortalType;

  if (!['customer', 'mitra', 'courier', 'admin'].includes(portal)) {
    return NextResponse.json(
      { success: false, error: { code: 'INVALID_PORTAL', message: `Portal '${portal}' tidak valid.` } },
      { status: 400 }
    );
  }

  let bodyData: any = {};
  try {
    bodyData = await request.json();
  } catch {
    bodyData = {};
  }

  // -------------------------------------------------------------
  // CUSTOMER PORTAL
  // -------------------------------------------------------------
  if (portal === 'customer') {
    if (action === 'register') {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      return NextResponse.json(data, { status: res.status });
    }

    if (action === 'otp-send') {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/otp/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      return NextResponse.json(data, { status: res.status });
    }

    if (action === 'otp-verify') {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/otp/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        return NextResponse.json(data, { status: res.status });
      }

      const token = data.data?.token;
      const user = data.data?.user;

      const response = NextResponse.json({
        success: true,
        message: data.message || 'Verifikasi OTP berhasil.',
        data: { user },
      });

      if (token) {
        response.cookies.set(PORTAL_COOKIE_NAMES.customer, token, getCookieOptions());
      }
      return response;
    }

    if (action === 'login') {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        return NextResponse.json(data, { status: res.status });
      }

      const token = data.data?.token;
      const user = data.data?.user;

      const response = NextResponse.json({
        success: true,
        message: data.message || 'Login berhasil.',
        data: { user },
      });

      if (token) {
        response.cookies.set(PORTAL_COOKIE_NAMES.customer, token, getCookieOptions());
      }
      return response;
    }

    if (action === 'logout') {
      const token = request.cookies.get(PORTAL_COOKIE_NAMES.customer)?.value;
      if (token) {
        await fetch(`${LARAVEL_API_BASE_URL}/auth/logout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
        }).catch(() => null);
      }

      const response = NextResponse.json({ success: true, message: 'Berhasil logout.' });
      response.cookies.set(PORTAL_COOKIE_NAMES.customer, '', getCookieOptions(0));
      return response;
    }
  }

  // -------------------------------------------------------------
  // MITRA PORTAL
  // -------------------------------------------------------------
  if (portal === 'mitra') {
    if (action === 'register') {
      const customerToken =
        request.cookies.get(PORTAL_COOKIE_NAMES.customer)?.value ||
        request.cookies.get('auth_token')?.value;

      if (!customerToken) {
        return NextResponse.json(
          { success: false, message: 'Unauthenticated. Silakan login sebagai customer terlebih dahulu.' },
          { status: 401 }
        );
      }

      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/mitra/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${customerToken}`,
        },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        return NextResponse.json(data, { status: res.status });
      }

      const token = data.token;
      const response = NextResponse.json({
        success: true,
        message: data.message || 'Registrasi mitra berhasil.',
        data: {
          user: data.user,
          store: data.store,
        },
      });

      if (token) {
        response.cookies.set(PORTAL_COOKIE_NAMES.mitra, token, getCookieOptions());
      }
      // Hapus flag entri segera setelah pendaftaran sukses
      response.cookies.set('mitra_entry_flag', '', { path: '/', maxAge: 0 });
      return response;
    }

    if (action === 'login') {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/mitra/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        return NextResponse.json(data, { status: res.status });
      }

      // Check for 2FA
      if (data.requires_2fa) {
        const tempToken = data.temp_token;
        const response = NextResponse.json({
          success: true,
          requires_2fa: true,
          message: data.message || 'Kode autentikasi 2FA diperlukan.',
        });
        if (tempToken) {
          response.cookies.set(TEMP_2FA_COOKIE_NAMES.mitra, tempToken, getCookieOptions(300));
        }
        return response;
      }

      const token = data.token;
      const response = NextResponse.json({
        success: true,
        message: data.message || 'Login berhasil.',
        data: {
          user: data.user,
          store: data.store,
        },
      });

      if (token) {
        response.cookies.set(PORTAL_COOKIE_NAMES.mitra, token, getCookieOptions());
      }
      return response;
    }

    if (action === '2fa') {
      const tempToken = request.cookies.get(TEMP_2FA_COOKIE_NAMES.mitra)?.value;
      if (!tempToken) {
        return NextResponse.json(
          { success: false, message: 'Sesi 2FA kedaluwarsa. Silakan login kembali.' },
          { status: 401 }
        );
      }

      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/mitra/2fa`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${tempToken}`,
        },
        body: JSON.stringify({ code: bodyData.code }),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        return NextResponse.json(data, { status: res.status });
      }

      const fullToken = data.token;
      const response = NextResponse.json({
        success: true,
        message: data.message || 'Verifikasi 2FA berhasil.',
        data: { user: data.user },
      });

      response.cookies.set(TEMP_2FA_COOKIE_NAMES.mitra, '', getCookieOptions(0));
      if (fullToken) {
        response.cookies.set(PORTAL_COOKIE_NAMES.mitra, fullToken, getCookieOptions());
      }
      return response;
    }

    if (action === 'logout') {
      const token = request.cookies.get(PORTAL_COOKIE_NAMES.mitra)?.value;
      if (token) {
        await fetch(`${LARAVEL_API_BASE_URL}/auth/mitra/logout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
        }).catch(() => null);
      }

      const response = NextResponse.json({ success: true, message: 'Berhasil logout.' });
      response.cookies.set(PORTAL_COOKIE_NAMES.mitra, '', getCookieOptions(0));
      response.cookies.set(TEMP_2FA_COOKIE_NAMES.mitra, '', getCookieOptions(0));
      return response;
    }
  }

  // -------------------------------------------------------------
  // COURIER PORTAL
  // -------------------------------------------------------------
  if (portal === 'courier') {
    if (action === 'login') {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/kurir/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(bodyData),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        return NextResponse.json(data, { status: res.status });
      }

      const token = data.token;
      const response = NextResponse.json({
        success: true,
        message: data.message || 'Login kurir berhasil.',
        data: { user: data.user },
      });

      if (token) {
        response.cookies.set(PORTAL_COOKIE_NAMES.courier, token, getCookieOptions());
      }
      return response;
    }

    if (action === 'logout') {
      const token = request.cookies.get(PORTAL_COOKIE_NAMES.courier)?.value;
      if (token) {
        await fetch(`${LARAVEL_API_BASE_URL}/auth/kurir/logout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
        }).catch(() => null);
      }

      const response = NextResponse.json({ success: true, message: 'Berhasil logout.' });
      response.cookies.set(PORTAL_COOKIE_NAMES.courier, '', getCookieOptions(0));
      return response;
    }
  }

  // -------------------------------------------------------------
  // SUPER ADMIN PORTAL
  // -------------------------------------------------------------
  if (portal === 'admin') {
    if (action === 'login') {
      // Map username to email if provided
      const email = bodyData.email || bodyData.username;
      const password = bodyData.password;

      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/admin/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        return NextResponse.json(data, { status: res.status });
      }

      if (data.requires_2fa) {
        const tempToken = data.temp_token;
        const response = NextResponse.json({
          success: true,
          requires_2fa: true,
          message: data.message || 'Kredensial valid. Silakan masukkan kode 2FA.',
        });
        if (tempToken) {
          response.cookies.set(TEMP_2FA_COOKIE_NAMES.admin, tempToken, getCookieOptions(300));
        }
        return response;
      }

      const token = data.token;
      const response = NextResponse.json({
        success: true,
        message: data.message || 'Login admin berhasil.',
        data: { user: data.user },
      });

      if (token) {
        response.cookies.set(PORTAL_COOKIE_NAMES.admin, token, getCookieOptions());
      }
      return response;
    }

    if (action === '2fa') {
      const tempToken = request.cookies.get(TEMP_2FA_COOKIE_NAMES.admin)?.value;
      if (!tempToken) {
        return NextResponse.json(
          { success: false, message: 'Sesi 2FA kedaluwarsa. Silakan login kembali.' },
          { status: 401 }
        );
      }

      const res = await fetch(`${LARAVEL_API_BASE_URL}/auth/admin/2fa`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${tempToken}`,
        },
        body: JSON.stringify({ code: bodyData.code }),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        return NextResponse.json(data, { status: res.status });
      }

      const fullToken = data.token;
      const response = NextResponse.json({
        success: true,
        message: data.message || 'Verifikasi 2FA berhasil.',
        data: { user: data.user },
      });

      response.cookies.set(TEMP_2FA_COOKIE_NAMES.admin, '', getCookieOptions(0));
      if (fullToken) {
        response.cookies.set(PORTAL_COOKIE_NAMES.admin, fullToken, getCookieOptions());
      }
      return response;
    }

    if (action === 'logout') {
      const token = request.cookies.get(PORTAL_COOKIE_NAMES.admin)?.value;
      if (token) {
        await fetch(`${LARAVEL_API_BASE_URL}/auth/admin/logout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
        }).catch(() => null);
      }

      const response = NextResponse.json({ success: true, message: 'Berhasil logout.' });
      response.cookies.set(PORTAL_COOKIE_NAMES.admin, '', getCookieOptions(0));
      response.cookies.set(TEMP_2FA_COOKIE_NAMES.admin, '', getCookieOptions(0));
      return response;
    }
  }

  return NextResponse.json(
    { success: false, error: { code: 'ACTION_NOT_FOUND', message: `Aksi '${action}' tidak dikenal untuk portal '${portal}'.` } },
    { status: 404 }
  );
}

export async function GET(request: NextRequest, { params }: RouteContext) {
  const { portal: rawPortal, action } = await params;
  const portal = rawPortal.toLowerCase() as PortalType;

  if (action === 'session') {
    const cookieName = PORTAL_COOKIE_NAMES[portal];
    const token = request.cookies.get(cookieName)?.value;

    if (!token) {
      return NextResponse.json({ success: false, user: null }, { status: 401 });
    }

    try {
      const res = await fetch(`${LARAVEL_API_BASE_URL}/me`, {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        cache: 'no-store',
      });

      if (!res.ok) {
        // Token invalid or revoked in database
        const response = NextResponse.json({ success: false, user: null }, { status: 401 });
        response.cookies.set(cookieName, '', getCookieOptions(0));
        return response;
      }

      const data = await res.json();
      const resolvedUser = data.data?.user || (data.data?.id ? data.data : data.user) || null;
      return NextResponse.json({
        success: true,
        user: resolvedUser,
      });
    } catch {
      return NextResponse.json({ success: false, user: null }, { status: 502 });
    }
  }

  return NextResponse.json({ success: false, message: 'Method not allowed' }, { status: 405 });
}
