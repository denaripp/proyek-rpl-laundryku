'use client';

import React, { useState } from 'react';
import { Card, Badge, Button, Modal } from '@/components/ui';
import {
  Complaint,
  COMPLAINT_CATEGORY_LABELS,
  COMPLAINT_STATUS_LABELS,
} from '@/lib/complaint';
import { formatDate } from '@/lib/format';
import {
  AlertCircle,
  Clock,
  CheckCircle2,
  XCircle,
  Image as ImageIcon,
  MessageSquare,
  ShieldCheck,
  PlusCircle,
} from 'lucide-react';

interface ComplaintListCardProps {
  complaints: Complaint[];
  onOpenComplaintModal: () => void;
  canFileComplaint: boolean;
}

export const ComplaintListCard: React.FC<ComplaintListCardProps> = ({
  complaints,
  onOpenComplaintModal,
  canFileComplaint,
}) => {
  const [selectedPhoto, setSelectedPhoto] = useState<string | null>(null);

  return (
    <Card variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
          <h3 className="font-heading font-bold text-base text-navy-900 flex items-center gap-2">
            <AlertCircle className="w-4 h-4 text-amber-500" />
            <span>Pusat Komplain & Kendala</span>
          </h3>
          <p className="text-xs text-slate-500">
            Laporkan masalah pakaian, keterlambatan, atau kendala layanan langsung ke toko.
          </p>
        </div>

        {canFileComplaint && (
          <Button
            variant="outline"
            size="sm"
            onClick={onOpenComplaintModal}
            className="rounded-xl text-xs border-amber-300 text-navy-900 hover:bg-amber-50 font-semibold min-h-[44px]"
          >
            <PlusCircle className="w-4 h-4 mr-1.5 text-amber-500" />
            Ajukan Komplain
          </Button>
        )}
      </div>

      {complaints.length === 0 ? (
        <div className="py-6 text-center text-xs text-slate-400">
          <p>Tidak ada komplain aktif untuk pesanan ini.</p>
          {canFileComplaint && (
            <p className="text-[11px] text-slate-400 mt-1">
              Jika terdapat kendala pada pakaian atau layanan, klik tombol <b>Ajukan Komplain</b> di atas.
            </p>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          {complaints.map((item) => {
            const statusMeta = COMPLAINT_STATUS_LABELS[item.status] || {
              label: item.status,
              variant: 'warning' as const,
            };
            const categoryLabel = COMPLAINT_CATEGORY_LABELS[item.category] || item.category;

            return (
              <div
                key={item.id}
                className="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3"
              >
                {/* Header item */}
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <Badge variant={statusMeta.variant} className="text-xs font-semibold px-2.5 py-0.5 rounded-full">
                      {statusMeta.label}
                    </Badge>
                    <span className="font-bold text-xs text-navy-900">
                      {categoryLabel}
                    </span>
                  </div>
                  <span className="text-[11px] text-slate-400 tabular-nums">
                    {formatDate(item.created_at)}
                  </span>
                </div>

                {/* Complaint Description */}
                <p className="text-xs text-slate-700 leading-relaxed whitespace-pre-line bg-white p-3 rounded-xl border border-slate-100">
                  {item.description}
                </p>

                {/* Attachments */}
                {item.attachments && item.attachments.length > 0 && (
                  <div className="space-y-1.5">
                    <span className="text-[11px] font-semibold text-slate-500 block">
                      Foto Bukti ({item.attachments.length}):
                    </span>
                    <div className="flex flex-wrap gap-2">
                      {item.attachments.map((att) => (
                        <button
                          key={att.id}
                          type="button"
                          onClick={() => setSelectedPhoto(att.url)}
                          className="w-16 h-16 rounded-xl overflow-hidden border border-slate-200 bg-white hover:opacity-80 transition-opacity focus:outline-none focus:ring-2 focus:ring-teal-400"
                        >
                          <img
                            src={att.url}
                            alt={att.file_name || 'Bukti'}
                            className="w-full h-full object-cover"
                          />
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* Resolution Note (If RESOLVED or REJECTED) */}
                {item.resolution_note && (
                  <div className="p-3 bg-teal-50/80 rounded-xl border border-teal-200 text-xs space-y-1">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-teal-900 flex items-center gap-1.5">
                        <CheckCircle2 className="w-4 h-4 text-teal-600" />
                        Catatan Penyelesaian Toko:
                      </span>
                      {item.resolved_at && (
                        <span className="text-[10px] text-teal-700 tabular-nums">
                          {formatDate(item.resolved_at)}
                        </span>
                      )}
                    </div>
                    <p className="text-teal-950 font-medium whitespace-pre-line pl-5">
                      {item.resolution_note}
                    </p>
                    {item.resolver_name && (
                      <span className="text-[10px] text-teal-700 block pl-5">
                        Ditangani oleh: {item.resolver_name}
                      </span>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Photo Preview Modal */}
      <Modal
        isOpen={selectedPhoto !== null}
        onClose={() => setSelectedPhoto(null)}
        title="Lihat Foto Bukti"
        maxWidth="xl"
      >

        <div className="flex flex-col items-center">
          {selectedPhoto && (
            <img
              src={selectedPhoto}
              alt="Foto Bukti"
              className="max-h-[70vh] w-auto rounded-2xl object-contain border border-slate-200"
            />
          )}
          <div className="pt-4 flex justify-end w-full">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setSelectedPhoto(null)}
              className="rounded-xl text-xs min-h-[44px]"
            >
              Tutup
            </Button>
          </div>
        </div>
      </Modal>
    </Card>
  );
};
