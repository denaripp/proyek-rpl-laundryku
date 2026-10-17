'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AuditLogItem } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { Search, Eye, X } from 'lucide-react';

export default function AdminAuditLogsPage() {
  const [logs, setLogs] = useState<AuditLogItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [actionFilter, setActionFilter] = useState<string>('');
  const [search, setSearch] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const [total, setTotal] = useState<number>(0);
  const [lastPage, setLastPage] = useState<number>(1);

  const [selectedLog, setSelectedLog] = useState<AuditLogItem | null>(null);
  const [detailModalOpen, setDetailModalOpen] = useState<boolean>(false);

  const fetchLogs = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const res = await adminApi.getAuditLogs({
        action: actionFilter || undefined,
        q: search || undefined,
        page,
      });
      setLogs(res.data || []);
      if (res.meta) {
        setTotal(res.meta.total || 0);
        setLastPage(res.meta.last_page || 1);
      }
    } catch {
      setLogs([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [actionFilter, search, page]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  const columns: Column<AuditLogItem>[] = [
    {
      header: 'Waktu (WIB)',
      accessor: (item) => (
        <span className="font-mono text-slate-500">
          {new Date(item.created_at).toLocaleString('id-ID', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
          })}
        </span>
      ),
    },
    {
      header: 'Aksi (Event)',
      accessor: (item) => (
        <span className="font-mono text-xs font-semibold text-slate-900">
          {item.action}
        </span>
      ),
    },
    {
      header: 'Entitas Target',
      accessor: (item) => (
        <span className="text-slate-700">
          {item.entity_type} {item.entity_id ? `(${item.entity_id.slice(0, 8)}...)` : ''}
        </span>
      ),
    },
    {
      header: 'Aktor Eksekutor',
      accessor: (item) => (
        <div>
          <div className="font-medium text-slate-800">{item.actor?.name || 'Sistem / Anonim'}</div>
          <div className="text-[11px] text-slate-500">{item.actor?.email || ''}</div>
        </div>
      ),
    },
    {
      header: 'IP Klien',
      accessor: (item) => (
        <span className="font-mono text-[11px] text-slate-400">
          {item.ip_hash || '-'}
        </span>
      ),
    },
    {
      header: 'Detail',
      className: 'text-right',
      accessor: (item) => (
        <button
          onClick={() => {
            setSelectedLog(item);
            setDetailModalOpen(true);
          }}
          className="p-1 rounded text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors cursor-pointer"
          title="Lihat Metadata Payload"
          aria-label="Lihat metadata audit log"
        >
          <Eye className="w-4 h-4" strokeWidth={1.5} />
        </button>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <AdminHeader
        title="Audit Log Sistem"
        subtitle="Jejak audit permanen (immutable) seluruh peristiwa otentikasi, pesanan, pembayaran, dan administrasi"
        onRefresh={() => fetchLogs(true)}
        refreshing={refreshing}
      />

      {/* Filter toolbar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200 rounded-lg p-3">
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" strokeWidth={1.5} />
            <input
              type="text"
              placeholder="Cari aksi, entitas ID, nama aktor..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
            />
          </div>

          <select
            value={actionFilter}
            onChange={(e) => {
              setActionFilter(e.target.value);
              setPage(1);
            }}
            className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
          >
            <option value="">Semua Tipe Aksi</option>
            <option value="ADMIN_LOGIN_SUCCESS">ADMIN_LOGIN_SUCCESS</option>
            <option value="ADMIN_LOGIN_FAILED">ADMIN_LOGIN_FAILED</option>
            <option value="ADMIN_LOGOUT_SUCCESS">ADMIN_LOGOUT_SUCCESS</option>
            <option value="ORDER_CREATED">ORDER_CREATED</option>
            <option value="ORDER_TRANSITION_PAID">ORDER_TRANSITION_PAID</option>
            <option value="ORDER_TRANSITION_COMPLETED">ORDER_TRANSITION_COMPLETED</option>
            <option value="ORDER_TRANSITION_CANCELLED">ORDER_TRANSITION_CANCELLED</option>
            <option value="ORDER_TRANSITION_PAYMENT_EXPIRED">ORDER_TRANSITION_PAYMENT_EXPIRED</option>
            <option value="PAYMENT_INITIATED_VA">PAYMENT_INITIATED_VA</option>
            <option value="PAYMENT_INITIATED_QRIS">PAYMENT_INITIATED_QRIS</option>
            <option value="PAYMENT_INITIATED_COD">PAYMENT_INITIATED_COD</option>
            <option value="PAYMENT_PAID_WEBHOOK">PAYMENT_PAID_WEBHOOK</option>
            <option value="APPROVE_STORE">APPROVE_STORE</option>
            <option value="REJECT_STORE">REJECT_STORE</option>
            <option value="UPDATE_USER_STATUS">UPDATE_USER_STATUS</option>
            <option value="UPDATE_COMMISSION">UPDATE_COMMISSION</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <DataTable
        columns={columns}
        data={logs}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Belum ada catatan aktivitas sistem."
        pagination={{
          currentPage: page,
          lastPage: lastPage,
          total: total,
          onPageChange: (p) => setPage(p),
        }}
      />

      {/* Detail JSON Modal */}
      {detailModalOpen && selectedLog && (
        <div className="fixed inset-0 bg-slate-950/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg border border-slate-200 max-w-lg w-full p-5 space-y-4 shadow-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">Detail Rekaman Audit Log</h3>
                <p className="font-mono text-xs text-slate-500">{selectedLog.id}</p>
              </div>
              <button
                onClick={() => setDetailModalOpen(false)}
                className="p-1 rounded text-slate-400 hover:text-slate-700"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-2 text-xs">
              <div>
                <span className="text-slate-500">Aksi:</span>
                <div className="font-mono font-semibold text-slate-900">{selectedLog.action}</div>
              </div>
              <div>
                <span className="text-slate-500">Entitas:</span>
                <div className="font-mono text-slate-800">{selectedLog.entity_type} ({selectedLog.entity_id || '-'})</div>
              </div>
              <div>
                <span className="text-slate-500">Aktor:</span>
                <div className="font-medium text-slate-800">{selectedLog.actor?.name || 'Sistem'}</div>
              </div>
              <div>
                <span className="text-slate-500">IP Hash:</span>
                <div className="font-mono text-slate-800">{selectedLog.ip_hash || '-'}</div>
              </div>
            </div>

            <div>
              <span className="text-xs font-semibold text-slate-700 block mb-1">Payload Metadata:</span>
              <pre className="p-3 bg-slate-900 text-slate-100 rounded-md text-[11px] font-mono overflow-x-auto max-h-60 leading-relaxed">
                {JSON.stringify(selectedLog.metadata, null, 2)}
              </pre>
            </div>

            <div className="pt-3 border-t border-slate-200 flex justify-end">
              <button
                onClick={() => setDetailModalOpen(false)}
                className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-md transition-colors cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
