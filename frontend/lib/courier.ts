import apiClient, { ApiSuccessResponse } from './api';
import { AddressSnapshot, OrderStatus, PaymentStatus } from './order';

export interface CourierProfileSummary {
  is_assigned: boolean;
  courier_profile_id?: string;
  vehicle_type?: string;
  verification_status?: string;
  assignment_status?: string;
  started_at?: string;
  store?: {
    id: string;
    name: string;
    address_text: string;
    service_radius_km: number;
  };
  stats?: {
    available_tasks: number;
    active_tasks: number;
    completed_tasks: number;
  };
}

export interface CourierTaskItem {
  id: string;
  order_id: string;
  order_number: string;
  task_type: 'pickup' | 'delivery';
  status: OrderStatus;
  payment_status: PaymentStatus;
  is_assigned_to_me: boolean;
  courier_id: string | null;
  courier_name?: string;
  customer_name?: string;
  customer_phone?: string;
  store_id: string;
  store_name?: string;
  store_address?: string;
  pickup_address: AddressSnapshot;
  delivery_address: AddressSnapshot;
  estimated_amount: number;
  final_amount: number | null;
  estimated_weight_kg: number | null;
  actual_weight_kg: number | null;
  customer_notes?: string;
  items_count: number;
  items?: {
    id: string;
    service_name: string;
    pricing_unit: string;
    quantity_estimated: number;
    quantity_actual: number | null;
  }[];
  created_at: string;
  updated_at: string;
}

export interface AssignedCourierItem {
  assignment_id: string;
  courier_profile_id: string;
  user_id: string;
  name: string;
  phone?: string;
  email?: string;
  vehicle_type: string;
  status: 'active' | 'inactive';
  started_at: string;
  ended_at?: string | null;
  recruited_by_name?: string;
}

export interface AvailableCandidateItem {
  courier_profile_id: string;
  user_id: string;
  name: string;
  phone?: string;
  email?: string;
  vehicle_type: string;
  verification_status: string;
  applied_at: string;
}

export interface StoreCouriersData {
  store: {
    id: string;
    name: string;
  };
  assigned_couriers: AssignedCourierItem[];
  available_candidates: AvailableCandidateItem[];
}

// -------------------------------------------------------------
// Courier Operations API (FR-COURIER-03..06, US-COU-02..05)
// -------------------------------------------------------------

/**
 * Fetch current authenticated courier profile and active store summary
 */
export async function getCourierProfile(): Promise<CourierProfileSummary> {
  const res = await apiClient<ApiSuccessResponse<CourierProfileSummary>>('/courier/profile');
  return res.data;
}

/**
 * Fetch list of tasks for courier (scoped to their active store)
 */
export async function getCourierTasks(tab: 'available' | 'active' | 'completed' | 'all' = 'all'): Promise<CourierTaskItem[]> {
  const res = await apiClient<ApiSuccessResponse<CourierTaskItem[]>>(`/courier/tasks?tab=${tab}`);
  return res.data;
}

/**
 * Fetch detail of a single task
 */
export async function getCourierTask(orderId: string): Promise<CourierTaskItem> {
  const res = await apiClient<ApiSuccessResponse<CourierTaskItem>>(`/courier/tasks/${orderId}`);
  return res.data;
}

/**
 * Atomically claim an available pickup or delivery task (FR-COURIER-04, FR-COURIER-05)
 */
export async function claimCourierTask(orderId: string): Promise<CourierTaskItem> {
  const res = await apiClient<ApiSuccessResponse<CourierTaskItem>>(`/courier/tasks/${orderId}/claim`, {
    method: 'POST',
  });
  return res.data;
}

/**
 * Progress status of assigned task (FR-COURIER-06, US-COU-04, US-COU-05)
 */
export async function transitionCourierTask(
  orderId: string,
  payload: { to_status: OrderStatus; note?: string }
): Promise<CourierTaskItem> {
  const res = await apiClient<ApiSuccessResponse<CourierTaskItem>>(`/courier/tasks/${orderId}/transition`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return res.data;
}

// -------------------------------------------------------------
// Store Admin Courier Management API (FR-COURIER-02, US-STORE-04)
// -------------------------------------------------------------

/**
 * Fetch assigned couriers and candidate applications for the store
 */
export async function getStoreCouriers(): Promise<StoreCouriersData> {
  const res = await apiClient<ApiSuccessResponse<StoreCouriersData>>('/store-admin/couriers');
  return res.data;
}

/**
 * Recruit / approve a candidate into store (enforces single-store constraint)
 */
export async function recruitStoreCourier(courierProfileId: string): Promise<unknown> {
  const res = await apiClient<ApiSuccessResponse<unknown>>(`/store-admin/couriers/recruit/${courierProfileId}`, {
    method: 'POST',
  });
  return res.data;
}

/**
 * Terminate / deactivate a courier's assignment in this store
 */
export async function terminateStoreCourier(assignmentId: string): Promise<unknown> {
  const res = await apiClient<ApiSuccessResponse<unknown>>(`/store-admin/couriers/terminate/${assignmentId}`, {
    method: 'POST',
  });
  return res.data;
}

/**
 * Reject a courier applicant
 */
export async function rejectStoreCourier(courierProfileId: string, reason?: string): Promise<unknown> {
  const res = await apiClient<ApiSuccessResponse<unknown>>(`/store-admin/couriers/reject/${courierProfileId}`, {
    method: 'POST',
    body: reason ? JSON.stringify({ reason }) : undefined,
  });
  return res.data;
}
