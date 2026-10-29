import { NextRequest, NextResponse } from 'next/server';
import { payoutAccountSchema } from '@/lib/validations/payoutAccount';
import { LARAVEL_API_BASE_URL, PORTAL_COOKIE_NAMES } from '@/lib/bff';

export async function GET(request: NextRequest) {
  const token = request.cookies.get(PORTAL_COOKIE_NAMES.mitra)?.value;
  if (!token) {
    return NextResponse.json({ success: false, message: 'Unauthenticated.' }, { status: 401 });
  }

  try {
    const backendRes = await fetch(`${LARAVEL_API_BASE_URL}/store/payout-account`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      cache: 'no-store',
    });

    const data = await backendRes.json();
    return NextResponse.json(data, { status: backendRes.status });
  } catch (error: unknown) {
    const msg = error instanceof Error ? error.message : 'Gagal menghubungi server.';
    return NextResponse.json({ success: false, message: msg }, { status: 500 });
  }
}

export async function PUT(request: NextRequest) {
  const token = request.cookies.get(PORTAL_COOKIE_NAMES.mitra)?.value;
  if (!token) {
    return NextResponse.json({ success: false, message: 'Unauthenticated.' }, { status: 401 });
  }

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'Invalid JSON body' }, { status: 400 });
  }

  // Server-side Zod validation with identical schema
  const parsed = payoutAccountSchema.safeParse(body);
  if (!parsed.success) {
    const errorMsg = parsed.error.issues[0]?.message || 'Validasi rekening pencairan gagal.';
    return NextResponse.json(
      {
        success: false,
        message: errorMsg,
        errors: parsed.error.format(),
      },
      { status: 422 }
    );
  }

  try {
    const backendRes = await fetch(`${LARAVEL_API_BASE_URL}/store/payout-account`, {
      method: 'PUT',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(parsed.data),
    });

    const data = await backendRes.json();
    return NextResponse.json(data, { status: backendRes.status });
  } catch (error: unknown) {
    const msg = error instanceof Error ? error.message : 'Gagal menghubungi server.';
    return NextResponse.json({ success: false, message: msg }, { status: 500 });
  }
}
