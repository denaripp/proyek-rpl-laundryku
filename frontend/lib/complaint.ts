import apiClient, { ApiSuccessResponse } from './api';

export interface ComplaintAttachment {
  id: string;
  file_name: string | null;
  file_size: number | null;
  mime_type: string | null;
  url: string;
  created_at: string;
}

export interface Complaint {
  id: string;
  order_id: string;
  order_number: string;
  customer_id: string;
  customer_name: string;
  store_id: string;
  store_name: string;
  category: 'DAMAGED_ITEM' | 'LOST_ITEM' | 'LATE_DELIVERY' | 'SERVICE_QUALITY' | 'INCORRECT_BILLING' | 'OTHER' | string;
  description: string;
  status: 'OPEN' | 'IN_INVESTIGATION' | 'RESOLVED' | 'REJECTED' | string;
  resolution_note: string | null;
  resolved_at: string | null;
  resolved_by: string | null;
  resolver_name: string | null;
  attachments: ComplaintAttachment[];
  created_at: string;
  updated_at: string;
}

export const COMPLAINT_CATEGORY_LABELS: Record<string, string> = {
  DAMAGED_ITEM: 'Pakaian Rusak / Robek',
  LOST_ITEM: 'Pakaian Hilang / Tertukar',
  LATE_DELIVERY: 'Keterlambatan Pengantaran',
  SERVICE_QUALITY: 'Kualitas Kurang Bersih / Wangi',
  INCORRECT_BILLING: 'Ketidaksesuaian Tagihan',
  OTHER: 'Lainnya / Pertanyaan Umum',
};

export const COMPLAINT_STATUS_LABELS: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' }> = {
  OPEN: { label: 'Menunggu Tanggapan', variant: 'warning' },
  IN_INVESTIGATION: { label: 'Sedang Diinvestigasi', variant: 'info' },
  RESOLVED: { label: 'Selesai Ditangani', variant: 'success' },
  REJECTED: { label: 'Ditolak', variant: 'danger' },
};

/**
 * Customer creates a complaint linked to an order
 */
export async function createComplaint(
  orderId: string,
  category: string,
  description: string,
  photos?: File[]
): Promise<ApiSuccessResponse<Complaint>> {
  const formData = new FormData();
  formData.append('category', category);
  formData.append('description', description);

  if (photos && photos.length > 0) {
    photos.forEach((file) => {
      formData.append('photos[]', file);
    });
  }

  return apiClient<ApiSuccessResponse<Complaint>>(`/orders/${orderId}/complaints`, {
    method: 'POST',
    body: formData,
  });
}

/**
 * Get customer's complaints list
 */
export async function getCustomerComplaints(params?: {
  status?: string;
  order_id?: string;
  page?: number;
}): Promise<ApiSuccessResponse<Complaint[]>> {
  const query = new URLSearchParams();
  if (params?.status) query.append('status', params.status);
  if (params?.order_id) query.append('order_id', params.order_id);
  if (params?.page) query.append('page', params.page.toString());

  const qs = query.toString() ? `?${query.toString()}` : '';
  return apiClient<ApiSuccessResponse<Complaint[]>>(`/complaints${qs}`);
}

/**
 * Get single complaint detail
 */
export async function getComplaintDetail(id: string): Promise<ApiSuccessResponse<Complaint>> {
  return apiClient<ApiSuccessResponse<Complaint>>(`/complaints/${id}`);
}

/**
 * Store Admin: Get complaints for owned store
 */
export async function getStoreAdminComplaints(params?: {
  status?: string;
  search?: string;
  page?: number;
}): Promise<ApiSuccessResponse<Complaint[]>> {
  const query = new URLSearchParams();
  if (params?.status) query.append('status', params.status);
  if (params?.search) query.append('search', params.search);
  if (params?.page) query.append('page', params.page.toString());

  const qs = query.toString() ? `?${query.toString()}` : '';
  return apiClient<ApiSuccessResponse<Complaint[]>>(`/store-admin/complaints${qs}`);
}

/**
 * Store Admin: Update complaint status & resolution note
 */
export async function updateComplaintStatus(
  id: string,
  status: 'OPEN' | 'IN_INVESTIGATION' | 'RESOLVED' | 'REJECTED',
  resolutionNote?: string
): Promise<ApiSuccessResponse<Complaint>> {
  return apiClient<ApiSuccessResponse<Complaint>>(`/store-admin/complaints/${id}/status`, {
    method: 'PATCH',
    body: JSON.stringify({
      status,
      resolution_note: resolutionNote,
    }),
  });
}
