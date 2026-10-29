import { NextRequest, NextResponse } from 'next/server';
import {
  LARAVEL_API_BASE_URL,
  PORTAL_COOKIE_NAMES,
  detectPortalFromRequest,
  isPathAllowedInProxy,
  validateCsrfOrigin,
} from '@/lib/bff';

async function handleProxy(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  if (!validateCsrfOrigin(request)) {
    return NextResponse.json(
      { success: false, error: { code: 'CSRF_FAILED', message: 'Validasi asal request (CSRF) gagal.' } },
      { status: 403 }
    );
  }

  const resolvedParams = await params;
  const pathSegments = resolvedParams.path || [];
  const rawPath = pathSegments.join('/');
  const search = request.nextUrl.search || '';
  const targetPath = `/${rawPath}`;

  if (!isPathAllowedInProxy(targetPath)) {
    return NextResponse.json(
      { success: false, error: { code: 'ENDPOINT_NOT_ALLOWED', message: `Akses proxy ke ${targetPath} tidak diizinkan.` } },
      { status: 403 }
    );
  }

  const portal = detectPortalFromRequest(request, targetPath);
  const cookieName = PORTAL_COOKIE_NAMES[portal];
  let token = request.cookies.get(cookieName)?.value;
  if (!token && portal === 'courier') {
    token = request.cookies.get(PORTAL_COOKIE_NAMES.customer)?.value || request.cookies.get('auth_token')?.value;
  }
  if (!token && portal === 'customer') {
    token = request.cookies.get('auth_token')?.value;
  }

  const backendUrl = `${LARAVEL_API_BASE_URL}${targetPath}${search}`;

  const headers = new Headers();
  headers.set('Accept', 'application/json');

  const incomingContentType = request.headers.get('content-type');
  if (incomingContentType && !incomingContentType.includes('multipart/form-data')) {
    headers.set('Content-Type', incomingContentType);
  }

  const incomingAuth = request.headers.get('authorization');
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  } else if (incomingAuth) {
    headers.set('Authorization', incomingAuth);
  }

  let body: BodyInit | null = null;
  const method = request.method.toUpperCase();
  if (!['GET', 'HEAD'].includes(method)) {
    const contentType = request.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      body = await request.text();
    } else if (contentType.includes('multipart/form-data')) {
      body = await request.formData();
    } else {
      body = await request.blob();
    }
  }

  try {
    const backendRes = await fetch(backendUrl, {
      method,
      headers,
      body,
      cache: 'no-store',
    });

    const responseContentType = backendRes.headers.get('content-type') || '';
    if (responseContentType.includes('application/json')) {
      const data = await backendRes.json();
      return NextResponse.json(data, { status: backendRes.status });
    }

    const blob = await backendRes.blob();
    return new NextResponse(blob, {
      status: backendRes.status,
      headers: {
        'Content-Type': responseContentType || 'application/octet-stream',
      },
    });
  } catch (err: unknown) {
    const rawError = err instanceof Error ? err.message : String(err);
    const causeCode = (err as { cause?: { code?: string } })?.cause?.code;
    const causeMessage = (err as { cause?: { message?: string } })?.cause?.message;

    // Log technical error on server console without tokens or passwords
    console.error(
      `[BFF Proxy Error] ${method} ${targetPath}:`,
      rawError,
      causeCode || causeMessage || ''
    );

    const isNetworkError =
      rawError === 'fetch failed' ||
      rawError.includes('fetch failed') ||
      rawError.includes('ECONNREFUSED') ||
      rawError.includes('ETIMEDOUT') ||
      rawError.includes('ENOTFOUND') ||
      causeCode === 'ECONNREFUSED' ||
      causeCode === 'ETIMEDOUT' ||
      (typeof causeMessage === 'string' &&
        (causeMessage.includes('ECONNREFUSED') || causeMessage.includes('ETIMEDOUT')));

    const message = isNetworkError
      ? 'Server tidak dapat dihubungi. Coba lagi sebentar lagi.'
      : err instanceof Error
        ? err.message
        : 'Server tidak dapat dihubungi. Coba lagi sebentar lagi.';

    return NextResponse.json(
      { success: false, error: { code: 'BACKEND_COMMUNICATION_ERROR', message } },
      { status: 502 }
    );
  }
}

export const GET = handleProxy;
export const POST = handleProxy;
export const PUT = handleProxy;
export const PATCH = handleProxy;
export const DELETE = handleProxy;
