'use client';

import React, { useState, useRef } from 'react';
import { Camera, Trash2, Loader2 } from 'lucide-react';
import { Avatar } from './Avatar';
import { AvatarCropperModal } from './AvatarCropperModal';
import { uploadProfilePhoto, deleteProfilePhoto } from '@/lib/profile';
import { updateStoredUser } from '@/lib/auth';
import { useToast } from './Toast';
import { cn } from '@/lib/format';

export interface AvatarUploaderProps {
  currentAvatarUrl?: string | null;
  name: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  onUploadSuccess?: (newAvatarUrl: string) => void;
  onDeleteSuccess?: () => void;
  className?: string;
  showActions?: boolean;
}

export const AvatarUploader: React.FC<AvatarUploaderProps> = ({
  currentAvatarUrl,
  name,
  size = 'xl',
  onUploadSuccess,
  onDeleteSuccess,
  className,
  showActions = true,
}) => {
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const { success: toastSuccess, error: toastError } = useToast();

  const [avatarUrl, setAvatarUrl] = useState<string | null>(currentAvatarUrl || null);
  const [selectedImageSrc, setSelectedImageSrc] = useState<string | null>(null);
  const [isCropperOpen, setIsCropperOpen] = useState<boolean>(false);
  const [isUploading, setIsUploading] = useState<boolean>(false);
  const [isDeleting, setIsDeleting] = useState<boolean>(false);

  // Sync state if prop changes
  React.useEffect(() => {
    setAvatarUrl(currentAvatarUrl || null);
  }, [currentAvatarUrl]);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files || e.target.files.length === 0) return;
    const file = e.target.files[0];

    // Reset input value so same file can be re-selected if needed
    e.target.value = '';

    // Validate type
    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!validTypes.includes(file.type)) {
      toastError('Format Tidak Didukung', 'Hanya gambar JPEG, PNG, atau WebP yang diperbolehkan.');
      return;
    }

    // Validate max size 2MB before cropping
    if (file.size > 2 * 1024 * 1024) {
      toastError('File Terlalu Besar', 'Ukuran foto maksimal 2 MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      setSelectedImageSrc(reader.result as string);
      setIsCropperOpen(true);
    };
    reader.onerror = () => {
      toastError('Gagal Membaca File', 'Tidak dapat membuka file gambar yang dipilih.');
    };
    reader.readAsDataURL(file);
  };

  const handleCropComplete = async (croppedFile: File) => {
    try {
      setIsUploading(true);
      const res = await uploadProfilePhoto(croppedFile);
      const newUrl = res.avatar_url || res.photo_url;
      setAvatarUrl(newUrl);

      // Update global stored user & notify all navbar/header components
      updateStoredUser({ avatar_url: newUrl });

      if (onUploadSuccess) {
        onUploadSuccess(newUrl);
      }
      toastSuccess('Foto Diperbarui', 'Foto profil berhasil disimpan dan diperbarui.');
    } catch (err: unknown) {
      toastError('Gagal Mengunggah', err instanceof Error ? err.message : 'Terjadi kesalahan saat mengunggah foto.');
    } finally {
      setIsUploading(false);
      setSelectedImageSrc(null);
    }
  };

  const handleDeletePhoto = async () => {
    if (isDeleting || isUploading) return;

    try {
      setIsDeleting(true);
      await deleteProfilePhoto();
      setAvatarUrl(null);

      // Update global stored user & notify navbar/header
      updateStoredUser({ avatar_url: null });

      if (onDeleteSuccess) {
        onDeleteSuccess();
      }
      toastSuccess('Foto Dihapus', 'Foto profil telah dihapus. Menggunakan inisial nama.');
    } catch (err: unknown) {
      toastError('Gagal Menghapus', err instanceof Error ? err.message : 'Terjadi kesalahan saat menghapus foto.');
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <div className={cn('flex flex-col items-center gap-3', className)}>
      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={handleFileSelect}
        className="hidden"
        aria-hidden="true"
      />

      {/* Avatar Container with hover trigger */}
      <div className="relative group">
        <div className="relative rounded-full ring-4 ring-slate-100/80 p-0.5 shadow-xs transition-transform group-hover:scale-102">
          <Avatar src={avatarUrl} name={name} size={size} />

          {/* Uploading overlay spinner */}
          {(isUploading || isDeleting) && (
            <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs rounded-full flex items-center justify-center text-white">
              <Loader2 className="w-5 h-5 animate-spin" />
            </div>
          )}
        </div>

        {/* Floating Quick Camera Button */}
        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          disabled={isUploading || isDeleting}
          className="absolute bottom-0 right-0 p-2 bg-[#1A3A5C] text-white rounded-full shadow-md hover:bg-[#132d47] transition-colors cursor-pointer ring-2 ring-white"
          aria-label="Pilih foto profil baru"
          title="Pilih foto profil"
        >
          <Camera className="w-3.5 h-3.5" strokeWidth={1.5} />
        </button>
      </div>

      {/* Actions */}
      {showActions && (
        <div className="flex items-center gap-2 mt-1">
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            disabled={isUploading || isDeleting}
            className="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition-colors cursor-pointer shadow-2xs"
          >
            {avatarUrl ? 'Ganti Foto' : 'Unggah Foto'}
          </button>

          {avatarUrl && (
            <button
              type="button"
              onClick={handleDeletePhoto}
              disabled={isUploading || isDeleting}
              className="px-3 py-1.5 text-xs font-medium text-rose-600 bg-white border border-slate-200 rounded-md hover:bg-rose-50 hover:border-rose-200 transition-colors cursor-pointer shadow-2xs flex items-center gap-1"
            >
              <Trash2 className="w-3 h-3" strokeWidth={1.5} />
              <span>Hapus</span>
            </button>
          )}
        </div>
      )}

      {/* Cropper Modal */}
      <AvatarCropperModal
        isOpen={isCropperOpen}
        imageSrc={selectedImageSrc}
        onClose={() => {
          setIsCropperOpen(false);
          setSelectedImageSrc(null);
        }}
        onCropComplete={handleCropComplete}
      />
    </div>
  );
};

export default AvatarUploader;
