import apiClient, { ApiSuccessResponse } from './api';

export interface AppNotification {
  id: string;
  channel: 'in_app' | 'whatsapp' | 'email' | string;
  event_type: string;
  title: string;
  message: string;
  payload: Record<string, any> | null;
  status: 'unread' | 'read' | 'sent' | 'failed' | string;
  sent_at: string | null;
  read_at: string | null;
  created_at: string;
}

export interface NotificationEventPreference {
  event_type: string;
  event_label: string;
  is_enabled: boolean;
}

export interface NotificationPreferenceGroup {
  channel: 'in_app' | 'whatsapp' | 'email' | string;
  channel_label: string;
  events: NotificationEventPreference[];
}

export const notificationApi = {
  // 1. Get user notifications
  async getNotifications(params?: { unread_only?: boolean; channel?: string; page?: number }) {
    const query = new URLSearchParams();
    if (params?.unread_only) query.append('unread_only', 'true');
    if (params?.channel && params.channel !== 'all') query.append('channel', params.channel);
    if (params?.page) query.append('page', params.page.toString());

    const qs = query.toString() ? `?${query.toString()}` : '';
    return apiClient<ApiSuccessResponse<{ unread_count: number; notifications: AppNotification[] }>>(`/notifications${qs}`);
  },

  // 2. Mark a notification as read
  async markAsRead(id: string) {
    return apiClient<ApiSuccessResponse<{ id: string; status: string }>>(`/notifications/${id}/read`, {
      method: 'PATCH',
    });
  },

  // 3. Mark all notifications as read
  async markAllAsRead() {
    return apiClient<ApiSuccessResponse<{ updated: boolean }>>('/notifications/read-all', {
      method: 'PATCH',
    });
  },

  // 4. Get notification preferences
  async getPreferences() {
    const res = await apiClient<ApiSuccessResponse<NotificationPreferenceGroup[]>>('/notifications/preferences');
    return res.data;
  },

  // 5. Update notification preferences
  async updatePreferences(preferences: Array<{ channel: string; event_type: string; is_enabled: boolean }>) {
    return apiClient<ApiSuccessResponse<{ updated: boolean }>>('/notifications/preferences', {
      method: 'PUT',
      body: JSON.stringify({ preferences }),
    });
  },
};
