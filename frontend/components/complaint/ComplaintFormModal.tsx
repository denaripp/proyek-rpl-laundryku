'use client';

import React, { useState } from 'react';
import { Modal, Button, Select, Textarea, Card } from '@/components/ui';

import { createComplaint, COMPLAINT_CATEGORY_LABELS, Complaint } from '@/lib/complaint';
import { cn } from '@/lib/format';
import { AlertCircle, UploadCloud, X, Image as ImageIcon } from 'lucide-react';

interface ComplaintFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  orderId: string;
  orderNumber: string;
  onSuccess: (complaint: Complaint) => void;
}

export const ComplaintFormModal: React.FC<ComplaintFormModalProps> = ({
  isOpen,
  onClose,
  orderId,
  orderNumber,
  onSuccess,
}) => {
  const [category, setCategory] = useState<string>('DAMAGED_ITEM');
  const [description, setDescription] = useState<string>('');
  const [photos, setPhotos] = useState<File[]>([]);
  const [photoPreviews, setPhotoPreviews] = useState<string[]>([]);
  const [loading, setLoading] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const categoryOptions = Object.entries(COMPLAINT_CATEGORY_LABELS).map(([value, label]) => ({
    value,
    label,
  }));

  const handlePhotoSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files) return;
    const files = Array.from(e.target.files);

    if (photos.length + files.length > 5) {
      setErrorMessage('Maksimal 5 foto bukti yang dapat dilampirkan.');
      return;
    }

    // Check size <= 5MB each
    for (const file of files) {
      if (file.size > 5 * 1024 * 1024) {
        setErrorMessage(`Ukuran file "${file.name}" melebihi batas maksimal 5 MB.`);
        return;
      }
    }

    setErrorMessage(null);
    const newPhotos = [...photos, ...files];
    setPhotos(newPhotos);

    // Generate previews
    const newPreviews = files.map((file) => URL.createObjectURL(file));
    setPhotoPreviews((prev) => [...prev, ...newPreviews]);
  };

  const handleRemovePhoto = (index: number) => {
    const newPhotos = photos.filter((_, i) => i !== index);
    const newPreviews = photoPreviews.filter((_, i) => i !== index);
    setPhotos(newPhotos);
    setPhotoPreviews(newPreviews);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!category) {
      setErrorMessage('Pilih kategori keluhan.');
      return;
    }
    if (description.trim().length < 10) {
      setErrorMessage('Deskripsi keluhan minimal 10 karakter agar admin toko dapat mengidentifikasi masalah.');
      return;
    }

    setLoading(true);
    setErrorMessage(null);

    try {
      const res = await createComplaint(orderId, category, description, photos);
      onSuccess(res.data);
      onClose();
      // Reset form
      setDescription('');
      setCategory('DAMAGED_ITEM');
      setPhotos([]);
      setPhotoPreviews([]);
    } catch (err: unknown) {
      const errObj = err as { message?: string };
      setErrorMessage(errObj?.message || 'Gagal mengajukan komplain. Silakan coba lagi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Ajukan Komplain Pesanan"
      maxWidth="lg"
    >

      <form onSubmit={handleSubmit} className="space-y-4 text-xs">
        <div className="p-3 bg-sky-50 rounded-2xl border border-sky-200">
          <span className="text-slate-500 block text-[11px]">Nomor Pesanan:</span>
          <span className="font-heading font-extrabold text-sm text-navy-900">{orderNumber}</span>
        </div>

        {errorMessage && (
          <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-start gap-2">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Category */}
        <div className="space-y-1.5">
          <label className="font-bold text-slate-700 block">
            Kategori Kendala <span className="text-red-500">*</span>
          </label>
          <Select
            options={categoryOptions}
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="w-full text-xs min-h-[44px]"
          />
        </div>

        {/* Description */}
        <div className="space-y-1.5">
          <label className="font-bold text-slate-700 block">
            Rincian & Deskripsi Keluhan <span className="text-red-500">*</span>
          </label>
          <Textarea
            rows={4}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Jelaskan secara detail kendala yang dialami (misal: warna pakaian luntur, kancing kemeja hilang, atau bau apek)..."
            className="text-xs"
            required
          />
          <div className="flex justify-between text-[11px] text-slate-400">
            <span>Minimal 10 karakter</span>
            <span className={cn(description.length < 10 ? 'text-amber-500 font-semibold' : 'text-slate-400')}>
              {description.length} / 2000
            </span>
          </div>
        </div>

        {/* Photo Upload */}
        <div className="space-y-2">
          <label className="font-bold text-slate-700 flex items-center justify-between">
            <span>Lampiran Foto Bukti (Opsional, Maks. 5 Foto)</span>
            <span className="text-[11px] text-slate-400 font-normal">{photos.length}/5</span>
          </label>

          {photoPreviews.length > 0 && (
            <div className="grid grid-cols-5 gap-2 pt-1">
              {photoPreviews.map((preview, index) => (
                <div key={index} className="relative group rounded-xl overflow-hidden aspect-square border border-slate-200 bg-slate-100">
                  <img src={preview} alt={`Bukti ${index + 1}`} className="w-full h-full object-cover" />
                  <button
                    type="button"
                    onClick={() => handleRemovePhoto(index)}
                    className="absolute top-1 right-1 p-1 bg-navy-900/80 hover:bg-red-600 text-white rounded-full transition-colors"
                    aria-label="Hapus foto"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </div>
              ))}
            </div>
          )}

          {photos.length < 5 && (
            <label className="flex flex-col items-center justify-center p-4 border-2 border-dashed border-slate-200 hover:border-teal-500 rounded-2xl cursor-pointer bg-slate-50/60 hover:bg-teal-50/40 transition-colors min-h-[44px]">
              <UploadCloud className="w-6 h-6 text-teal-600 mb-1" />
              <span className="font-semibold text-slate-700 text-xs">Pilih atau Seret Foto</span>
              <span className="text-[10px] text-slate-400">PNG, JPG, WebP hingga 5 MB</span>
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                onChange={handlePhotoSelect}
                className="hidden"
              />
            </label>
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            disabled={loading}
            className="rounded-xl text-xs min-h-[44px]"
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="primary"
            isLoading={loading}
            disabled={loading || description.trim().length < 10}
            className="rounded-xl text-xs min-h-[44px] bg-navy-900 hover:bg-navy-800"
          >
            Kirim Komplain
          </Button>
        </div>
      </form>
    </Modal>
  );
};
