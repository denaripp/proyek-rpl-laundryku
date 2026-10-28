'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AuditLogItem } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { Search, Eye, X, ScrollText, User, Server } from 'lucide-react';

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
        <span className="font-mono text-xs text-slate-500">
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
      header: 'Aksi (event)',
      accessor: (item) => (
        <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-navy-50 text-navy-800 border border-navy-100">
          {item.action}
        </span>
      ),
    },
    {
      header: 'Entitas target',
      accessor: (item) => (
        <span className="text-slate-700 font-medium">
          {item.entity_type} {item.entity_id ? `(${item.entity_id.slice(0, 8)}...)` : ''}
        </span>
      ),
    },
    {
      header: 'Aktor eksekutor',
      accessor: (item) => (
        <div>
          <div className="font-semibold text-slate-800">{item.actor?.name || 'Sistem / Anonim'}</div>
          <div className="text-[11px] text-slate-500 font-mono">{item.actor?.email || ''}</div>
        </div>
      ),
    },
    {
      header: 'IP klien',
      accessor: (item) => (
        <span className="font-mono text-[11px] text-slate-500">
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
          className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-700 bg-slate-100 hover:bg-navy-50 hover:text-navy-700 transition-colors cursor-pointer"
          title="Lihat metadata payload"
          aria-label="Lihat metadata audit log"
        >
          <Eye className="w-3.5 h-3.5" />
          <span>Lihat</span>
        </button>
      ),
    },
  ];

  return (
    <div className="space-y-5">
      <AdminHeader
        title="Audit log sistem"
        subtitle="Jejak audit permanen (immutable) seluruh peristiwa otentikasi, pesanan, pembayaran, dan administrasi"
        onRefresh={() => fetchLogs(true)}
        refreshing={refreshing}
      />

      {/* Filter toolbar */}
      <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white border border-slate-200/80 rounded-2xl p-3.5 shadow-2xs">
        <div className="flex flex-col sm:flex-row items-center gap-2.5 w-full sm:w-auto">
          <div className="relative w-full sm:w-72">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Cari aksi, entitas ID, nama aktor..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 transition-all font-medium"
            />
          </div>

          <select
            value={actionFilter}
            onChange={(e) => {
              setActionFilter(e.target.value);
              setPage(1);
            }}
            className="w-full sm:w-auto py-2 px-3 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-700 font-medium focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 transition-all cursor-pointer"
          >
            <option value="">Semua tipe aksi</option>
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
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200/90 max-w-lg w-full p-6 space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-start justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-navy-50 text-navy-700 flex items-center justify-center">
                  <ScrollText className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-base font-semibold text-slate-900">Detail rekaman audit log</h3>
                  <p className="font-mono text-xs text-slate-500 font-medium">{selectedLog.id}</p>
                </div>
              </div>
              <button
                onClick={() => setDetailModalOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <span className="text-slate-500 block mb-1">Aksi (event)</span>
                <div className="font-mono font-semibold text-slate-900">{selectedLog.action}</div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <span className="text-slate-500 block mb-1">Entitas target</span>
                <div className="font-mono text-slate-800 font-semibold">{selectedLog.entity_type} ({selectedLog.entity_id || '-'})</div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <div className="flex items-center gap-1.5 text-slate-500 mb-1">
                  <User className="w-3.5 h-3.5" />
                  <span>Aktor eksekutor</span>
                </div>
                <div className="font-semibold text-slate-800">{selectedLog.actor?.name || 'Sistem'}</div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <div className="flex items-center gap-1.5 text-slate-500 mb-1">
                  <Server className="w-3.5 h-3.5" />
                  <span>IP hash</span>
                </div>
                <div className="font-mono text-slate-800 font-semibold">{selectedLog.ip_hash || '-'}</div>
              </div>
            </div>

            <div>
              <span className="text-xs font-semibold text-slate-700 block mb-2">Payload metadata</span>
              <pre className="p-3.5 bg-slate-900 text-slate-100 rounded-xl text-[11px] font-mono overflow-x-auto max-h-60 leading-relaxed border border-slate-800">
                {JSON.stringify(selectedLog.metadata, null, 2)}
              </pre>
            </div>

            <div className="pt-3 border-t border-slate-100 flex justify-end">
              <button
                onClick={() => setDetailModalOpen(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors cursor-pointer"
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
