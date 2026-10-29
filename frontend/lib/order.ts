import apiClient, { ApiSuccessResponse } from './api';

export type OrderStatus =
  | 'PENDING_PICKUP'
  | 'PICKUP_ASSIGNED'
  | 'PICKED_UP'
  | 'AT_STORE'
  | 'WEIGHED'
  | 'AWAITING_PRICE_APPROVAL'
  | 'PRICE_APPROVED'
  | 'AWAITING_PAYMENT'
  | 'PAID'
  | 'PROCESSING'
  | 'READY_FOR_DELIVERY'
  | 'DELIVERY_ASSIGNED'
  | 'OUT_FOR_DELIVERY'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'PAYMENT_FAILED'
  | 'PAYMENT_EXPIRED';

export type PaymentStatus =
  | 'UNPAID'
  | 'PAID'
  | 'PAYMENT_FAILED'
  | 'PAYMENT_EXPIRED';

export interface AddressSnapshot {
  label?: string;
  address_text: string;
  latitude: number;
  longitude: number;
  recipient_name?: string;
  recipient_phone?: string;
  notes?: string;
}

export interface OrderItemSnapshot {
  id: string;
  service_id: string;
  service_name: string;
  pricing_unit: string;
  unit_price: number;
  minimum_charge: number;
  quantity_estimated: number;
  quantity_actual: number | null;
  line_estimated_amount: number;
  line_final_amount: number | null;
}

export interface OrderStatusHistoryItem {
  id: string;
  from_status: OrderStatus | null;
  to_status: OrderStatus;
  actor_role: string;
  actor_name?: string;
  note?: string;
  timestamp: string;
}

export interface OrderDetail {
  id: string;
  order_number: string;
  customer_id: string;
  customer_name?: string;
  customer_phone?: string;
  store_id: string;
  store_name?: string;
  courier_id: string | null;
  courier_name?: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  estimated_weight_kg: number | null;
  actual_weight_kg: number | null;
  actual_weight?: number | null;
  estimated_weight?: number | null;
  estimated_amount: number;
  final_amount: number | null;
  estimated_price?: number | null;
  final_price?: number | null;
  total_amount?: number;
  delivery_fee: number;
  pickup_fee?: number;
  platform_fee?: number;
  discount?: number;
  tax?: number;
  commission_amount: number;
  pickup_address: AddressSnapshot;
  delivery_address: AddressSnapshot;
  customer_notes?: string;
  store_notes?: string | null;
  store_payment_methods?: {
    cod: boolean;
    qris: boolean;
    va: boolean;
    va_banks: string[];
  };
  price_approved_at: string | null;
  cancelled_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  payment_expires_at?: string | null;
  items?: OrderItemSnapshot[];
  status_histories?: OrderStatusHistoryItem[];
}

export interface OrderTrackingTimelineItem {
  id: string;
  from_status: OrderStatus | null;
  to_status: OrderStatus;
  actor_role: string;
  actor_name?: string;
  note?: string;
  timestamp: string;
}

export interface OrderTrackingData {
  order_id: string;
  order_number: string;
  current_status: OrderStatus;
  payment_status: PaymentStatus;
  store_name?: string;
  courier_name?: string;
  timeline: OrderTrackingTimelineItem[];
}

export interface CreateOrderPayload {
  store_id: string;
  pickup_address: AddressSnapshot;
  delivery_address?: AddressSnapshot;
  customer_notes?: string;
  store_notes?: string | null;
  store_payment_methods?: {
    cod: boolean;
    qris: boolean;
    va: boolean;
    va_banks: string[];
  };
  items: {
    service_id: string;
    quantity: number;
  }[];
}

export interface TransitionOrderPayload {
  to_status: OrderStatus;
  courier_id?: string;
  actual_weight_kg?: number;
  items?: {
    item_id?: string;
    id?: string;
    quantity_actual: number;
  }[];
  note?: string;
  payment_method?: string;
}

export interface CancelOrderPayload {
  reason?: string;
  note?: string;
}

export const ORDER_STATUS_META: Record<
  OrderStatus,
  {
    label: string;
    description: string;
    badgeVariant: 'default' | 'teal' | 'amber' | 'sky' | 'secondary' | 'outline' | 'success' | 'warning' | 'danger' | 'info';
    stepIndex: number;
  }
