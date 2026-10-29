'use client';

import React, { useState } from 'react';
import dynamic from 'next/dynamic';
import { Search, Navigation, MapPin, AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { forwardGeocode, reverseGeocode } from '@/lib/profile';
import { Button } from '@/components/ui/Button';

const StoreLocationPickerMap = dynamic(() => import('./StoreLocationPickerMap'), {
  ssr: false,
  loading: () => (
    <div className="h-[360px] w-full rounded-xl bg-slate-100 flex flex-col items-center justify-center p-4 border border-slate-200 text-center animate-pulse">
      <MapPin className="w-8 h-8 text-slate-400 mb-2 animate-bounce" />
      <p className="text-xs text-slate-500 font-medium">Memuat peta lokasi...</p>
    </div>
  ),
});

export const DEFAULT_LATITUDE = -6.2088;
export const DEFAULT_LONGITUDE = 106.8456;

export function isDefaultCoordinates(lat?: number | string | null, lng?: number | string | null): boolean {
  if (lat == null || lng == null || lat === '' || lng === '') return true;
  const latNum = typeof lat === 'string' ? parseFloat(lat) : lat;
  const lngNum = typeof lng === 'string' ? parseFloat(lng) : lng;
  if (isNaN(latNum) || isNaN(lngNum)) return true;
  if (latNum === 0 && lngNum === 0) return true;
  return (
    Math.abs(latNum - DEFAULT_LATITUDE) < 0.0005 &&
    Math.abs(lngNum - DEFAULT_LONGITUDE) < 0.0005
  );
}

export interface StoreLocationPickerProps {
  latitude: number | null | undefined;
  longitude: number | null | undefined;
  onChange: (lat: number, lng: number) => void;
  onAddressResolved?: (info: { formatted_address?: string | null; city?: string | null; province?: string | null }) => void;
  serviceRadiusKm?: number;
  className?: string;
  label?: string;
  error?: string | null;
}

export const StoreLocationPicker: React.FC<StoreLocationPickerProps> = ({
  latitude,
  longitude,
  onChange,
  onAddressResolved,
  serviceRadiusKm,
  className = '',
  label = 'Titik Lokasi Toko di Peta',
  error,
}) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const [isDetectingGps, setIsDetectingGps] = useState(false);
  const [searchMessage, setSearchMessage] = useState<string | null>(null);

  const isDefault = isDefaultCoordinates(latitude, longitude);
  const hasCoordinates =
    latitude != null &&
    longitude != null &&
    !isNaN(Number(latitude)) &&
    !isNaN(Number(longitude)) &&
    !isDefault;

  // Search Address Forward Geocoding
  const handleSearchAddress = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    const query = searchQuery.trim();
    if (!query) {
      setSearchMessage('Ketik nama jalan, kelurahan, atau kota untuk mencari lokasi.');
      return;
    }

    setIsSearching(true);
    setSearchMessage(null);
    try {
      const result = await forwardGeocode(query);
      if (result && result.latitude != null && result.longitude != null) {
        onChange(Number(result.latitude.toFixed(6)), Number(result.longitude.toFixed(6)));
        setSearchMessage(`Lokasi ditemukan: ${result.formatted_address || query}`);

        if (onAddressResolved) {
          // Attempt reverse geocoding on precise coordinates to populate city/province
          try {
            const rev = await reverseGeocode({ latitude: result.latitude, longitude: result.longitude });
            onAddressResolved(rev);
          } catch {
            if (result.formatted_address) {
              onAddressResolved({ formatted_address: result.formatted_address });
            }
          }
        }
      } else {
        setSearchMessage('Lokasi tidak ditemukan. Coba gunakan kata kunci alamat yang lebih spesifik.');
      }
    } catch {
      setSearchMessage('Gagal mencari lokasi. Pastikan koneksi internet aktif atau geser pin manual pada peta.');
    } finally {
      setIsSearching(false);
    }
  };

  // Detect GPS
  const handleDetectGps = () => {
    if (typeof window === 'undefined' || !navigator.geolocation) {
      setSearchMessage('Browser Anda tidak mendukung deteksi lokasi otomatis.');
      return;
    }

    setIsDetectingGps(true);
    setSearchMessage(null);

    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        const lat = Number(pos.coords.latitude.toFixed(6));
        const lng = Number(pos.coords.longitude.toFixed(6));
        onChange(lat, lng);

        try {
          const rev = await reverseGeocode({ latitude: lat, longitude: lng });
          setSearchMessage(`GPS terdeteksi: ${rev.formatted_address || `${lat}, ${lng}`}`);
          if (onAddressResolved) {
            onAddressResolved(rev);
          }
        } catch {
          setSearchMessage(`GPS berhasil terdeteksi: ${lat}, ${lng}`);
        } finally {
          setIsDetectingGps(false);
        }
      },
      (err) => {
        setIsDetectingGps(false);
        setSearchMessage(`Gagal mendeteksi GPS: ${err.message}. Silakan tentukan lokasi pada peta.`);
      },
      { timeout: 10000, enableHighAccuracy: true }
    );
  };

  return (
    <div className={`space-y-3 ${className}`}>
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <label className="block text-sm font-semibold text-slate-800">
            {label} <span className="text-red-500">*</span>
          </label>
          <p className="text-xs text-slate-500 mt-0.5">
            Geser pin atau klik peta untuk menentukan titik gerai. Koordinat terisi otomatis dari pin.
          </p>
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleDetectGps}
          disabled={isDetectingGps}
          className="shrink-0 text-xs gap-1.5 border-emerald-600 text-emerald-700 hover:bg-emerald-50"
        >
          {isDetectingGps ? (
            <Loader2 className="w-3.5 h-3.5 animate-spin" />
          ) : (
            <Navigation className="w-3.5 h-3.5" />
          )}
          Deteksi GPS Saya
        </Button>
      </div>

      {/* Address Search Bar */}
      <div className="flex gap-2">
        <div className="relative flex-1">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                handleSearchAddress();
              }
            }}
            placeholder="Ketik alamat atau kota untuk menggerakkan pin peta..."
            className="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
          />
        </div>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          onClick={() => handleSearchAddress()}
          disabled={isSearching || !searchQuery.trim()}
          className="text-xs px-3 shrink-0"
        >
          {isSearching ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : 'Cari'}
        </Button>
      </div>

      {searchMessage && (
        <p className="text-[11px] text-slate-600 bg-slate-50 px-2.5 py-1.5 rounded border border-slate-200">
          {searchMessage}
        </p>
      )}

      {/* Interactive Map */}
      <StoreLocationPickerMap
        latitude={latitude}
        longitude={longitude}
        onChange={onChange}
        serviceRadiusKm={serviceRadiusKm}
      />

      {/* Coordinate Status Feedback */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2.5 rounded-lg border bg-slate-50 text-xs">
        <div className="flex items-center gap-2">
          {hasCoordinates ? (
            <>
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              <span className="font-medium text-emerald-800">
                Titik Koordinat Terpilih:
              </span>
              <span className="font-mono text-slate-700 bg-white px-2 py-0.5 rounded border border-slate-200">
                {Number(latitude).toFixed(6)}, {Number(longitude).toFixed(6)}
              </span>
            </>
          ) : (
            <>
              <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
              <span className="font-medium text-amber-800">
                {isDefault
                  ? 'Koordinat masih nilai default (-6.2088, 106.8456). Tentukan titik di peta.'
                  : 'Titik koordinat belum ditentukan. Geser pin pada peta.'}
              </span>
            </>
          )}
        </div>

        {serviceRadiusKm && serviceRadiusKm > 0 && hasCoordinates && (
          <span className="text-[11px] text-slate-500">
            Radius Jangkauan: <strong>{serviceRadiusKm} km</strong>
          </span>
        )}
      </div>

      {(error || (!hasCoordinates && error)) && (
        <p className="text-xs text-red-600 flex items-center gap-1 font-medium">
          <AlertCircle className="w-3.5 h-3.5 shrink-0" />
          {error || 'Koordinat lokasi toko wajib ditentukan dan tidak boleh menggunakan titik default.'}
        </p>
      )}
    </div>
  );
};

export default StoreLocationPicker;
