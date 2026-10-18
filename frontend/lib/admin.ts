import apiClient, { ApiSuccessResponse } from './api';
import { setAuthSession, clearAuthSession, AuthUser, AuthApiError } from './auth';

export const ADMIN_LOGIN_PATH = '/admin/login';

export interface DashboardMetrics {
  period: string;
  metrics: {
    total_gmv: number;
    total_platform_revenue: number;
    total_orders: number;
    completed_orders: number;
    active_stores: number;
    pending_stores: number;
    total_stores: number;
    total_users: number;
    active_users: number;
    suspended_users: number;
  };
  orders_by_status: Record<string, number>;
  users_by_role: {
    customer: number;
    store_admin: number;
    courier: number;
  };
  recent_logs: Array<{
    id: string;
    action: string;
    entity_type: string;
    entity_id: string | null;
    actor_name: string;
    created_at: string;
  }>;
}

export interface AdminStore {
  id: string;
  name: string;
  description: string | null;
  address_text: string;
  latitude: number | null;
  longitude: number | null;
  service_radius_km: number;
  opening_hours: Record<string, any> | null;
  approval_status: 'pending' | 'approved' | 'rejected';
  operational_status: 'active' | 'inactive';
  rejection_reason: string | null;
  approved_at: string | null;
  owner: {
    id: string;
    name: string;
    email: string;
    phone: string;
  } | null;
  documents?: Array<{
    id: string;
    document_type: string;
    file_url: string;
    status: string;
    created_at: string;
  }>;
  services?: Array<{
    id: string;
    name: string;
    category?: string;
    pricing_unit: string;
    price_per_unit: number;
    is_active: boolean;
  }>;
  created_at: string;
}

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  phone: string;
  username?: string | null;
  status: 'active' | 'suspended' | 'banned';
  status_reason: string | null;
  roles: string[];
  verified_at: string | null;
  created_at: string;
}

