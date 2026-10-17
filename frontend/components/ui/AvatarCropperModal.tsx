'use client';

import React, { useState, useCallback, useEffect } from 'react';
import Cropper, { Area, Point } from 'react-easy-crop';
import { RotateCw, ZoomIn, ZoomOut, X, Check, Loader2 } from 'lucide-react';
import { Button } from './Button';

export interface AvatarCropperModalProps {
  isOpen: boolean;
  imageSrc: string | null;
  onClose: () => void;
  onCropComplete: (croppedFile: File) => Promise<void> | void;
}

// Helper to create HTMLImageElement
function createImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.addEventListener('load', () => resolve(image));
    image.addEventListener('error', (error) => reject(error));
    image.setAttribute('crossOrigin', 'anonymous');
    image.src = url;
  });
}

// Helper to crop canvas and output 512x512 JPEG File
async function getCroppedImg(
  imageSrc: string,
  pixelCrop: Area,
  rotation = 0,
  outputSize = 512
): Promise<File> {
  const image = await createImage(imageSrc);
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  if (!ctx) {
    throw new Error('Canvas 2D context tidak tersedia');
  }

  const rotRad = (rotation * Math.PI) / 180;

  // Calculate bounding box of rotated image
  const { width: bBoxWidth, height: bBoxHeight } = {
    width: Math.abs(Math.cos(rotRad) * image.width) + Math.abs(Math.sin(rotRad) * image.height),
    height: Math.abs(Math.sin(rotRad) * image.width) + Math.abs(Math.cos(rotRad) * image.height),
  };

  const rotCanvas = document.createElement('canvas');
  rotCanvas.width = bBoxWidth;
  rotCanvas.height = bBoxHeight;
  const rotCtx = rotCanvas.getContext('2d');

  if (!rotCtx) {
    throw new Error('Canvas 2D context tidak tersedia');
  }

  // Draw rotated source to intermediary canvas
  rotCtx.translate(bBoxWidth / 2, bBoxHeight / 2);
  rotCtx.rotate(rotRad);
  rotCtx.translate(-image.width / 2, -image.height / 2);
  rotCtx.drawImage(image, 0, 0);

  // Set output dimensions to 512x512
  canvas.width = outputSize;
  canvas.height = outputSize;

  // Fill white background for JPEG conversion
  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, outputSize, outputSize);

  // Draw cropped slice into final output canvas
  ctx.drawImage(
    rotCanvas,
    pixelCrop.x,
    pixelCrop.y,
    pixelCrop.width,
    pixelCrop.height,
    0,
    0,
    outputSize,
    outputSize
  );

  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error('Gagal memproses gambar crop'));
          return;
        }
        const file = new File([blob], 'avatar_cropped.jpg', { type: 'image/jpeg' });
        resolve(file);
      },
      'image/jpeg',
      0.92
    );
  });
}

export const AvatarCropperModal: React.FC<AvatarCropperModalProps> = ({
  isOpen,
  imageSrc,
  onClose,
  onCropComplete,
}) => {
  const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
  const [zoom, setZoom] = useState<number>(1);
  const [rotation, setRotation] = useState<number>(0);
  const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);
  const [isProcessing, setIsProcessing] = useState<boolean>(false);

  // Reset controls on open
  useEffect(() => {
    if (isOpen) {
      setCrop({ x: 0, y: 0 });
      setZoom(1);
      setRotation(0);
      setIsProcessing(false);
    }
  }, [isOpen, imageSrc]);

  // Handle ESC key to close
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen && !isProcessing) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isProcessing, onClose]);

  const onCropChange = (newCrop: Point) => {
    setCrop(newCrop);
  };

  const onCropCompleteHandler = useCallback((_croppedArea: Area, croppedAreaPixels: Area) => {
    setCroppedAreaPixels(croppedAreaPixels);
  }, []);

  const handleRotate = () => {
    setRotation((prev) => (prev + 90) % 360);
  };

  const handleSave = async () => {
    if (!imageSrc || !croppedAreaPixels) return;

    try {
      setIsProcessing(true);
      const croppedFile = await getCroppedImg(imageSrc, croppedAreaPixels, rotation, 512);
      await onCropComplete(croppedFile);
      onClose();
    } catch (err) {
      console.error('Failed to crop image', err);
    } finally {
      setIsProcessing(false);
    }
  };

  if (!isOpen || !imageSrc) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs animate-fadeIn">
      <div
        className="w-full max-w-md bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cropper-title"
      >
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
          <div>
            <h2 id="cropper-title" className="text-sm font-semibold text-slate-900 tracking-tight">
              Sesuaikan Foto Profil
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">
              Geser dan perbesar untuk mengatur posisi foto.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={isProcessing}
            aria-label="Tutup dialog"
            className="p-1 text-slate-400 hover:text-slate-700 rounded-md transition-colors cursor-pointer"
          >
            <X className="w-4 h-4" strokeWidth={1.5} />
          </button>
        </div>

        {/* Crop Area */}
        <div className="relative w-full h-72 bg-slate-900 select-none">
          <Cropper
            image={imageSrc}
            crop={crop}
            zoom={zoom}
            rotation={rotation}
            aspect={1}
            cropShape="round"
            showGrid={false}
            onCropChange={onCropChange}
            onCropComplete={onCropCompleteHandler}
            onZoomChange={setZoom}
            classes={{
              containerClassName: 'relative w-full h-full',
              cropAreaClassName: 'border-2 border-white/80 shadow-lg',
            }}
          />
        </div>

        {/* Controls */}
        <div className="p-4 bg-slate-50/80 border-t border-slate-100 space-y-4">
          {/* Zoom & Rotate row */}
          <div className="flex items-center gap-3">
            <ZoomOut className="w-4 h-4 text-slate-400 shrink-0" strokeWidth={1.5} />
            <input
              type="range"
              min={1}
              max={3}
              step={0.05}
              value={zoom}
              aria-label="Perbesar gambar"
              onChange={(e) => setZoom(parseFloat(e.target.value))}
              className="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[#1A3A5C]"
            />
            <ZoomIn className="w-4 h-4 text-slate-400 shrink-0" strokeWidth={1.5} />

            <button
              type="button"
              onClick={handleRotate}
              disabled={isProcessing}
              title="Putar 90 derajat"
              aria-label="Putar foto 90 derajat"
              className="p-2 border border-slate-200 rounded-md bg-white text-slate-700 hover:bg-slate-100 transition-colors shrink-0 cursor-pointer"
            >
              <RotateCw className="w-4 h-4" strokeWidth={1.5} />
            </button>
          </div>

          <p className="text-[11px] text-slate-500 text-center">
            Gunakan mouse drag atau scroll pada desktop, atau cubit di layar sentuh.
          </p>
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-end gap-2.5 px-5 py-3.5 bg-white border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onClose}
            disabled={isProcessing}
          >
            Batal
          </Button>

          <Button
            type="button"
            variant="primary"
            size="sm"
            onClick={handleSave}
            disabled={isProcessing}
            className="bg-[#1A3A5C] text-white hover:bg-[#132d47]"
          >
            {isProcessing ? (
              <>
                <Loader2 className="w-3.5 h-3.5 animate-spin mr-1.5" />
                <span>Menyimpan...</span>
              </>
            ) : (
              <>
                <Check className="w-3.5 h-3.5 mr-1.5" />
                <span>Simpan Foto</span>
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default AvatarCropperModal;