> = {
  PENDING_PICKUP: {
    label: 'Menunggu Penugasan Kurir',
    description: 'Pesanan telah dibuat dan menunggu toko menugaskan kurir jemput.',
    badgeVariant: 'warning',
    stepIndex: 1,
  },
  PICKUP_ASSIGNED: {
    label: 'Kurir Ditugaskan Jemput',
    description: 'Kurir telah ditugaskan dan segera menuju ke lokasi penjemputan.',
    badgeVariant: 'teal',
    stepIndex: 2,
  },
  PICKED_UP: {
    label: 'Cucian Dijemput',
    description: 'Cucian telah diambil oleh kurir dan sedang dibawa menuju outlet.',
    badgeVariant: 'teal',
    stepIndex: 3,
  },
  AT_STORE: {
    label: 'Tiba di Outlet',
    description: 'Cucian sudah sampai di outlet dan siap untuk ditimbang.',
    badgeVariant: 'secondary',
    stepIndex: 4,
  },
  WEIGHED: {
    label: 'Selesai Ditimbang',
    description: 'Penimbangan telah selesai dan harga final telah dihitung oleh sistem.',
    badgeVariant: 'sky',
    stepIndex: 5,
  },
  AWAITING_PRICE_APPROVAL: {
    label: 'Menunggu Persetujuan Harga',
    description: 'Perlu konfirmasi persetujuan harga final dari Anda.',
    badgeVariant: 'warning',
    stepIndex: 6,
  },
  PRICE_APPROVED: {
    label: 'Harga Disetujui',
    description: 'Anda telah menyetujui rincian harga final cucian.',
    badgeVariant: 'success',
    stepIndex: 7,
  },
  AWAITING_PAYMENT: {
    label: 'Menunggu Pembayaran',
    description: 'Silakan selesaikan pembayaran untuk melanjutkan proses cuci.',
    badgeVariant: 'warning',
    stepIndex: 8,
  },
  PAID: {
    label: 'Pembayaran Lunas',
    description: 'Pembayaran telah terverifikasi dan siap untuk dicuci.',
    badgeVariant: 'success',
    stepIndex: 9,
  },
  PROCESSING: {
    label: 'Sedang Dicuci & Diproses',
    description: 'Cucian sedang dalam tahap pencucian, pengeringan, atau penyetrikaan.',
    badgeVariant: 'teal',
    stepIndex: 10,
  },
  READY_FOR_DELIVERY: {
    label: 'Siap Diantar',
    description: 'Cucian telah bersih, rapi, dan dikemas siap untuk diantar.',
    badgeVariant: 'success',
    stepIndex: 11,
  },
  DELIVERY_ASSIGNED: {
    label: 'Kurir Pengantaran Ditugaskan',
    description: 'Kurir pengantaran telah ditugaskan membawa cucian Anda.',
    badgeVariant: 'teal',
    stepIndex: 12,
  },
  OUT_FOR_DELIVERY: {
    label: 'Sedang Diantar ke Alamat',
    description: 'Kurir sedang dalam perjalanan mengantarkan cucian bersih ke alamat Anda.',
    badgeVariant: 'teal',
    stepIndex: 13,
  },
  COMPLETED: {
    label: 'Pesanan Selesai',
    description: 'Pesanan telah selesai dan cucian telah diterima.',
    badgeVariant: 'success',
    stepIndex: 14,
  },
  CANCELLED: {
    label: 'Pesanan Dibatalkan',
    description: 'Pesanan telah dibatalkan pada tahap persetujuan harga.',
    badgeVariant: 'danger',
    stepIndex: 0,
  },
  PAYMENT_FAILED: {
    label: 'Pembayaran Gagal',
    description: 'Transaksi pembayaran tidak berhasil. Silakan coba metode lain.',
    badgeVariant: 'danger',
    stepIndex: 0,
  },
  PAYMENT_EXPIRED: {
    label: 'Pembayaran Kedaluwarsa',
    description: 'Batas waktu pembayaran telah habis.',
    badgeVariant: 'danger',
    stepIndex: 0,
  },
};

