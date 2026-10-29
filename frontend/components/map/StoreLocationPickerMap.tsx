'use client';

import React, { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export interface StoreLocationPickerMapProps {
  latitude: number | null | undefined;
  longitude: number | null | undefined;
  onChange: (lat: number, lng: number) => void;
  serviceRadiusKm?: number;
  className?: string;
  defaultCenter?: [number, number];
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
    lng <= 180 &&
    !(lat === 0 && lng === 0)
  );
};

export const StoreLocationPickerMap: React.FC<StoreLocationPickerMapProps> = ({
  latitude,
  longitude,
  onChange,
  serviceRadiusKm,
  className = 'h-[360px] w-full rounded-xl overflow-hidden border border-slate-200 shadow-inner',
  defaultCenter = [-6.9147, 107.6098], // Default view center (Bandung center) if no coord yet
}) => {
  const mapContainerRef = useRef<HTMLDivElement | null>(null);
  const mapInstanceRef = useRef<L.Map | null>(null);
  const markerRef = useRef<L.Marker | null>(null);
  const circleRef = useRef<L.Circle | null>(null);
  const isMountedRef = useRef<boolean>(true);

  const onChangeRef = useRef(onChange);
  useEffect(() => {
    onChangeRef.current = onChange;
  }, [onChange]);

  const hasValidCoord = isValidLatLng(latitude, longitude);

  // Initialize Map
  useEffect(() => {
    isMountedRef.current = true;
    if (!mapContainerRef.current || mapInstanceRef.current) return;

    const initialCenter: [number, number] = hasValidCoord
      ? [latitude as number, longitude as number]
      : defaultCenter;

    const map = L.map(mapContainerRef.current, {
      center: initialCenter,
      zoom: hasValidCoord ? 15 : 13,
      zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
    }).addTo(map);

    mapInstanceRef.current = map;

    // Click map to place/move pin
    map.on('click', (e: L.LeafletMouseEvent) => {
      if (!isMountedRef.current) return;
      const { lat, lng } = e.latlng;
      if (isValidLatLng(lat, lng)) {
        onChangeRef.current(Number(lat.toFixed(6)), Number(lng.toFixed(6)));
      }
    });

    // ResizeObserver to handle tab changes or layout reflows
    let resizeObserver: ResizeObserver | null = null;
    if (typeof ResizeObserver !== 'undefined' && mapContainerRef.current) {
      resizeObserver = new ResizeObserver(() => {
        if (!isMountedRef.current || !mapInstanceRef.current) return;
        try {
          mapInstanceRef.current.invalidateSize({ pan: false });
        } catch {
          // ignore
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
        map.stop();
        map.off();
        map.remove();
      } catch {
        // safe cleanup
      }
      mapInstanceRef.current = null;
      markerRef.current = null;
      circleRef.current = null;
    };
  }, []);

  // Sync Marker & Radius with Latitude / Longitude Props
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMountedRef.current) return;

    if (!hasValidCoord) {
      if (markerRef.current) {
        map.removeLayer(markerRef.current);
        markerRef.current = null;
      }
      if (circleRef.current) {
        map.removeLayer(circleRef.current);
        circleRef.current = null;
      }
      return;
    }

    const lat = latitude as number;
    const lng = longitude as number;

    const pinIcon = L.divIcon({
      className: 'store-location-pin',
      html: `
        <div class="relative flex items-center justify-center -translate-x-1/2 -translate-y-full cursor-grab active:cursor-grabbing">
          <div class="relative flex items-center justify-center w-10 h-10 rounded-full bg-emerald-600 text-white shadow-lg ring-4 ring-white animate-bounce-subtle">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
          </div>
          <div class="absolute -bottom-1.5 w-3 h-3 bg-emerald-700 rotate-45"></div>
        </div>
      `,
      iconSize: [40, 48],
      iconAnchor: [20, 44],
    });

    if (!markerRef.current) {
      const marker = L.marker([lat, lng], {
        icon: pinIcon,
        draggable: true,
        autoPan: true,
      }).addTo(map);

      marker.bindPopup(`
        <div class="text-xs p-1">
          <p class="font-bold text-slate-800">Titik Lokasi Toko</p>
          <p class="text-slate-500 text-[11px] mt-0.5">Geser pin ini untuk menyesuaikan lokasi presisi.</p>
        </div>
      `);

      marker.on('dragend', (e) => {
        if (!isMountedRef.current) return;
        const targetMarker = e.target as L.Marker;
        const newPos = targetMarker.getLatLng();
        if (isValidLatLng(newPos.lat, newPos.lng)) {
          onChangeRef.current(Number(newPos.lat.toFixed(6)), Number(newPos.lng.toFixed(6)));
        }
      });

      markerRef.current = marker;
      map.setView([lat, lng], Math.max(map.getZoom(), 15));
    } else {
      const currentPos = markerRef.current.getLatLng();
      if (
        Math.abs(currentPos.lat - lat) > 0.00001 ||
        Math.abs(currentPos.lng - lng) > 0.00001
      ) {
        markerRef.current.setLatLng([lat, lng]);
        map.panTo([lat, lng], { animate: true, duration: 0.5 });
      }
    }

    // Service Radius Circle
    if (serviceRadiusKm && serviceRadiusKm > 0) {
      const radiusMeters = serviceRadiusKm * 1000;
      if (!circleRef.current) {
        circleRef.current = L.circle([lat, lng], {
          radius: radiusMeters,
          color: '#059669',
          weight: 1.5,
          opacity: 0.8,
          fillColor: '#10B981',
          fillOpacity: 0.1,
          dashArray: '4, 6',
        }).addTo(map);
      } else {
        circleRef.current.setLatLng([lat, lng]);
        circleRef.current.setRadius(radiusMeters);
      }
    } else if (circleRef.current) {
      map.removeLayer(circleRef.current);
      circleRef.current = null;
    }
  }, [latitude, longitude, hasValidCoord, serviceRadiusKm]);

  return <div ref={mapContainerRef} className={className} />;
};

export default StoreLocationPickerMap;
