/**
 * Base API client configuration and utility functions for LaundryKu
 * Conforms to PRD 12.1 Standard API Response Structure
 */

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

export interface ApiSuccessResponse<T = unknown> {
  success: true;
  data: T;
  meta?: {
    total?: number;
    last_page?: number;
    current_page?: number;
    per_page?: number;
    message?: string;
    [key: string]: any;
  };
}

export interface ApiErrorDetail {
  code: string;
  message: string;
  details?: Record<string, unknown> | null;
}

export interface ApiErrorResponse {
  success: false;
  error: ApiErrorDetail;
}

export type ApiResponse<T = unknown> = ApiSuccessResponse<T> | ApiErrorResponse;

export interface HealthCheckResponse {
  status: string;
  service: string;
  version?: string;
  database: string;
  timestamp: string;
  database_driver?: string;
}

export async function checkBackendHealth(): Promise<HealthCheckResponse> {
  const response = await fetch(`${API_BASE_URL}/health`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Health check failed with status: ${response.status}`);
  }

  const json = await response.json();
  return json.data || json;
}

/**
 * Helper to ensure raw network/fetch error strings are converted into a friendly user message.
 */
export function formatFriendlyErrorMessage(
  err: unknown,
  fallbackMessage: string = 'Terjadi kesalahan sistem.'
): string {
  if (!err) return fallbackMessage;

  const msg =
    (err as { responseData?: { error?: { message?: string } } })?.responseData?.error?.message ||
    (err as { response?: { data?: { error?: { message?: string } } } })?.response?.data?.error?.message ||
    (err instanceof Error ? err.message : typeof err === 'string' ? err : '');

  if (
    !msg ||
    msg === 'fetch failed' ||
    msg.includes('fetch failed') ||
    msg.includes('Failed to fetch') ||
    msg.includes('NetworkError') ||
    msg.includes('ECONNREFUSED') ||
    msg.includes('ETIMEDOUT') ||
    msg.includes('ENOTFOUND')
  ) {
    return 'Server tidak dapat dihubungi. Coba lagi sebentar lagi.';
  }

  return msg;
}

export async function apiClient<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers || {});
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }
  if (!headers.has('Content-Type') && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  // When running in the browser, route through the Next.js BFF proxy which attaches
  // the appropriate httpOnly cookie as a Bearer token to Laravel.
  const isBrowser = typeof window !== 'undefined';
  const url = isBrowser
    ? `/api/proxy${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`
    : `${API_BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

  let response: Response;
  try {
    response = await fetch(url, {
      ...options,
      headers,
      credentials: 'include',
    });
  } catch (err: unknown) {
    throw new Error(formatFriendlyErrorMessage(err, 'Server tidak dapat dihubungi. Coba lagi sebentar lagi.'));
  }

  let data: ApiResponse<T>;
  try {
    data = await response.json();
  } catch {
    throw new Error(`Gagal memproses respons dari server (${response.status})`);
  }

  if (!response.ok || (data as ApiErrorResponse).success === false) {
    const errObj = (data as ApiErrorResponse).error;
    const rawData = data as unknown as { message?: string; error?: { message?: string } };
    const rawErrorMsg = errObj?.message || rawData?.message || 'Terjadi kesalahan pada sistem.';
    const errorMsg = formatFriendlyErrorMessage(rawErrorMsg, 'Terjadi kesalahan pada sistem.');
    const error = new Error(errorMsg);
    (error as unknown as { responseData: unknown; status: number; code?: string }).responseData = data;
    (error as unknown as { status: number }).status = response.status;
    (error as unknown as { code?: string }).code = errObj?.code;
    throw error;
  }

  return data as T;
}

export default apiClient;