export const ORDER_STEPS = [
  { status: 'PENDING_PICKUP', title: 'Order Dibuat' },
  { status: 'PICKUP_ASSIGNED', title: 'Kurir Ditugaskan' },
  { status: 'PICKED_UP', title: 'Dijemput' },
  { status: 'AT_STORE', title: 'Tiba di Toko' },
  { status: 'WEIGHED', title: 'Ditimbang' },
  { status: 'AWAITING_PRICE_APPROVAL', title: 'Persetujuan Harga' },
  { status: 'PAID', title: 'Pembayaran' },
  { status: 'PROCESSING', title: 'Diproses' },
  { status: 'READY_FOR_DELIVERY', title: 'Siap Antar' },
  { status: 'OUT_FOR_DELIVERY', title: 'Diantar' },
  { status: 'COMPLETED', title: 'Selesai' },
];

export type OrderTabCategory = 'active' | 'completed' | 'cancelled';

/**
 * Single Source of Truth to map order status into tab filters / counts.
 */
export function getOrderCategory(status: OrderStatus): OrderTabCategory {
  if (status === 'COMPLETED') {
    return 'completed';
  }
  if (status === 'CANCELLED' || status === 'PAYMENT_FAILED' || status === 'PAYMENT_EXPIRED') {
    return 'cancelled';
  }
  return 'active';
}

/**
 * Check if order requires urgent user action (price approval or payment).
 */
export function isOrderActionRequired(status: OrderStatus): boolean {
  return status === 'AWAITING_PRICE_APPROVAL' || status === 'AWAITING_PAYMENT';
}