export interface ServiceCategory {
  id: string;
  name: string;
  slug: string;
  status: 'active' | 'inactive';
  services_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface PlatformCommission {
  key: string;
  amount: number;
  description: string;
  updated_at?: string;
}

export interface AuditLogItem {
  id: string;
  actor: {
    id: string;
    name: string;
    email: string;
  } | null;
  action: string;
  entity_type: string;
  entity_id: string | null;
  metadata: Record<string, any> | null;
  ip_hash: string | null;
  created_at: string;
}

export interface AdminOrder {
  id: string;
  order_number: string;
  customer: {
    id: string;
    name: string;
    email: string;
    phone: string;
  } | null;
  store: {
    id: string;
    name: string;
  } | null;
  courier: {
    id: string;
    name: string;
    phone: string;
  } | null;
  status: string;
  payment_status: string;
  estimated_amount: number;
  final_amount: number | null;
  commission_amount: number;
  delivery_fee: number;
  created_at: string;
  updated_at: string;
  items?: Array<{
    id: string;
    service_name_snapshot: string;
    quantity_estimated: number;
    quantity_actual: number | null;
    unit_price_snapshot: number;
    line_final_amount: number | null;
  }>;
  payments?: Array<{
    id: string;
    payment_number: string;
    method: string;
    amount: number;
    status: string;
    created_at: string;
  }>;
}

export interface AdminPayment {
  id: string;
  payment_number: string;
  order_id: string;
  order_number: string | null;
  customer_name: string | null;
  store_name: string | null;
  provider: string;
  method: string;
  amount: number;
  status: string;
  provider_reference: string | null;
  paid_at: string | null;
  created_at: string;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

export const adminApi = {
  // Admin Auth
  async login(credentials: { username: string; password: string }): Promise<{ user: AuthUser; token: string }> {
    let res: Response;
    try {
      res = await fetch(`${API_URL}/admin/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(credentials),
      });
    } catch {
      throw new AuthApiError('Tidak dapat terhubung ke server backend.', 0, 'NETWORK_ERROR');
    }

    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.success === false) {
      const errorMsg =
        json?.message ||
        json?.error?.message ||
        (json?.errors ? Object.values(json.errors).flat().join(', ') : null) ||
        (res.status === 401 ? 'Username atau password salah.' :
         res.status === 403 ? 'Akses ditolak. Akun bukan Super Administrator.' :
         res.status === 429 ? 'Terlalu banyak percobaan login. Silakan tunggu 1 menit.' :
         'Login admin gagal.');
      throw new AuthApiError(errorMsg, res.status, json?.error?.code || json?.code, json?.errors || json?.error?.details);
    }

    setAuthSession(json.data.token, json.data.user);
    return json.data;
  },

  async logout(): Promise<void> {
    try {
      await fetch('/api/auth/admin/logout', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
        },
      });
    } catch {
      // ignore network error
    }
    clearAuthSession();
  },

  // 1. Dashboard Metrics
  async getMetrics(period: string = 'all_time', startDate?: string, endDate?: string) {
    const params = new URLSearchParams({ period });
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    const res = await apiClient<ApiSuccessResponse<DashboardMetrics>>(`/admin/dashboard/metrics?${params.toString()}`);
    return res.data;
  },

  // 2. Orders Cross-Store Monitoring
  async getOrders(params?: { status?: string; store_id?: string; q?: string; page?: number; start_date?: string; end_date?: string }) {
    const query = new URLSearchParams();
    if (params?.status) query.append('status', params.status);
    if (params?.store_id) query.append('store_id', params.store_id);
    if (params?.q) query.append('q', params.q);
    if (params?.start_date) query.append('start_date', params.start_date);
    if (params?.end_date) query.append('end_date', params.end_date);
    if (params?.page) query.append('page', params.page.toString());
    return apiClient<ApiSuccessResponse<AdminOrder[]>>(`/admin/orders?${query.toString()}`);
  },

  async getOrder(id: string) {
    const res = await apiClient<ApiSuccessResponse<AdminOrder>>(`/admin/orders/${id}`);
    return res.data;
  },

  // 3. Payments & Financial Transactions
  async getPayments(params?: { status?: string; method?: string; q?: string; page?: number; start_date?: string; end_date?: string }) {
    const query = new URLSearchParams();
    if (params?.status) query.append('status', params.status);
    if (params?.method) query.append('method', params.method);
    if (params?.q) query.append('q', params.q);
    if (params?.start_date) query.append('start_date', params.start_date);
    if (params?.end_date) query.append('end_date', params.end_date);
    if (params?.page) query.append('page', params.page.toString());
    return apiClient<ApiSuccessResponse<AdminPayment[]>>(`/admin/payments?${query.toString()}`);
  },

  async getPayment(id: string) {
    const res = await apiClient<ApiSuccessResponse<AdminPayment>>(`/admin/payments/${id}`);
    return res.data;
  },

  // 4. Store Approvals
  async getStores(params?: { approval_status?: string; operational_status?: string; q?: string; page?: number }) {
    const query = new URLSearchParams();
    if (params?.approval_status) query.append('approval_status', params.approval_status);
    if (params?.operational_status) query.append('operational_status', params.operational_status);
    if (params?.q) query.append('q', params.q);
    if (params?.page) query.append('page', params.page.toString());
    return apiClient<ApiSuccessResponse<AdminStore[]>>(`/admin/stores?${query.toString()}`);
  },

  async getStore(id: string) {
    const res = await apiClient<ApiSuccessResponse<AdminStore>>(`/admin/stores/${id}`);
    return res.data;
  },

  async approveStore(id: string) {
    return apiClient<ApiSuccessResponse<AdminStore>>(`/admin/stores/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({}),
    });
  },

  async rejectStore(id: string, reason: string) {
    return apiClient<ApiSuccessResponse<AdminStore>>(`/admin/stores/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  },

  // 5. User Management
  async getUsers(params?: { role?: string; status?: string; q?: string; page?: number }) {
    const query = new URLSearchParams();
    if (params?.role) query.append('role', params.role);
    if (params?.status) query.append('status', params.status);
    if (params?.q) query.append('q', params.q);
    if (params?.page) query.append('page', params.page.toString());
    return apiClient<ApiSuccessResponse<AdminUser[]>>(`/admin/users?${query.toString()}`);
  },

  async getUser(id: string) {
    const res = await apiClient<ApiSuccessResponse<AdminUser>>(`/admin/users/${id}`);
    return res.data;
  },

  async updateUserStatus(id: string, status: 'active' | 'suspended' | 'banned', reason?: string) {
    return apiClient<ApiSuccessResponse<AdminUser>>(`/admin/users/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status, reason }),
    });
  },

  // 6. Categories Master CRUD
  async getCategories(params?: { status?: string; q?: string }) {
    const query = new URLSearchParams();
    if (params?.status) query.append('status', params.status);
    if (params?.q) query.append('q', params.q);
    const res = await apiClient<ApiSuccessResponse<ServiceCategory[]>>(`/admin/categories?${query.toString()}`);
    return res.data;
  },

  async createCategory(data: { name: string; slug?: string; status?: 'active' | 'inactive' }) {
    return apiClient<ApiSuccessResponse<ServiceCategory>>('/admin/categories', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  async updateCategory(id: string, data: { name: string; slug?: string; status: 'active' | 'inactive' }) {
    return apiClient<ApiSuccessResponse<ServiceCategory>>(`/admin/categories/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  async deleteCategory(id: string) {
    return apiClient<ApiSuccessResponse<{ id: string }>>(`/admin/categories/${id}`, {
      method: 'DELETE',
    });
  },

  // 7. Platform Commission Setting
  async getCommission() {
    const res = await apiClient<ApiSuccessResponse<PlatformCommission>>('/admin/platform-settings/commission');
    return res.data;
  },

  async updateCommission(amount: number, description?: string) {
    return apiClient<ApiSuccessResponse<PlatformCommission>>('/admin/platform-settings/commission', {
      method: 'PATCH',
      body: JSON.stringify({ amount, description }),
    });
  },

  // 8. Audit Logs
  async getAuditLogs(params?: { action?: string; entity_type?: string; actor_id?: string; q?: string; page?: number }) {
    const query = new URLSearchParams();
    if (params?.action) query.append('action', params.action);
    if (params?.entity_type) query.append('entity_type', params.entity_type);
    if (params?.actor_id) query.append('actor_id', params.actor_id);
    if (params?.q) query.append('q', params.q);
    if (params?.page) query.append('page', params.page.toString());
    return apiClient<ApiSuccessResponse<AuditLogItem[]>>(`/admin/audit-logs?${query.toString()}`);
  },

  async getAuditLog(id: string) {
    const res = await apiClient<ApiSuccessResponse<AuditLogItem>>(`/admin/audit-logs/${id}`);
    return res.data;
  },
};
