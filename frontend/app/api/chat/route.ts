import { NextRequest, NextResponse } from 'next/server';
import {
  LARAVEL_API_BASE_URL,
  PORTAL_COOKIE_NAMES,
  detectPortalFromRequest,
  validateCsrfOrigin,
} from '@/lib/bff';

export const SYSTEM_PROMPT = `Kamu adalah AI Assistant resmi untuk marketplace laundry LaundryKu. Jawab langsung pertanyaan pengguna secara ringkas, jelas, dan santun.`;

export function normalizeUserQuery(text: string): string {
  return text.toLowerCase().trim().replace(/[.,\/#!$%\^&\*;:{}=\-_`~()?"'’]/g, ' ').replace(/\s+/g, ' ');
}

/**
 * BFF /api/chat: Meneruskan seluruh kueri chatbot langsung ke backend Laravel.
 * - Pengguna terotentikasi: diteruskan ke /chatbot/sessions/{id}/messages dengan header Authorization.
 * - Pengguna tamu (guest): diteruskan ke /chatbot/guest/ask.
 * Tanpa template penolakan di lapisan BFF.
 */
export async function POST(req: NextRequest) {
  if (!validateCsrfOrigin(req)) {
    return NextResponse.json(
      { success: false, error: { code: 'CSRF_FAILED', message: 'Validasi asal request (CSRF) gagal.' } },
      { status: 403 }
    );
  }

  try {
    let body: any = {};
    try {
      body = await req.json();
    } catch {
      try {
        const text = await req.text();
        body = JSON.parse(text);
      } catch {
        body = {};
      }
    }

    const userMessage =
      typeof body.message === 'string'
        ? body.message
        : Array.isArray(body.messages) && body.messages.length > 0
        ? body.messages[body.messages.length - 1]?.content || ''
        : '';

    if (!userMessage.trim()) {
      return NextResponse.json(
        { success: false, error: { message: 'Pesan tidak boleh kosong.' } },
        { status: 400 }
      );
    }

    // Ekstrak token otentikasi dari Authorization header atau Cookie portal
    let token: string | undefined;
    const authHeader = req.headers.get('authorization');
    if (authHeader && authHeader.toLowerCase().startsWith('bearer ')) {
      token = authHeader.substring(7).trim();
    } else {
      const portal = detectPortalFromRequest(req, '/chatbot');
      const cookieName = PORTAL_COOKIE_NAMES[portal];
      token = req.cookies.get(cookieName)?.value;
    }

    // 1. Jalur Terotentikasi (Header Authorization / Token Terkirim ke Laravel)
    if (token) {
      let sessionId = body.session_id;

      // Buat sesi jika belum ada
      if (!sessionId) {
        try {
          const sessionRes = await fetch(`${LARAVEL_API_BASE_URL}/chatbot/sessions`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({ title: 'Percakapan Asisten AI' }),
          });

          if (sessionRes.ok) {
            const sessionJson = await sessionRes.json();
            sessionId = sessionJson?.data?.id;
          } else if (sessionRes.status === 401) {
            return NextResponse.json(
              { success: false, error: { code: 'UNAUTHENTICATED', message: 'Sesi login telah kedaluwarsa.' } },
              { status: 401 }
            );
          }
        } catch (sessionErr: any) {
          console.error('[BFF /api/chat] Gagal membuat session di Laravel:', sessionErr.message);
        }
      }

      if (sessionId) {
        const messageRes = await fetch(`${LARAVEL_API_BASE_URL}/chatbot/sessions/${sessionId}/messages`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
          body: JSON.stringify({ message: userMessage }),
        });

        const messageData = await messageRes.json();
        if (messageRes.ok && messageData.success) {
          const assistantContent = messageData.data?.assistant_message?.content || '';
          return NextResponse.json({
            success: true,
            data: {
              question: userMessage,
              answer: assistantContent,
              is_fallback: messageData.data?.assistant_message?.metadata?.is_fallback || false,
              provider: messageData.data?.assistant_message?.metadata?.provider || 'laravel_backend',
              session_id: sessionId,
              user_message: messageData.data?.user_message,
              assistant_message: messageData.data?.assistant_message,
            },
          });
        }

        return NextResponse.json(messageData, { status: messageRes.status });
      }
    }

    // 2. Jalur Tamu (Guest Ask ke Laravel)
    const guestRes = await fetch(`${LARAVEL_API_BASE_URL}/chatbot/guest/ask`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ message: userMessage }),
    });

    const guestData = await guestRes.json();
    return NextResponse.json(guestData, { status: guestRes.status });
  } catch (err: any) {
    console.error('[BFF /api/chat Error]:', err.message);
    return NextResponse.json(
      {
        success: false,
        error: { message: 'Gagal meneruskan pesan ke backend: ' + err.message },
      },
      { status: 500 }
    );
  }
}
