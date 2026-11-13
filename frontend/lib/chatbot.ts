import apiClient, { ApiSuccessResponse } from './api';
import { detectCurrentPortal } from './auth';

function chatbotClient<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers || {});
  headers.set('x-portal', detectCurrentPortal());
  return apiClient<T>(endpoint, { ...options, headers });
}

export interface ChatbotMessageItem {
  id: string;
  session_id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  token_count?: number | null;
  metadata?: {
    is_fallback?: boolean;
    provider?: string;
  } | null;
  created_at: string;
}

export interface ChatbotSessionItem {
  id: string;
  user_id: string | null;
  title: string | null;
  context_type: string | null;
  context_id: string | null;
  provider: string;
  messages_count?: number;
  latest_message?: ChatbotMessageItem | null;
  messages?: ChatbotMessageItem[];
  created_at: string;
  updated_at: string;
}

export interface FaqSuggestionItem {
  id: string;
  question: string;
  category: string;
  icon?: string;
}

export interface GuestAskResponse {
  question: string;
  answer: string;
  is_fallback: boolean;
  provider: string;
}

export interface SendMessageResponse {
  user_message: ChatbotMessageItem;
  assistant_message: ChatbotMessageItem;
}

/**
 * Get FAQ suggestions and starter prompt pills (Public)
 */
export async function getFaqSuggestions(): Promise<FaqSuggestionItem[]> {
  const response = await apiClient<ApiSuccessResponse<FaqSuggestionItem[]>>('/chatbot/faq');
  return response.data;
}

/**
 * List authenticated user's chatbot sessions
 */
export async function getChatbotSessions(): Promise<ChatbotSessionItem[]> {
  const response = await chatbotClient<ApiSuccessResponse<ChatbotSessionItem[]>>('/chatbot/sessions');
  return response.data;
}

/**
 * Create a new chatbot session, optionally with an initial message
 */
export async function createChatbotSession(payload?: {
  message?: string;
  title?: string;
  context_type?: string;
  context_id?: string;
}): Promise<ChatbotSessionItem> {
  const response = await chatbotClient<ApiSuccessResponse<ChatbotSessionItem>>('/chatbot/sessions', {
    method: 'POST',
    body: JSON.stringify(payload || {}),
  });
  return response.data;
}

/**
 * Get chatbot session detail and message history
 */
export async function getChatbotSessionDetail(id: string): Promise<ChatbotSessionItem> {
  const response = await chatbotClient<ApiSuccessResponse<ChatbotSessionItem>>(`/chatbot/sessions/${id}`);
  return response.data;
}

/**
 * Send a message within a chatbot session and receive AI response
 */
export async function sendChatbotMessage(
  sessionId: string,
  message: string
): Promise<SendMessageResponse> {
  const response = await chatbotClient<ApiSuccessResponse<SendMessageResponse>>(
    `/chatbot/sessions/${sessionId}/messages`,
    {
      method: 'POST',
      body: JSON.stringify({ message }),
    }
  );
  return response.data;
}

/**
 * Delete a chatbot session
 */
export async function deleteChatbotSession(id: string): Promise<void> {
  await chatbotClient<ApiSuccessResponse<{ message: string }>>(`/chatbot/sessions/${id}`, {
    method: 'DELETE',
  });
}

/**
 * Clear all chatbot sessions owned by the current authenticated user
 */
export async function clearAllChatbotSessions(): Promise<{ deleted_count: number }> {
  const response = await chatbotClient<ApiSuccessResponse<{ deleted_count: number }>>('/chatbot/sessions', {
    method: 'DELETE',
  });
  return response.data;
}

/**
 * Ask a question as a guest without creating a persistent session.
 * Connects to the server-side /api/chat route (or backend), passing full multi-turn history.
 */
export async function guestAskChatbot(
  message: string,
  history: { role: 'user' | 'assistant' | 'system'; content: string }[] = []
): Promise<GuestAskResponse> {
  const fullMessages = [
    ...history.filter((m) => m.role === 'user' || m.role === 'assistant'),
    { role: 'user' as const, content: message },
  ];

  try {
    const localRes = await fetch('/api/chat', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ message, messages: fullMessages }),
    });

    if (localRes.ok) {
      const json = await localRes.json();
      if (json.success && json.data) {
        return json.data;
      }
    } else {
      console.error('[guestAskChatbot] /api/chat returned status:', localRes.status);
    }
  } catch (err: any) {
    console.error('[guestAskChatbot] Failed to reach local /api/chat route, trying backend:', err.message);
  }

  const response = await apiClient<ApiSuccessResponse<GuestAskResponse>>('/chatbot/guest/ask', {
    method: 'POST',
    body: JSON.stringify({ message, messages: fullMessages }),
  });
  return response.data;
}

