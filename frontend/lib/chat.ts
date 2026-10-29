import apiClient, { ApiSuccessResponse } from './api';

export interface ChatMessageItem {
  id: string;
  conversation_id: string;
  sender_id: string;
  sender_name?: string;
  message_type: 'text' | 'image';
  body: string | null;
  attachment_key: string | null;
  attachment_url: string | null;
  read_at: string | null;
  is_read: boolean;
  created_at: string;
}

export interface ConversationItem {
  id: string;
  order_id: string | null;
  order_number?: string | null;
  customer_id: string;
  customer_name?: string;
  store_id: string;
  store_name?: string;
  other_party_name: string;
  other_party_role: 'store_admin' | 'customer';
  status: string;
  unread_count: number;
  last_message: ChatMessageItem | null;
  last_message_at: string;
  created_at: string;
}

export interface ConversationDetailResponse {
  conversation: ConversationItem;
  messages: ChatMessageItem[];
}

export async function getConversations(context?: 'store' | 'customer'): Promise<ConversationItem[]> {
  const url = `/conversations${context ? `?context=${context}` : ''}`;
  const response = await apiClient<ApiSuccessResponse<ConversationItem[]>>(url);
  return response.data;
}

export async function startOrGetConversation(
  storeIdOrParams: string | { store_id: string; order_id?: string },
  orderIdParam?: string
): Promise<ConversationItem> {
  let storeId: string;
  let orderId: string | undefined;

  if (typeof storeIdOrParams === 'object') {
    storeId = storeIdOrParams.store_id;
    orderId = storeIdOrParams.order_id;
  } else {
    storeId = storeIdOrParams;
    orderId = orderIdParam;
  }

  const response = await apiClient<ApiSuccessResponse<ConversationItem>>('/conversations', {
    method: 'POST',
    headers: {
      'x-portal': 'customer',
    },
    body: JSON.stringify({
      store_id: storeId,
      order_id: orderId || undefined,
    }),
  });
  return response.data;
}

export async function getConversationDetail(id: string): Promise<ConversationDetailResponse> {
  const response = await apiClient<ApiSuccessResponse<ConversationDetailResponse>>(`/conversations/${id}`);
  return response.data;
}

export async function sendChatMessage(
  conversationId: string,
  payload: { body?: string; image?: File | null }
): Promise<ChatMessageItem> {
  const formData = new FormData();
  if (payload.body) formData.append('body', payload.body);
  if (payload.image) formData.append('image', payload.image);

  const response = await apiClient<ApiSuccessResponse<ChatMessageItem>>(
    `/conversations/${conversationId}/messages`,
    {
      method: 'POST',
      body: formData,
    }
  );
  return response.data;
}

export async function pollChatMessages(
  conversationId: string,
  since?: string
): Promise<ChatMessageItem[]> {
  const url = `/conversations/${conversationId}/messages${since ? `?since=${encodeURIComponent(since)}` : ''}`;
  const response = await apiClient<ApiSuccessResponse<ChatMessageItem[]>>(url);
  return response.data;
}

export async function getUnreadChatCount(): Promise<number> {
  try {
    const response = await apiClient<ApiSuccessResponse<{ unread_count: number }>>('/conversations/unread-count');
    return response.data?.unread_count ?? 0;
  } catch {
    return 0;
  }
}

export async function markConversationAsRead(id: string): Promise<void> {
  try {
    await apiClient<ApiSuccessResponse<{ conversation_id: string; marked_read_count: number }>>(`/conversations/${id}/read`, {
      method: 'POST',
    });
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new Event('laundryku_chat_read'));
    }
  } catch {
    // Ignore error
  }
}
