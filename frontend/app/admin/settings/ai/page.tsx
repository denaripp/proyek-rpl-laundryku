'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AiSettingsData, AiTestResult } from '@/lib/admin';
import { AdminHeader } from '@/components/admin/AdminHeader';
import {
  Cpu,
  SlidersHorizontal,
  Activity,
  Check,
  CheckCircle2,
  AlertCircle,
  X,
  Server,
  Info,
  Copy,
  ArrowRight,
} from 'lucide-react';

export default function AdminAiSettingsPage() {
  const [data, setData] = useState<AiSettingsData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);

  // Form states
  const [selectedModel, setSelectedModel] = useState<string>('google/gemini-2.5-flash');
  const [isCustomMode, setIsCustomMode] = useState<boolean>(false);
  const [customModelInput, setCustomModelInput] = useState<string>('');
  const [temperature, setTemperature] = useState<number>(0.2);
  const [maxToolRounds, setMaxToolRounds] = useState<number>(5);
  const [categoryFilter, setCategoryFilter] = useState<'all' | 'free' | 'paid'>('all');

  // Actions
  const [testLoading, setTestLoading] = useState<boolean>(false);
  const [testResult, setTestResult] = useState<AiTestResult | null>(null);
  const [copiedReply, setCopiedReply] = useState<boolean>(false);
  const [saveLoading, setSaveLoading] = useState<boolean>(false);
  const [showConfirmModal, setShowConfirmModal] = useState<boolean>(false);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const fetchSettings = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const res = await adminApi.getAiSettings();
      setData(res);

      const active = res.active_model;
      const isPreset = res.presets.some((p) => p.id === active);

      if (isPreset) {
        setSelectedModel(active);
        setIsCustomMode(false);
      } else {
        setSelectedModel(active);
        setIsCustomMode(true);
        setCustomModelInput(active);
      }

      setTemperature(res.temperature ?? 0.2);
      setMaxToolRounds(res.max_tool_rounds ?? 5);
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err.message || 'Gagal memuat pengaturan AI.',
      });
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const effectiveModel = isCustomMode ? customModelInput.trim() : selectedModel;
  const isModelChanged = Boolean(data?.active_model && effectiveModel !== data.active_model);

  const totalPresets = data?.presets.length ?? 0;
  const freeCount = data?.presets.filter((p) => p.category === 'free' || p.is_free).length ?? 0;
  const paidCount = data?.presets.filter((p) => p.category === 'paid' || (!p.is_free && p.category !== 'free')).length ?? 0;

  const displayedPresets = (data?.presets ?? []).filter((preset) => {
    const isFree = preset.category === 'free' || preset.is_free;
    if (categoryFilter === 'free') return isFree;
    if (categoryFilter === 'paid') return !isFree;
    return true;
  });

  const handleTestConnection = async () => {
    if (!effectiveModel) {
      setFeedback({
        type: 'error',
        message: 'Tentukan identifier model yang ingin diuji terlebih dahulu.',
      });
      return;
    }

    setTestLoading(true);
    setTestResult(null);
    setCopiedReply(false);
    setFeedback(null);

    try {
      const result = await adminApi.testAiModel(effectiveModel);
      setTestResult(result);
    } catch (err: any) {
      setTestResult({
        success: false,
        latency_ms: 0,
        message: err.message || 'Gagal menghubungi server OpenRouter.',
        model: effectiveModel,
        error: err.message,
      });
    } finally {
      setTestLoading(false);
    }
  };

  const handleCopyReply = (text: string) => {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      navigator.clipboard.writeText(text);
      setCopiedReply(true);
      setTimeout(() => setCopiedReply(false), 2000);
    }
  };

  const handleConfirmSave = async () => {
    if (!effectiveModel) {
      setFeedback({
        type: 'error',
        message: 'Identifier model tidak boleh kosong.',
      });
      return;
    }

    setSaveLoading(true);
    setFeedback(null);

    try {
      await adminApi.updateAiSettings({
        model: effectiveModel,
        temperature,
        max_tool_rounds: maxToolRounds,
      });

      setShowConfirmModal(false);
      setFeedback({
        type: 'success',
        message: `Model AI berhasil dialihkan ke "${effectiveModel}". Perubahan langsung aktif ke seluruh chatbot.`,
      });
      fetchSettings(true);
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err.message || 'Gagal menyimpan konfigurasi model AI.',
      });
    } finally {
      setSaveLoading(false);
    }
  };

  return (
    <div className="space-y-5 max-w-6xl pb-10">
      {/* Header */}
      <AdminHeader
        title="Infrastruktur & Model AI"
        subtitle="Kelola pemilihan model LLM dan parameter inferensi untuk asisten cerdas platform (LANDY)"
        onRefresh={() => fetchSettings(true)}
        refreshing={refreshing}
      />

      {/* Global Feedback Alert Banner */}
      {feedback && (
        <div
          className={`p-3.5 rounded-xl text-xs flex items-center justify-between border transition-all ${
            feedback.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border-rose-200 text-rose-800'
          }`}
        >
          <div className="flex items-center gap-2">
            {feedback.type === 'success' ? (
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
            ) : (
              <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
            )}
            <span className="font-medium leading-relaxed">{feedback.message}</span>
          </div>
          <button
            onClick={() => setFeedback(null)}
            className="text-slate-400 hover:text-slate-700 cursor-pointer p-1"
            aria-label="Tutup pemberitahuan"
          >
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Compact System Status Strip */}
      <div className="bg-white border border-slate-200/90 rounded-xl px-4 py-3 shadow-2xs flex flex-wrap items-center justify-between gap-4 text-xs">
        <div className="flex items-center gap-5 flex-wrap">
          {/* Active Model */}
          <div className="flex items-center gap-2">
            <span className="text-slate-400 font-medium">Model Aktif:</span>
            <span className="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/80">
              {loading ? 'Memuat...' : data?.active_model || '-'}
            </span>
          </div>

          {/* Connection Provider */}
          <div className="flex items-center gap-2">
            <span className="text-slate-400 font-medium">Provider:</span>
            <span className="inline-flex items-center gap-1.5 font-medium text-slate-700">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              OpenRouter API
            </span>
          </div>

          {/* Runtime Parameters */}
          <div className="hidden sm:flex items-center gap-3 text-slate-500 border-l border-slate-200 pl-4">
            <span>
              Temperature: <strong className="font-mono text-slate-800">{loading ? '...' : data?.temperature}</strong>
            </span>
            <span className="text-slate-300">•</span>
            <span>
              Max Rounds: <strong className="font-mono text-slate-800">{loading ? '...' : data?.max_tool_rounds}</strong>
            </span>
          </div>
        </div>

        {/* Audit Meta */}
        <div className="text-[11px] text-slate-400">
          Pembaruan:{' '}
          <span className="font-medium text-slate-600">
            {data?.updated_at
              ? new Date(data.updated_at).toLocaleString('id-ID', {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                }) + ' WIB'
              : 'Default'}
          </span>
          {data?.updated_by?.name && (
            <span className="text-slate-500"> ({data.updated_by.name})</span>
          )}
        </div>
      </div>

      {/* Main Split-Pane Workspace (Option A) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        {/* LEFT COLUMN (7 COLS): Model Registry & Selection */}
        <div className="lg:col-span-7 space-y-4">
          <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-4">
            {/* Section Header */}
            <div className="pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <Cpu className="w-4 h-4 text-cyan-700" />
                <h2 className="text-sm font-semibold text-slate-900">Katalog Model LLM</h2>
              </div>
              <p className="text-xs text-slate-500 mt-0.5">
                Pilih model inferensi yang kompatibel dengan pemanggilan tools database.
              </p>
            </div>

            {/* Roomy Horizontal Category Filter Tabs */}
            <div className="flex items-center gap-1.5 p-1 bg-slate-100/90 rounded-xl border border-slate-200/80 text-xs">
              <button
                type="button"
                onClick={() => setCategoryFilter('all')}
                className={`flex-1 py-2 px-3 font-semibold rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap ${
                  categoryFilter === 'all'
                    ? 'bg-white text-slate-900 shadow-2xs font-bold'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                }`}
              >
                <span>Semua Model</span>
                <span
                  className={`text-[10px] px-2 py-0.5 rounded-full font-mono font-bold ${
                    categoryFilter === 'all' ? 'bg-slate-100 text-slate-700' : 'bg-slate-200 text-slate-600'
                  }`}
                >
                  {totalPresets}
                </span>
              </button>

              <button
                type="button"
                onClick={() => setCategoryFilter('free')}
                className={`flex-1 py-2 px-3 font-semibold rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap ${
                  categoryFilter === 'free'
                    ? 'bg-white text-emerald-800 shadow-2xs ring-1 ring-emerald-300 font-bold'
                    : 'text-slate-600 hover:text-emerald-900 hover:bg-emerald-50/50'
                }`}
              >
                <span className="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                <span>Free Tier (Gratis)</span>
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold font-mono">
                  {freeCount}
                </span>
              </button>

              <button
                type="button"
                onClick={() => setCategoryFilter('paid')}
                className={`flex-1 py-2 px-3 font-semibold rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer whitespace-nowrap ${
                  categoryFilter === 'paid'
                    ? 'bg-white text-cyan-900 shadow-2xs ring-1 ring-cyan-300 font-bold'
                    : 'text-slate-600 hover:text-cyan-900 hover:bg-cyan-50/50'
                }`}
              >
                <span>Komersial (Berbayar)</span>
                <span
                  className={`text-[10px] px-2 py-0.5 rounded-full font-mono font-bold ${
                    categoryFilter === 'paid' ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-200 text-slate-700'
                  }`}
                >
                  {paidCount}
                </span>
              </button>
            </div>

            {/* Contextual Tier Guide Note */}
            {categoryFilter === 'free' && (
              <div className="flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-emerald-50/60 border border-emerald-200/70 text-emerald-900 text-xs">
                <Info className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                <span className="leading-relaxed">
                  <strong>OpenRouter Free Tier:</strong> Model gratis (berakhiran <code>:free</code>) tidak memerlukan saldo deposit akun. Ideal untuk tahap pengembangan dan pengujian tanpa biaya API.
                </span>
              </div>
            )}

            {categoryFilter === 'paid' && (
              <div className="flex items-start gap-2.5 px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-xs">
                <Info className="w-4 h-4 text-slate-500 shrink-0 mt-0.5" />
                <span className="leading-relaxed">
                  <strong>Model Komersial:</strong> Membutuhkan saldo kredit aktif di akun OpenRouter. Menyediakan kapasitas rate-limit tinggi dan latensi rendah untuk trafik produksi.
                </span>
              </div>
            )}

            {/* Model Selectable Items List */}
            <div className="space-y-2">
              {displayedPresets.map((preset) => {
                const isSelected = !isCustomMode && selectedModel === preset.id;
                const isCurrentlyActive = data?.active_model === preset.id;
                const isFree = preset.category === 'free' || preset.is_free;

                return (
                  <div
                    key={preset.id}
                    onClick={() => {
                      setIsCustomMode(false);
                      setSelectedModel(preset.id);
                    }}
                    tabIndex={0}
                    role="button"
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        setIsCustomMode(false);
                        setSelectedModel(preset.id);
                      }
                    }}
                    className={`p-3.5 rounded-xl border text-left cursor-pointer transition-all duration-150 flex items-center justify-between gap-3 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-cyan-600 ${
                      isSelected
                        ? 'border-cyan-600 bg-cyan-50/30 ring-1 ring-cyan-600/30 shadow-2xs'
                        : 'border-slate-200/90 hover:border-slate-300 hover:bg-slate-50/60'
                    }`}
                  >
                    {/* Left: Radio & Info */}
                    <div className="flex items-start gap-3 min-w-0">
                      <div
                        className={`w-4 h-4 rounded-full border flex items-center justify-center shrink-0 mt-0.5 transition-colors ${
                          isSelected ? 'border-cyan-700 bg-cyan-700 text-white' : 'border-slate-300 bg-white'
                        }`}
                      >
                        {isSelected && <Check className="w-2.5 h-2.5" strokeWidth={3} />}
                      </div>

                      <div className="space-y-0.5 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-xs font-bold text-slate-900 truncate">{preset.name}</span>

                          {/* Tier Badge */}
                          <span
                            className={`inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded ${
                              isFree
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70'
                                : 'bg-slate-100 text-slate-700 border border-slate-200'
                            }`}
                          >
                            <span
                              className={`w-1.5 h-1.5 rounded-full ${
                                isFree ? 'bg-emerald-500' : 'bg-slate-400'
                              }`}
                            ></span>
                            {isFree ? 'Free Tier' : 'Komersial'}
                          </span>

                          {/* Database Active Status Indicator */}
                          {isCurrentlyActive && (
                            <span className="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-900 text-white flex items-center gap-1">
                              <span className="w-1 h-1 rounded-full bg-emerald-400"></span>
                              Aktif di Sistem
                            </span>
                          )}

                          {preset.available_on_openrouter === false && (
                            <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">
                              Tidak aktif di OpenRouter (404)
                            </span>
                          )}
                        </div>

                        <div className="text-[11px] font-mono text-slate-500 truncate">{preset.id}</div>
                        <p className="text-[11px] text-slate-600 line-clamp-1">{preset.description}</p>
                      </div>
                    </div>

                    {/* Right: Pricing */}
                    <div className="text-right shrink-0">
                      <div className="text-[10px] text-slate-400 font-medium">Estimasi Biaya</div>
                      <div
                        className={`text-xs font-mono font-semibold ${
                          isFree ? 'text-emerald-700 font-bold' : 'text-slate-800'
                        }`}
                      >
                        {preset.cost_estimate}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Live OpenRouter :free Catalog */}
            {categoryFilter !== 'paid' && (data?.live_free_models?.length ?? 0) > 0 && (
              <div className="pt-3 border-t border-slate-100 space-y-2.5">
                <div className="flex items-center justify-between gap-2">
                  <div>
                    <h3 className="text-xs font-bold text-slate-900">
                      Model <code className="font-mono">:free</code> Live dari OpenRouter
                    </h3>
                    <p className="text-[11px] text-slate-500">
                      Sinkron otomatis dari katalog OpenRouter aktif tanpa menghapus preset di atas.
                    </p>
                  </div>
                  <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">
                    {data?.live_free_models?.length} aktif
                  </span>
                </div>

                <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                  {data?.live_free_models?.map((live) => {
                    const isSelected = isCustomMode && customModelInput.trim() === live.id;
                    const isCurrentlyActive = data?.active_model === live.id;

                    return (
                      <div
                        key={live.id}
                        onClick={() => {
                          setIsCustomMode(true);
                          setCustomModelInput(live.id);
                        }}
                        tabIndex={0}
                        role="button"
                        onKeyDown={(e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                            setIsCustomMode(true);
                            setCustomModelInput(live.id);
                          }
                        }}
                        className={`p-3 rounded-xl border text-left cursor-pointer transition-all duration-150 flex items-center justify-between gap-3 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-cyan-600 ${
                          isSelected
                            ? 'border-cyan-600 bg-cyan-50/30 ring-1 ring-cyan-600/30 shadow-2xs'
                            : 'border-slate-200/90 hover:border-slate-300 hover:bg-slate-50/60'
                        }`}
                      >
                        <div className="flex items-start gap-3 min-w-0">
                          <div
                            className={`w-4 h-4 rounded-full border flex items-center justify-center shrink-0 mt-0.5 transition-colors ${
                              isSelected ? 'border-cyan-700 bg-cyan-700 text-white' : 'border-slate-300 bg-white'
                            }`}
                          >
                            {isSelected && <Check className="w-2.5 h-2.5" strokeWidth={3} />}
                          </div>

                          <div className="space-y-0.5 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="text-xs font-bold text-slate-900 truncate">{live.name}</span>
                              <span
                                className={`text-[10px] font-semibold px-1.5 py-0.5 rounded border ${
                                  live.supports_tools
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : 'bg-amber-50 text-amber-700 border-amber-200'
                                }`}
                              >
                                {live.supports_tools ? 'Tools OK' : 'Tanpa Tools'}
                              </span>
                              {isCurrentlyActive && (
                                <span className="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-900 text-white flex items-center gap-1">
                                  <span className="w-1 h-1 rounded-full bg-emerald-400"></span>
                                  Aktif di Sistem
                                </span>
                              )}
                            </div>
                            <div className="text-[11px] font-mono text-slate-500 truncate">{live.id}</div>
                          </div>
                        </div>

                        <div className="text-right shrink-0">
                          <div className="text-[11px] font-mono font-bold text-emerald-700">Rp 0</div>
                          {live.context_length > 0 && (
                            <div className="text-[10px] font-mono text-slate-400">
                              {Math.round(live.context_length / 1000)}K ctx
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Custom Model Option */}
            <div className="pt-2">
              <div
                onClick={() => setIsCustomMode(true)}
                className={`p-3.5 rounded-xl border cursor-pointer transition-all duration-150 ${
                  isCustomMode
                    ? 'border-cyan-600 bg-cyan-50/30 ring-1 ring-cyan-600/30 shadow-2xs'
                    : 'border-slate-200/90 hover:border-slate-300'
                }`}
              >
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2">
                    <Server className="w-4 h-4 text-slate-600" />
                    <span className="text-xs font-bold text-slate-900">Custom Model Identifier (OpenRouter)</span>
                  </div>
                  <div
                    className={`w-4 h-4 rounded-full border flex items-center justify-center shrink-0 ${
                      isCustomMode ? 'border-cyan-700 bg-cyan-700 text-white' : 'border-slate-300 bg-white'
                    }`}
                  >
                    {isCustomMode && <Check className="w-2.5 h-2.5" strokeWidth={3} />}
                  </div>
                </div>

                <p className="text-[11px] text-slate-500 mb-2.5">
                  Gunakan model OpenRouter lain dengan format <code>provider/model-id</code> (contoh: <code>mistralai/mistral-large-2411</code>).
                </p>

                <input
                  type="text"
                  value={customModelInput}
                  disabled={!isCustomMode}
                  onChange={(e) => setCustomModelInput(e.target.value)}
                  placeholder="e.g. mistralai/mistral-large-2411"
                  className={`w-full px-3 py-2 text-xs font-mono rounded-lg border transition-all ${
                    isCustomMode
                      ? 'bg-white border-slate-300 text-slate-900 focus:outline-hidden focus:border-cyan-700 focus:ring-1 focus:ring-cyan-700'
                      : 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed'
                  }`}
                />
              </div>
            </div>
          </div>
        </div>

        {/* RIGHT COLUMN (5 COLS): Runtime Parameters, Diagnostics, and Execution */}
        <div className="lg:col-span-5 space-y-4 lg:sticky lg:top-6">
          {/* Card 1: Runtime Parameters */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-4">
            <div className="flex items-center gap-2 pb-3 border-b border-slate-100">
              <SlidersHorizontal className="w-4 h-4 text-cyan-700" />
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                Parameter Inferensi
              </h3>
            </div>

            <div className="space-y-4">
              {/* Temperature Slider & Quick Presets */}
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="text-xs font-semibold text-slate-700">Temperature</label>
                  <span className="text-xs font-mono font-bold text-cyan-800 bg-cyan-50 border border-cyan-200/60 px-2 py-0.5 rounded">
                    {temperature}
                  </span>
                </div>
                <input
                  type="range"
                  min="0"
                  max="1"
                  step="0.05"
                  value={temperature}
                  onChange={(e) => setTemperature(parseFloat(e.target.value))}
                  className="w-full accent-cyan-700 cursor-pointer"
                />
                
                {/* Visual scale indicators */}
                <div className="flex justify-between text-[10px] text-slate-400 mt-1">
                  <span>0.0 (Presisi)</span>
                  <span>0.5 (Seimbang)</span>
                  <span>1.0 (Kreatif)</span>
                </div>

                {/* Ergonomic Quick Select Pills */}
                <div className="flex items-center gap-1.5 mt-2.5 pt-2 border-t border-slate-100">
                  <span className="text-[10px] text-slate-400 font-medium mr-1">Preset:</span>
                  <button
                    type="button"
                    onClick={() => setTemperature(0.0)}
                    className={`text-[10px] px-2 py-0.5 rounded border transition-colors cursor-pointer ${
                      temperature === 0.0
                        ? 'bg-cyan-700 text-white border-cyan-700 font-semibold'
                        : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200'
                    }`}
                  >
                    0.0 Presisi
                  </button>
                  <button
                    type="button"
                    onClick={() => setTemperature(0.2)}
                    className={`text-[10px] px-2 py-0.5 rounded border transition-colors cursor-pointer ${
                      temperature === 0.2
                        ? 'bg-cyan-700 text-white border-cyan-700 font-semibold'
                        : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200'
                    }`}
                  >
                    0.2 Rekomendasi
                  </button>
                  <button
                    type="button"
                    onClick={() => setTemperature(0.5)}
                    className={`text-[10px] px-2 py-0.5 rounded border transition-colors cursor-pointer ${
                      temperature === 0.5
                        ? 'bg-cyan-700 text-white border-cyan-700 font-semibold'
                        : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200'
                    }`}
                  >
                    0.5 Seimbang
                  </button>
                </div>
              </div>

              {/* Max Tool Rounds */}
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="text-xs font-semibold text-slate-700">Batas Putaran Tools (Max Rounds)</label>
                  <span className="text-xs font-mono font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">
                    {maxToolRounds}
                  </span>
                </div>
                <input
                  type="number"
                  min="1"
                  max="10"
                  value={maxToolRounds}
                  onChange={(e) => setMaxToolRounds(parseInt(e.target.value, 10) || 5)}
                  className="w-full px-3 py-2 text-xs font-mono font-semibold bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:bg-white focus:border-cyan-700 focus:ring-1 focus:ring-cyan-700 text-slate-900"
                />
                <span className="text-[10px] text-slate-400 mt-1 block">
                  Batas perulangan panggilan tool database per query user (standar: 5).
                </span>
              </div>
            </div>
          </div>

          {/* Card 2: Live Diagnostic Inspector */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-3">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <Activity className="w-4 h-4 text-cyan-700" />
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                  Uji Diagnostik
                </h3>
              </div>

              <button
                type="button"
                onClick={handleTestConnection}
                disabled={testLoading || !effectiveModel}
                className="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50"
              >
                <Activity className={`w-3.5 h-3.5 ${testLoading ? 'animate-spin text-cyan-700' : 'text-slate-500'}`} />
                {testLoading ? 'Menguji...' : 'Uji Koneksi'}
              </button>
            </div>

            <div className="text-xs text-slate-500">
              Uji latensi respon dan ketersediaan model sebelum menerapkan ke sistem.
            </div>

            {/* Test loading state skeleton */}
            {testLoading && (
              <div className="p-3 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 text-xs space-y-1.5 font-mono animate-pulse">
                <div className="flex items-center gap-2 text-cyan-400">
                  <Activity className="w-3.5 h-3.5 animate-spin" />
                  <span>Mengirim ping diagnostik ke model...</span>
                </div>
                <div className="text-[10px] text-slate-400 truncate">
                  Endpoint: OpenRouter /chat/completions ({effectiveModel})
                </div>
              </div>
            )}

            {/* Diagnostic Console Box */}
            {!testLoading && testResult && (
              <div
                className={`p-3 rounded-lg border text-xs space-y-2 ${
                  testResult.success
                    ? 'bg-slate-900 text-slate-100 border-slate-800'
                    : 'bg-rose-50 text-rose-900 border-rose-200'
                }`}
              >
                <div className="flex items-center justify-between">
                  <span className="flex items-center gap-1.5 font-bold">
                    {testResult.success ? (
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
                    ) : (
                      <AlertCircle className="w-3.5 h-3.5 text-rose-600" />
                    )}
                    {testResult.success ? 'Koneksi Berhasil (200 OK)' : 'Koneksi Gagal'}
                  </span>
                  {testResult.latency_ms > 0 && (
                    <span className="font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-emerald-300 border border-slate-700">
                      {testResult.latency_ms} ms
                    </span>
                  )}
                </div>

                <div className="text-[11px] font-mono leading-relaxed opacity-90">
                  {testResult.message}
                  {testResult.resolved_model && testResult.resolved_model !== testResult.model && (
                    <div className="mt-1 text-[10px] text-cyan-300">
                      Model eksekusi OpenRouter: {testResult.resolved_model}
                    </div>
                  )}
                </div>

                {testResult.reply && (
                  <div className="relative group">
                    <div className="text-[11px] font-mono p-2.5 rounded bg-slate-950/90 border border-slate-800 text-slate-300 pr-10">
                      <span className="text-slate-500 select-none">&gt; </span>
                      {testResult.reply}
                    </div>
                    <button
                      type="button"
                      onClick={() => handleCopyReply(testResult.reply || '')}
                      className="absolute top-2 right-2 p-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors cursor-pointer text-[10px] flex items-center gap-1"
                      title="Salin respons"
                    >
                      {copiedReply ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3" />}
                    </button>
                  </div>
                )}

                {testResult.error && (
                  <div className="text-[11px] font-mono p-2 rounded bg-rose-100/70 border border-rose-300 text-rose-900">
                    {testResult.error}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Card 3: Action Panel */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-3">
            <div className="text-xs space-y-1">
              <div className="text-slate-400 font-medium">Model yang akan diaktifkan:</div>
              <div className="font-mono font-bold text-slate-900 truncate" title={effectiveModel}>
                {effectiveModel || '-'}
              </div>
            </div>

            {/* Change state notice */}
            {isModelChanged ? (
              <div className="flex items-center gap-1.5 text-[11px] text-cyan-800 bg-cyan-50/70 border border-cyan-200/80 p-2.5 rounded-lg">
                <Info className="w-3.5 h-3.5 shrink-0 text-cyan-700" />
                <span>Model ini akan menggantikan model aktif di database setelah disimpan.</span>
              </div>
            ) : (
              <div className="text-[11px] text-slate-500">
                Model ini saat ini merupakan model aktif sistem.
              </div>
            )}

            <button
              type="button"
              onClick={() => setShowConfirmModal(true)}
              disabled={saveLoading || !effectiveModel}
              className="w-full px-4 py-2.5 rounded-lg bg-cyan-700 hover:bg-cyan-800 text-white text-xs font-semibold flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:opacity-50 shadow-xs"
            >
              {saveLoading ? 'Menyimpan konfigurasi...' : 'Terapkan Konfigurasi'}
            </button>
          </div>
        </div>
      </div>

      {/* Recent Model Execution Activity */}
      <div className="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-3">
        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h2 className="text-sm font-semibold text-slate-900">Aktivitas Eksekusi Model Terbaru</h2>
            <p className="text-xs text-slate-500 mt-0.5">
              Pantau 10 panggilan chatbot terakhir untuk memastikan model berjalan sukses dan bukan fallback error (404/429).
            </p>
          </div>
          <button
            type="button"
            onClick={() => fetchSettings(true)}
            disabled={refreshing}
            className="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
          >
            {refreshing ? 'Memuat...' : 'Refresh Log'}
          </button>
        </div>

        {(data?.recent_usage?.length ?? 0) === 0 ? (
          <div className="py-6 text-center text-xs text-slate-500">
            Belum ada log eksekusi chatbot tersimpan.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                  <th className="py-2 pr-4 font-semibold">Waktu (WIB)</th>
                  <th className="py-2 pr-4 font-semibold">Model Diminta</th>
                  <th className="py-2 pr-4 font-semibold">Status</th>
                  <th className="py-2 text-right font-semibold">Token (In / Out)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data?.recent_usage?.map((log) => {
                  const isSuccess = log.status === 'success';
                  return (
                    <tr key={log.id} className="align-top">
                      <td className="py-2.5 pr-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                        {log.created_at
                          ? new Date(log.created_at).toLocaleString('id-ID', {
                              day: '2-digit',
                              month: 'short',
                              hour: '2-digit',
                              minute: '2-digit',
                              second: '2-digit',
                            })
                          : '-'}
                      </td>
                      <td className="py-2.5 pr-4">
                        <div className="font-mono font-semibold text-slate-800">{log.model}</div>
                        {log.resolved_model && log.resolved_model !== log.model && (
                          <div className="text-[11px] font-mono text-cyan-700 mt-0.5">
                            Eksekusi OpenRouter: {log.resolved_model}
                          </div>
                        )}
                        {log.error && (
                          <div className="text-[11px] text-rose-700 mt-0.5 break-all">{log.error}</div>
                        )}
                      </td>
                      <td className="py-2.5 pr-4 whitespace-nowrap">
                        <span
                          className={`inline-block text-[10px] font-semibold px-2 py-0.5 rounded border ${
                            isSuccess
                              ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                              : 'bg-rose-50 text-rose-700 border-rose-200'
                          }`}
                        >
                          {log.status}
                        </span>
                      </td>
                      <td className="py-2.5 text-right font-mono text-[11px] text-slate-600 whitespace-nowrap">
                        {log.prompt_tokens} / {log.completion_tokens}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Confirmation Modal */}
      {showConfirmModal && (
        <div className="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4 border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-start justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-700 flex items-center justify-center shrink-0">
                  <Cpu className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">Konfirmasi Pembaruan Model AI</h3>
                  <p className="text-xs text-slate-500">Model baru akan langsung aktif untuk seluruh chatbot asisten.</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setShowConfirmModal(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Diff Highlight Box */}
            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-2 font-mono">
              <div className="flex items-center justify-between text-slate-600">
                <span>Model Aktif Saat Ini:</span>
                <span className="font-semibold text-slate-700 truncate max-w-[200px]">{data?.active_model || '-'}</span>
              </div>
              <div className="flex items-center justify-between text-cyan-800 font-bold bg-cyan-50/60 p-1.5 rounded border border-cyan-200/60">
                <span className="flex items-center gap-1">
                  <ArrowRight className="w-3 h-3" /> Model Baru:
                </span>
                <span className="truncate max-w-[200px]">{effectiveModel}</span>
              </div>
              <div className="flex items-center justify-between text-slate-600 pt-1 border-t border-slate-200/60">
                <span>Temperature:</span>
                <span className="font-bold text-slate-900">{temperature}</span>
              </div>
              <div className="flex items-center justify-between text-slate-600">
                <span>Max tool rounds:</span>
                <span className="font-bold text-slate-900">{maxToolRounds}</span>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setShowConfirmModal(false)}
                disabled={saveLoading}
                className="px-3.5 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-200 cursor-pointer transition-colors"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleConfirmSave}
                disabled={saveLoading}
                className="px-4 py-2 text-xs font-semibold text-white bg-cyan-700 hover:bg-cyan-800 rounded-xl shadow-xs cursor-pointer flex items-center gap-1.5 transition-colors disabled:opacity-50"
              >
                {saveLoading ? 'Menyimpan...' : 'Ya, Terapkan Sekarang'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