export async function createOrder(payload: CreateOrderPayload): Promise<OrderDetail> {
  const response = await apiClient<ApiSuccessResponse<OrderDetail>>('/orders', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function getOrders(params?: {
  status?: string;
  role?: string;
  page?: number;
  per_page?: number;
}): Promise<OrderDetail[]> {
  const query = new URLSearchParams();
  if (params?.status) query.set('status', params.status);
  if (params?.role) query.set('role', params.role);
  if (params?.page) query.set('page', params.page.toString());
  if (params?.per_page) query.set('per_page', params.per_page.toString());

  const qs = query.toString();
  const endpoint = `/orders${qs ? `?${qs}` : ''}`;

  const response = await apiClient<ApiSuccessResponse<OrderDetail[]>>(endpoint);
  return response.data;
}

export async function getOrder(id: string): Promise<OrderDetail> {
  const response = await apiClient<ApiSuccessResponse<OrderDetail>>(`/orders/${id}`);
  return response.data;
}

export async function getOrderTracking(id: string): Promise<OrderTrackingData> {
  const response = await apiClient<ApiSuccessResponse<OrderTrackingData>>(`/orders/${id}/tracking`);
  return response.data;
}

export async function transitionOrder(
  id: string,
  payload: TransitionOrderPayload
): Promise<OrderDetail> {
  const response = await apiClient<ApiSuccessResponse<OrderDetail>>(`/orders/${id}/transition`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function assignCourier(
  id: string,
  payload: { task_type?: 'pickup' | 'delivery' } = { task_type: 'pickup' }
): Promise<unknown> {
  const response = await apiClient<unknown>(`/orders/${id}/assign-courier`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response;
}

export async function cancelOrder(
  id: string,
  payload?: CancelOrderPayload
): Promise<OrderDetail> {
  const response = await apiClient<ApiSuccessResponse<OrderDetail>>(`/orders/${id}/cancel`, {
    method: 'POST',
    body: JSON.stringify(payload || {}),
  });
  return response.data;
}

export async function submitPriceApproval(
  id: string,
  payload: { action: 'approve' | 'reject'; reason?: string }
): Promise<OrderDetail> {
  if (payload.action === 'approve') {
    return transitionOrder(id, {
      to_status: 'PRICE_APPROVED',
    });
  } else {
    const response = await apiClient<ApiSuccessResponse<OrderDetail>>(`/orders/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason: payload.reason || 'Pelanggan menolak harga final setelah penimbangan.' }),
    });
    return response.data;
  }
}

export interface InAppNotificationItem {
  id: string;
  channel: string;
  event_type: string;
  title: string;
  message: string;
  payload?: Record<string, unknown>;
  status: 'unread' | 'read';
  sent_at?: string;
  read_at?: string;
  created_at: string;
}

export async function getNotifications(unreadOnly = false): Promise<{ unread_count: number; notifications: InAppNotificationItem[] }> {
  const response = await apiClient<ApiSuccessResponse<{ unread_count: number; notifications: InAppNotificationItem[] }>>(
    `/notifications${unreadOnly ? '?unread_only=1' : ''}`
  );
  return response.data;
}

export async function markNotificationAsRead(id: string): Promise<void> {
  await apiClient(`/notifications/${id}/read`, {
    method: 'PATCH',
  });
}

export async function markAllNotificationsAsRead(): Promise<void> {
  await apiClient('/notifications/read-all', {
    method: 'PATCH',
  });
}

// ================= FASE 8: PAYMENT & LEDGER TYPES & APIS =================

export interface PaymentInfo {
  payment_id?: string;
  order_id: string;
  order_number: string;
  method: 'va' | 'qris' | 'cod';
  bank_code?: string;
  va_number?: string;
  qr_string?: string;
  amount: number;
  status: 'pending' | 'paid' | 'failed' | 'expired';
  order_status?: OrderStatus;
  expiration_date?: string;
  expires_at?: string;
  payment_details?: {
    method_label?: string;
    instructions?: string;
    collected_by?: string;
    va_number?: string;
    qr_string?: string;
    [key: string]: unknown;
  };
  paid_at?: string;
}

export async function payOrder(
  orderId: string,
  payload: { method: 'va' | 'qris' | 'cod'; bank_code?: string }
): Promise<PaymentInfo> {
  const response = await apiClient<ApiSuccessResponse<PaymentInfo>>(`/orders/${orderId}/pay`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function getOrderPayment(orderId: string): Promise<{
  order_id: string;
  order_number: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  amount: number;
  latest_payment: PaymentInfo | null;
}> {
  const response = await apiClient<ApiSuccessResponse<{
    order_id: string;
    order_number: string;
    status: OrderStatus;
    payment_status: PaymentStatus;
    amount: number;
    latest_payment: PaymentInfo | null;
  }>>(`/orders/${orderId}/payment`);
  return response.data;
}

export async function simulatePaymentSuccess(orderId: string): Promise<{ order_id: string; status: OrderStatus; payment_status: PaymentStatus }> {
  const response = await apiClient<ApiSuccessResponse<{ order_id: string; status: OrderStatus; payment_status: PaymentStatus }>>(
    `/orders/${orderId}/payment/simulate-success`,
    {
      method: 'POST',
    }
  );
  return response.data;
}

export async function confirmCourierCod(orderId: string): Promise<{
  order_id: string;
  order_number: string;
  payment_status: PaymentStatus;
  collected_amount: number;
  collected_by: string;
}> {
  const response = await apiClient<ApiSuccessResponse<{
    order_id: string;
    order_number: string;
    payment_status: PaymentStatus;
    collected_amount: number;
    collected_by: string;
  }>>(`/courier/tasks/${orderId}/confirm-cod`, {
    method: 'POST',
  });
  return response.data;
}

export interface StoreLedgerEntryItem {
  id: string;
  entry_type: 'COD_RECEIVABLE' | 'COD_DEPOSIT' | 'COURIER_WAGE' | 'PLATFORM_COMMISSION' | 'PAYMENT_RECEIPT' | 'ADJUSTMENT';
  direction: 'debit' | 'credit';
  amount: number;
  description: string | null;
  order_id: string | null;
  order_number?: string | null;
  created_by_name?: string | null;
  created_at: string;
}

export interface StoreLedgerSummary {
  store_id: string;
  store_name: string;
  total_income: number;
  total_expense: number;
  net_balance: number;
  breakdown: {
    digital_payments: number;
    cod_deposits: number;
    courier_wages: number;
    platform_commission: number;
  };
  total_transactions: number;
}

export interface StoreLedgerResponse {
  summary: StoreLedgerSummary;
  entries: StoreLedgerEntryItem[];
}

export async function getStoreLedger(params?: {
  entry_type?: string;
  direction?: string;
  page?: number;
  per_page?: number;
}): Promise<StoreLedgerResponse> {
  const queryParams = new URLSearchParams();
  if (params?.entry_type) queryParams.set('entry_type', params.entry_type);
  if (params?.direction) queryParams.set('direction', params.direction);
  if (params?.page) queryParams.set('page', String(params.page));
  if (params?.per_page) queryParams.set('per_page', String(params.per_page));

  const url = `/store/ledger${queryParams.toString() ? `?${queryParams.toString()}` : ''}`;
  const response = await apiClient<ApiSuccessResponse<StoreLedgerResponse>>(url);
  return response.data;
}

