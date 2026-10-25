import apiClient from './api';

export interface ProfileData {
  id: string;
  name: string;
  email: string | null;
  display_email?: string | null;
  has_real_email?: boolean;
  phone: string | null;
  display_phone?: string | null;
  status: string;
  avatar_url?: string | null;
  roles: string[];
  profile: {
    date_of_birth: string | null;
    has_photo: boolean;
    avatar_url?: string | null;
  };
  default_address?: AddressItem | null;
}

export interface AddressItem {
  id: string;
  user_id: string;
  label: string;
  address_text: string;
  latitude: number | null;
  longitude: number | null;
  is_default: boolean;
  created_at: string;
}

export interface CourierStatusData {
  applied: boolean;
  application: {
    id: string;
    store_id?: string | null;
    store_name?: string | null;
    store_address?: string | null;
    vehicle_type: string;
    verification_status: string;
    status: string;
    rejection_reason?: string | null;
    has_ktp: boolean;
    created_at: string;
  } | null;
}

export interface StoreStatusData {
  applied: boolean;
  store: {
    id: string;
    name: string;
    description: string | null;
    address_text: string;
    latitude: number | null;
    longitude: number | null;
    service_radius_km: number;
    opening_hours: Record<string, string> | null;
    approval_status: string;
    operational_status: string;
    documents: { id: string; document_type: string; verification_status: string }[];
    created_at: string;
  } | null;
}

// Profile API
export async function getProfile(): Promise<ProfileData> {
  const res = await apiClient<{ success: true; data: ProfileData }>('/profile');
  return res.data;
}

export async function updateProfile(data: { name?: string; date_of_birth?: string; email?: string }): Promise<ProfileData> {
  const res = await apiClient<{ success: true; data: ProfileData }>('/profile', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
  return res.data;
}

export async function uploadProfilePhoto(file: File): Promise<{ has_photo: boolean; avatar_url: string; photo_url: string }> {
  const formData = new FormData();
  formData.append('photo', file);

  const res = await apiClient<{ success: true; data: { has_photo: boolean; avatar_url: string; photo_url: string } }>('/profile/photo', {
    method: 'POST',
    body: formData,
  });
  return res.data;
}

export async function deleteProfilePhoto(): Promise<{ has_photo: boolean; avatar_url: null }> {
  const res = await apiClient<{ success: true; data: { has_photo: boolean; avatar_url: null } }>('/profile/photo', {
    method: 'DELETE',
  });
  return res.data;
}

// Addresses API
export interface GeocodeReverseResult {
  latitude: number;
  longitude: number;
  formatted_address: string;
  road?: string | null;
  kelurahan?: string | null;
  kecamatan?: string | null;
  city?: string | null;
  province?: string | null;
  postal_code?: string | null;
}

export async function reverseGeocode(coords: { latitude: number; longitude: number }): Promise<GeocodeReverseResult> {
  const res = await apiClient<{ success: true; data: GeocodeReverseResult }>('/geocode/reverse', {
    method: 'POST',
    body: JSON.stringify(coords),
  });
  return res.data;
}

export async function getAddresses(): Promise<AddressItem[]> {
  const res = await apiClient<{ success: true; data: AddressItem[] }>('/addresses');
  return res.data;
}

export async function createAddress(data: {
  label: string;
  address_text: string;
  latitude?: number | null;
  longitude?: number | null;
  is_default?: boolean;
  recipient_name?: string;
  phone?: string;
  rt_rw?: string;
  kelurahan?: string;
  kecamatan?: string;
  city?: string;
  province?: string;
  postal_code?: string;
  address_note?: string;
}): Promise<AddressItem> {
  const res = await apiClient<{ success: true; data: AddressItem }>('/addresses', {
    method: 'POST',
    body: JSON.stringify(data),
  });
  return res.data;
}

export async function updateAddress(
  id: string,
  data: {
    label?: string;
    address_text?: string;
    latitude?: number | null;
    longitude?: number | null;
    is_default?: boolean;
    recipient_name?: string;
    phone?: string;
    rt_rw?: string;
    kelurahan?: string;
    kecamatan?: string;
    city?: string;
    province?: string;
    postal_code?: string;
    address_note?: string;
  }
): Promise<AddressItem> {
  const res = await apiClient<{ success: true; data: AddressItem }>(`/addresses/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  });
  return res.data;
}

export async function deleteAddress(id: string): Promise<void> {
  await apiClient(`/addresses/${id}`, {
    method: 'DELETE',
  });
}

export async function setDefaultAddress(id: string): Promise<AddressItem> {
  const res = await apiClient<{ success: true; data: AddressItem }>(`/addresses/${id}/set-default`, {
    method: 'POST',
  });
  return res.data;
}

// Upgrades API
export async function getCourierStatus(): Promise<CourierStatusData> {
  const res = await apiClient<{ success: true; data: CourierStatusData }>('/upgrades/courier');
  return res.data;
}

export async function applyCourier(formData: FormData): Promise<unknown> {
  const res = await apiClient<{ success: true; data: unknown }>('/upgrades/courier', {
    method: 'POST',
    body: formData,
  });
  return res.data;
}

export async function getStoreStatus(): Promise<StoreStatusData> {
  const res = await apiClient<{ success: true; data: StoreStatusData }>('/upgrades/store');
  return res.data;
}

export async function applyStore(formData: FormData): Promise<unknown> {
  const res = await apiClient<{ success: true; data: unknown }>('/upgrades/store', {
    method: 'POST',
    body: formData,
  });
  return res.data;
}
