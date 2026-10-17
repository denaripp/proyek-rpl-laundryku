'use client';

import React, { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { StoreItem } from '@/lib/store';

export interface StoreMapProps {
  customerLocation: { lat: number; lng: number } | null;
  stores: StoreItem[];
  selectedStoreId?: string | null;
  onStoreSelect?: (store: StoreItem) => void;
  onLocationChange?: (lat: number, lng: number) => void;
  searchRadiusKm?: number;
  className?: string;
  showLegend?: boolean;
  autoOpenPopup?: boolean;
  isMiniMap?: boolean;
}

const isValidLatLng = (lat: unknown, lng: unknown): lat is number => {
  return (
    typeof lat === 'number' &&
    typeof lng === 'number' &&
    !isNaN(lat) &&
    !isNaN(lng) &&
    lat >= -90 &&
    lat <= 90 &&
    lng >= -180 &&
    lng <= 180
  );
};

export const StoreMap: React.FC<StoreMapProps> = ({
  customerLocation,
  stores,
  selectedStoreId,
  onStoreSelect,
  onLocationChange,
  searchRadiusKm = 10,
  className = 'h-[400px] w-full rounded-2xl overflow-hidden shadow-inner',
  showLegend,
  autoOpenPopup = true,
  isMiniMap = false,
}) => {
  const mapContainerRef = useRef<HTMLDivElement | null>(null);
  const mapInstanceRef = useRef<L.Map | null>(null);
  const markersLayerRef = useRef<L.LayerGroup | null>(null);
  const customerMarkerRef = useRef<L.Marker | null>(null);
  const radiusCircleRef = useRef<L.Circle | null>(null);
  const isMountedRef = useRef<boolean>(true);

  // Keep latest callbacks in refs to avoid stale closures in Leaflet events
  const onLocationChangeRef = useRef(onLocationChange);
  const onStoreSelectRef = useRef(onStoreSelect);

  useEffect(() => {
    onLocationChangeRef.current = onLocationChange;
  }, [onLocationChange]);

  useEffect(() => {
    onStoreSelectRef.current = onStoreSelect;
  }, [onStoreSelect]);

  const initialCenterRef = useRef<[number, number]>(
    isMiniMap && stores.length > 0 && isValidLatLng(stores[0].latitude, stores[0].longitude)
      ? [stores[0].latitude as number, stores[0].longitude as number]
      : customerLocation && isValidLatLng(customerLocation.lat, customerLocation.lng)
      ? [customerLocation.lat as number, customerLocation.lng as number]
      : [-6.8900, 107.6150]
  );

  const showLegendResolved = showLegend !== undefined ? showLegend : !isMiniMap;

  // Initialize Leaflet Map
  useEffect(() => {
    isMountedRef.current = true;
    if (!mapContainerRef.current || mapInstanceRef.current) return;

    const map = L.map(mapContainerRef.current, {
      center: initialCenterRef.current,
      zoom: isMiniMap ? 14 : 13,
      zoomControl: !isMiniMap,
    });

    // OpenStreetMap Tile Layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
    }).addTo(map);

    const markersLayer = L.layerGroup().addTo(map);
    markersLayerRef.current = markersLayer;
    mapInstanceRef.current = map;

    // Allow user to click on map to change customer position
    map.on('click', (e: L.LeafletMouseEvent) => {
      if (!isMountedRef.current) return;
      if (onLocationChangeRef.current && isValidLatLng(e.latlng.lat, e.latlng.lng)) {
        onLocationChangeRef.current(e.latlng.lat, e.latlng.lng);
      }
    });

    // ResizeObserver to handle container resize or visibility toggles (e.g. mobile tab switch)
    let resizeObserver: ResizeObserver | null = null;
    if (typeof ResizeObserver !== 'undefined' && mapContainerRef.current) {
      resizeObserver = new ResizeObserver(() => {
        if (!isMountedRef.current || !mapInstanceRef.current) return;
        try {
          const mapSize = mapInstanceRef.current.getSize();
          if (mapSize.x > 0 && mapSize.y > 0) {
            mapInstanceRef.current.invalidateSize({ pan: false, debounceMoveend: true });
          }
        } catch {
          // Safeguard against unmounted access
        }
      });
      resizeObserver.observe(mapContainerRef.current);
    }

    return () => {
      isMountedRef.current = false;
      if (resizeObserver) {
        resizeObserver.disconnect();
      }
      try {
        map.stop(); // Stop ongoing pan/zoom/PosAnimation before removal to prevent _leaflet_pos error
        map.closePopup();
        map.off();
        if (markersLayerRef.current) {
          markersLayerRef.current.clearLayers();
        }
        map.remove();
      } catch {
        // Safe fallback
      }
      mapInstanceRef.current = null;
      markersLayerRef.current = null;
      customerMarkerRef.current = null;
      radiusCircleRef.current = null;
    };
  }, [isMiniMap]); // Run once on mount

  // Update Customer Marker & Search Radius Circle
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMountedRef.current) return;

    if (customerMarkerRef.current) {
      customerMarkerRef.current.off();
      map.removeLayer(customerMarkerRef.current);
      customerMarkerRef.current = null;
    }
    if (radiusCircleRef.current) {
      map.removeLayer(radiusCircleRef.current);
      radiusCircleRef.current = null;
    }

    if (!customerLocation || !isValidLatLng(customerLocation.lat, customerLocation.lng)) return;

    // Custom HTML Pin for Customer with pulse effect
    const customerIcon = L.divIcon({
      className: 'custom-customer-pin',
      html: `
        <div class="relative flex items-center justify-center">
          <span class="animate-ping absolute inline-flex h-8 w-8 rounded-full bg-teal-400 opacity-75"></span>
          <div class="relative w-8 h-8 rounded-full bg-navy-900 border-2 border-white shadow-lg flex items-center justify-center text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
          <div class="absolute -bottom-1 w-2 h-2 bg-navy-900 rotate-45"></div>
        </div>
      `,
      iconSize: [32, 36],
      iconAnchor: [16, 36],
      popupAnchor: [0, -36],
    });

    const marker = L.marker([customerLocation.lat, customerLocation.lng], {
      icon: customerIcon,
      draggable: true,
      title: 'Lokasi Anda (Geser pin untuk ubah lokasi)',
    }).addTo(map);

    marker.bindPopup(`
      <div class="p-1 font-sans text-slate-800">
        <p class="font-bold text-navy-900 text-sm">📍 Lokasi Anda</p>
        <p class="text-xs text-slate-500 mt-0.5">Geser pin atau klik peta untuk ubah titik jemput.</p>
      </div>
    `);

    marker.on('dragend', (e) => {
      if (!isMountedRef.current) return;
      const targetMarker = e.target as L.Marker;
      const newPos = targetMarker.getLatLng();
      if (onLocationChangeRef.current && isValidLatLng(newPos.lat, newPos.lng)) {
        onLocationChangeRef.current(newPos.lat, newPos.lng);
      }
    });

    customerMarkerRef.current = marker;

    // Search Radius Visual Circle (up to 10km)
    if (searchRadiusKm && searchRadiusKm > 0) {
      const circle = L.circle([customerLocation.lat, customerLocation.lng], {
        radius: searchRadiusKm * 1000,
        color: '#14B8A6',
        weight: 1.5,
        opacity: 0.7,
        fillColor: '#14B8A6',
        fillOpacity: 0.06,
        dashArray: '4, 6',
      }).addTo(map);
      radiusCircleRef.current = circle;
    }
  }, [customerLocation, searchRadiusKm]);

  // Update Store Markers & Bounds
  useEffect(() => {
    const map = mapInstanceRef.current;
    const markersLayer = markersLayerRef.current;
    if (!map || !markersLayer || !isMountedRef.current) return;

    markersLayer.clearLayers();

    const bounds = L.latLngBounds([]);
    if (customerLocation && isValidLatLng(customerLocation.lat, customerLocation.lng)) {
      bounds.extend([customerLocation.lat, customerLocation.lng]);
    }

    stores.forEach((store) => {
      if (
        store.latitude === null ||
        store.longitude === null ||
        !isValidLatLng(store.latitude, store.longitude)
      ) {
        return;
      }

      const isSelected = selectedStoreId === store.id;
      const isServiceable = store.is_serviceable !== false;
      const latLng: [number, number] = [store.latitude, store.longitude];
      bounds.extend(latLng);

      // Icon colors and style based on state
      const bgColor = isSelected ? 'bg-navy-900' : isServiceable ? 'bg-teal-500' : 'bg-slate-400';
      const ringClass = isSelected ? 'ring-4 ring-teal-400 ring-offset-2 scale-110 z-50' : 'ring-2 ring-white';

      const storeIcon = L.divIcon({
        className: 'custom-store-pin',
        html: `
          <div class="relative flex items-center justify-center transition-transform hover:scale-125 cursor-pointer ${ringClass} rounded-full shadow-md ${bgColor} text-white w-9 h-9">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <div class="absolute -bottom-1 w-2 h-2 ${bgColor} rotate-45"></div>
          </div>
        `,
        iconSize: [36, 40],
        iconAnchor: [18, 40],
        popupAnchor: [0, -40],
      });

      const marker = L.marker(latLng, { icon: storeIcon }).addTo(markersLayer);

      const distanceBadge =
        store.distance_km !== undefined
          ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
              isServiceable ? 'bg-teal-100 text-teal-800' : 'bg-amber-100 text-amber-800'
            }">
              ${store.distance_km} km ${isServiceable ? '• Dalam Jangkauan' : '• Di Luar Jangkauan'}
            </span>`
          : '';

      const serviceRadiusText = `<span class="text-xs text-slate-500">Radius Layanan: ${store.service_radius_km} km</span>`;

      const popupContent = isMiniMap
        ? `
        <div class="p-1.5 min-w-[180px] max-w-[220px] font-sans">
          <h4 class="font-heading font-bold text-sm text-navy-900 leading-snug mb-1">${store.name}</h4>
          <p class="text-[11px] text-slate-600 mb-1.5 line-clamp-2">${store.address_text}</p>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-50 text-teal-700 border border-teal-200">
            Radius Layanan: ${store.service_radius_km} km
          </span>
        </div>
      `
        : `
        <div class="p-2 min-w-[200px] max-w-[260px] font-sans">
          <div class="flex items-start justify-between gap-1 mb-1">
            <h4 class="font-heading font-bold text-sm text-navy-900 leading-snug">${store.name}</h4>
          </div>
          <div class="mb-2">
            ${distanceBadge}
          </div>
          <p class="text-xs text-slate-600 mb-1 line-clamp-2">${store.address_text}</p>
          <div class="mb-3">${serviceRadiusText}</div>
          <a href="/stores/${store.id}" class="inline-flex items-center justify-center w-full px-3 py-1.5 text-xs font-semibold text-white bg-navy-900 hover:bg-navy-800 rounded-lg transition-colors shadow-sm">
            Lihat Detail Layanan &rarr;
          </a>
        </div>
      `;

      marker.bindPopup(popupContent, {
        autoPanPadding: [20, 20],
      });

      marker.on('click', () => {
        if (!isMountedRef.current) return;
        if (onStoreSelectRef.current) {
          onStoreSelectRef.current(store);
        }
      });

      if (isSelected && autoOpenPopup) {
        marker.openPopup();
      }
    });

    // Auto-fit bounds if we have valid points and map has non-zero size
    if (bounds.isValid() && (stores.length > 0 || customerLocation)) {
      try {
        const size = map.getSize();
        if (size.x > 0 && size.y > 0) {
          map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15, animate: false });
        }
      } catch {
        // Safeguard against unmounted access
      }
    }
  }, [stores, selectedStoreId, customerLocation, isMiniMap, autoOpenPopup]);

  return (
    <div className="relative w-full h-full">
      <div ref={mapContainerRef} className={className} style={{ minHeight: isMiniMap ? '180px' : '350px' }} />
      {/* Map legend overlay (rendered only if showLegendResolved is true) */}
      {showLegendResolved && (
        <div className="absolute bottom-3 left-3 z-[1000] bg-white/90 backdrop-blur-sm border border-slate-200/80 rounded-xl p-2.5 shadow-md text-xs space-y-1.5 pointer-events-auto">
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-full bg-navy-900 border border-white"></span>
            <span className="text-slate-700 font-medium">Titik Lokasi Anda</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-full bg-teal-500 border border-white"></span>
            <span className="text-slate-700 font-medium">Toko Dapat Melayani (&le; Radius Toko)</span>
          </div>
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-full bg-slate-400 border border-white"></span>
            <span className="text-slate-700 font-medium">Toko Di Luar Radius Layanan</span>
          </div>
        </div>
      )}
    </div>
  );
};

export default StoreMap;

