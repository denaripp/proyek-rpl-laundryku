import apiClient, { ApiSuccessResponse } from './api';

export interface ServiceCategoryItem {
  id: string;
  name: string;
  slug: string;
  status: string;
}

export interface StoreServiceItem {
  id: string;
  store_id?: string;
  category_id: string;
  category_name?: string;
  category_slug?: string;
  name: string;
  description?: string | null;
  pricing_unit: string;
  price_per_unit: number;
  minimum_charge: number;
  estimated_duration_hours: number;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface StoreCategoryItem {
  id: string;
  name: string;
  slug: string;
}

export interface StoreItem {
  id: string;
  name: string;
  nama?: string;
  slug?: string;
  description: string | null;
  address_text: string;
  short_address?: string;
  alamat_singkat?: string;
  latitude: number | null;
  longitude: number | null;
  service_radius_km: number;
  opening_hours: Record<string, string> | null;
  opening_hours_today?: {
    open?: string;
    close?: string;
    raw?: string;
  } | string | null;
  jam_buka_tutup_hari_ini?: string | null;
  is_open_now?: boolean;
  status_buka_sekarang?: boolean;
  approval_status: string;
  operational_status: string;
  logo_url?: string | null;
  categories: StoreCategoryItem[];
  services: StoreServiceItem[];
  top_services?: string[];
  tiga_layanan_teratas?: string[];
  services_count: number;
  active_services_count?: number;
  jumlah_layanan_aktif?: number;
  starting_price: number | null;
  harga_mulai_dari?: number | null;
  starting_unit?: string;
  rating_average?: number | null;
  rating_count?: number;
  rating?: number | null;
  reviews_count?: number | null;
  jumlah_ulasan?: number | null;
  distance_km?: number;
  jarak?: number;
  is_serviceable?: boolean;
  created_at: string;
}


export interface StoreListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface StoreNearbyMeta {
  customer_lat: number;
  customer_lng: number;
  search_radius_km: number;
  total: number;
  serviceable_total: number;
}

export interface GetStoresParams {
  search?: string;
  q?: string;
  category_id?: string;
  category_slug?: string;
  page?: number;
  per_page?: number;
  lat?: number;
  lng?: number;
}

export interface GetNearbyStoresParams {
  lat: number;
  lng: number;
  radius_km?: number;
  search?: string;
  category_id?: string;
  category_slug?: string;
}

export interface StoreLookupItem {
  id: string;
  name: string;
  address_text: string;
  logo_url?: string | null;
}

/**
 * Fast lookup search for stores by query (debounced in UI)
 */
export async function lookupStores(q: string, signal?: AbortSignal): Promise<StoreLookupItem[]> {
  const query = new URLSearchParams();
  query.set('q', q);
  const res = await apiClient<ApiSuccessResponse<StoreLookupItem[]>>(`/stores/lookup?${query.toString()}`, {
    signal,
  });
  return res.data || [];
}

/**
 * Fetch list of active service categories
 */
export async function getServiceCategories(): Promise<ServiceCategoryItem[]> {
  const res = await apiClient<ApiSuccessResponse<ServiceCategoryItem[]>>('/service-categories');
  return res.data;
}

/**
 * Fetch approved and active stores with pagination & filter
 */
export async function getStores(params: GetStoresParams = {}): Promise<{ stores: StoreItem[]; meta: StoreListMeta }> {
  const query = new URLSearchParams();
  if (params.search) query.set('search', params.search);
  if (params.q) query.set('q', params.q);
  if (params.category_id) query.set('category_id', params.category_id);
  if (params.category_slug) query.set('category_slug', params.category_slug);
  if (params.page) query.set('page', params.page.toString());
  if (params.per_page) query.set('per_page', params.per_page.toString());
  if (params.lat !== undefined && params.lat !== null) query.set('lat', params.lat.toString());
  if (params.lng !== undefined && params.lng !== null) query.set('lng', params.lng.toString());

  const qs = query.toString();
  const endpoint = `/stores${qs ? `?${qs}` : ''}`;

  const res = await apiClient<ApiSuccessResponse<StoreItem[]> & { meta: StoreListMeta }>(endpoint);
  return {
    stores: res.data,
    meta: res.meta,
  };
}

/**
 * Fetch store detail by UUID
 */
export async function getStore(id: string): Promise<StoreItem> {
  const res = await apiClient<ApiSuccessResponse<StoreItem>>(`/stores/${id}`);
  return res.data;
}

/**
 * Fetch nearby stores with Haversine distance and is_serviceable flag
 */
export async function getNearbyStores(
  params: GetNearbyStoresParams
): Promise<{ stores: StoreItem[]; meta: StoreNearbyMeta }> {
  const query = new URLSearchParams();
  query.set('lat', params.lat.toString());
  query.set('lng', params.lng.toString());
  if (params.radius_km !== undefined) query.set('radius_km', params.radius_km.toString());
  if (params.search) query.set('search', params.search);
  if (params.category_id) query.set('category_id', params.category_id);
  if (params.category_slug) query.set('category_slug', params.category_slug);

  const endpoint = `/stores/nearby?${query.toString()}`;
  const res = await apiClient<ApiSuccessResponse<StoreItem[]> & { meta: StoreNearbyMeta }>(endpoint);
  return {
    stores: res.data,
    meta: res.meta,
  };
}

// -------------------------------------------------------------
// FASE 4: Store Admin Services CRUD & Price Estimation API
// -------------------------------------------------------------

export interface PriceEstimateItemResult {
  service_id: string;
  service_name: string;
  category_name?: string;
  pricing_unit: string;
  price_per_unit: number;
  quantity: number;
  subtotal: number;
  minimum_charge: number;
  minimum_charge_applied: boolean;
  charged_amount: number;
  estimated_duration_hours: number;
}

export interface PriceEstimateSummary {
  total_items: number;
  total_raw_subtotal: number;
  total_estimated_price: number;
  max_estimated_duration_hours: number;
  currency: string;
  notes: string;
}

export interface PriceEstimationResult {
  store: {
    id: string;
    name: string;
    service_radius_km: number;
  };
  items: PriceEstimateItemResult[];
  summary: PriceEstimateSummary;
}

/**
 * Fetch price estimation from backend (FR-PRICE-01, FR-PRICE-03)
 */
export async function estimatePrice(data: {
  store_id: string;
  items: { service_id: string; quantity: number }[];
}): Promise<PriceEstimationResult> {
  const res = await apiClient<ApiSuccessResponse<PriceEstimationResult>>('/pricing/estimate', {
    method: 'POST',
    body: JSON.stringify(data),
  });
  return res.data;
}

export interface GetStoreServicesParams {
  store_id?: string;
  search?: string;
  category_id?: string;
  status?: 'all' | 'active' | 'inactive';
  is_active?: boolean | string;
  sort?: 'latest' | 'oldest' | 'name_asc' | 'name_desc' | 'price_asc' | 'price_desc';
  page?: number;
  per_page?: number;
}

export interface StoreServicesMeta {
  store_id: string;
  store_name: string;
  total: number;
  active_count?: number;
  inactive_count?: number;
  current_page?: number;
  last_page?: number;
  per_page?: number;
}

/**
 * Fetch list of services for store admin's store (FR-STORE-05)
 */
export async function getStoreAdminServices(params?: string | GetStoreServicesParams): Promise<{
  services: StoreServiceItem[];
  meta: StoreServicesMeta;
}> {
  let endpoint = '/store/services';
  if (typeof params === 'string') {
    endpoint += `?store_id=${params}`;
  } else if (params) {
    const query = new URLSearchParams();
    if (params.store_id) query.set('store_id', params.store_id);
    if (params.search) query.set('search', params.search);
    if (params.category_id && params.category_id !== 'all') query.set('category_id', params.category_id);
    if (params.status && params.status !== 'all') query.set('status', params.status);
    if (params.is_active !== undefined && params.is_active !== 'all') query.set('is_active', String(params.is_active));
    if (params.sort) query.set('sort', params.sort);
    if (params.page) query.set('page', params.page.toString());
    if (params.per_page) query.set('per_page', params.per_page.toString());
    const qs = query.toString();
    if (qs) endpoint += `?${qs}`;
  }

  const res = await apiClient<
    ApiSuccessResponse<StoreServiceItem[]> & {
      meta: StoreServicesMeta;
    }
  >(endpoint);
  return {
    services: res.data,
    meta: res.meta,
  };
}

/**
 * Create a new service for store admin's store (FR-STORE-05)
 */
export async function createStoreAdminService(data: {
  store_id?: string;
  category_id: string;
  name: string;
  description?: string | null;
  pricing_unit: string;
  price_per_unit: number;
  minimum_charge?: number;
  estimated_duration_hours: number;
  is_active?: boolean;
}): Promise<StoreServiceItem> {
  const res = await apiClient<ApiSuccessResponse<StoreServiceItem>>('/store/services', {
    method: 'POST',
    body: JSON.stringify(data),
  });
  return res.data;
}

/**
 * Update an existing service (FR-STORE-05)
 */
export async function updateStoreAdminService(
  id: string,
  data: {
    category_id?: string;
    name?: string;
    description?: string | null;
    pricing_unit?: string;
    price_per_unit?: number;
    minimum_charge?: number;
    estimated_duration_hours?: number;
    is_active?: boolean;
  }
): Promise<StoreServiceItem> {
  const res = await apiClient<ApiSuccessResponse<StoreServiceItem>>(`/store/services/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(data),
  });
  return res.data;
}

/**
 * Delete a service (FR-STORE-05)
 */
export async function deleteStoreAdminService(id: string): Promise<void> {
  await apiClient<ApiSuccessResponse<{ id: string; deleted: boolean }>>(`/store/services/${id}`, {
    method: 'DELETE',
  });
}

/**
 * Toggle or set active status of a service (FR-STORE-05)
 */
export async function toggleStoreAdminServiceStatus(id: string, isActive?: boolean): Promise<StoreServiceItem> {
  const body = isActive !== undefined ? JSON.stringify({ is_active: isActive }) : undefined;
  const res = await apiClient<ApiSuccessResponse<StoreServiceItem>>(
    `/store/services/${id}/status`,
    {
      method: 'PATCH',
      body,
    }
  );
  return res.data;
}

/**
 * Bulk activate or deactivate store services (max 50)
 */
export async function bulkUpdateStoreServiceStatus(data: {
  ids: string[];
  is_active: boolean;
  store_id?: string;
}): Promise<{ updated_count: number; is_active: boolean }> {
  const res = await apiClient<ApiSuccessResponse<{ updated_count: number; is_active: boolean }>>(
    '/store/services/bulk-status',
    {
      method: 'POST',
      body: JSON.stringify(data),
    }
  );
  return res.data;
}

export interface StoreRecentOrderItem {
  id: string;
  order_number: string;
  customer_name: string;
  status: string;
  status_label: string;
  total_amount: number;
  created_at: string;
}

export interface StoreMetricsData {
  store: {
    id: string;
    name: string;
    approval_status: string;
    operational_status: string;
    rejection_reason?: string | null;
  };
  metrics: {
    new_orders: number;
    processing_orders: number;
    ready_for_delivery: number;
    completed_today: number;
    today_revenue: number;
  };
  recent_orders: StoreRecentOrderItem[];
}

export async function getStoreMetrics(storeId?: string): Promise<StoreMetricsData> {
  let endpoint = '/store/dashboard';
  if (storeId) {
    endpoint += `?store_id=${storeId}`;
  }
  const res = await apiClient<ApiSuccessResponse<StoreMetricsData>>(endpoint);
  return res.data;
}

export async function toggleStoreOperationalStatus(
  storeId?: string,
  operationalStatus?: 'active' | 'inactive'
): Promise<{
  store_id: string;
  store_name: string;
  operational_status: string;
}> {
  const res = await apiClient<
    ApiSuccessResponse<{
      store_id: string;
      store_name: string;
      operational_status: string;
    }>
  >('/store/operational-status', {
    method: 'PATCH',
    body: JSON.stringify({
      store_id: storeId,
      operational_status: operationalStatus,
    }),
  });
  return res.data;
}

export interface StoreSidebarCounts {
  pelamar_kurir_pending: number;
  pesanan_baru: number;
  unread_chat: number;
  store_id?: string;
}

export async function getStoreSidebarCounts(storeId?: string): Promise<StoreSidebarCounts> {
  let endpoint = '/store/sidebar-counts';
  if (storeId) {
    endpoint += `?store_id=${storeId}`;
  }
  const res = await apiClient<ApiSuccessResponse<StoreSidebarCounts>>(endpoint);
  return res.data;
}

export {
  getStoreCouriers,
  recruitStoreCourier,
  terminateStoreCourier,
  rejectStoreCourier,
} from './courier';
export type {
  StoreCouriersData,
  AssignedCourierItem,
  AvailableCandidateItem,
} from './courier';

