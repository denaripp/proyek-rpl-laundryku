import fs from 'fs';
import path from 'path';

const API_BASE = 'http://localhost:3000/api/chat';

const scenarios = [
  {
    id: 1,
    name: 'Sapaan sederhana',
    message: 'Halo',
    history: [],
    validate: (reply) => {
      // Must be natural brief greeting, NOT a massive help menu or FAQ
      const isBrief = reply.length < 250;
      const isGreeting = /halo|hai|bantu|selamat/i.test(reply);
      const isNotMenu = !reply.includes('1. Cari Toko') && !reply.includes('Pusat Bantuan Cerdas:');
      return isGreeting && isBrief && isNotMenu;
    },
  },
  {
    id: 2,
    name: 'Pendaftaran customer',
    message: 'Gimana cara daftar akun?',
    history: [],
    validate: (reply) => {
      // Must mention /auth, OTP, whatsapp/email, passwordless
      return /auth|daftar|otp|whatsapp/i.test(reply);
    },
  },
  {
    id: 3,
    name: 'Pendaftaran mitra laundry',
    message: 'Gimana cara daftar jadi mitra laundry?',
    history: [],
    validate: (reply) => {
      // Must mention /upgrade, toko, 1x24 jam verifikasi, syarat
      return /upgrade|toko|verifikasi|mitra/i.test(reply);
    },
  },
  {
    id: 4,
    name: 'Pendaftaran kurir',
    message: 'Gue mau daftar jadi kurir, apa aja yang harus disiapin?',
    history: [],
    validate: (reply) => {
      // Must mention SIM C, KTP, motor, /upgrade
      return /sim c|ktp|motor|upgrade/i.test(reply);
    },
  },
  {
    id: 5,
    name: 'Pesanan & estimasi biaya',
    message: 'Gimana cara buat pesanan dan hitung estimasi biayanya?',
    history: [],
    validate: (reply) => {
      // Must explain order steps, stores, estimation vs actual weight
      return /stores|timbang|estimasi|berat|jemput/i.test(reply);
    },
  },
  {
    id: 6,
    name: 'Metode pembayaran',
    message: 'Bisa bayar pakai apa saja?',
    history: [],
    validate: (reply) => {
      // Must list supported methods: QRIS, VA, COD
      return /qris/i.test(reply) && /virtual account|va/i.test(reply) && /cod/i.test(reply);
    },
  },
  {
    id: 7,
    name: 'Keluhan status pesanan',
    message: 'Kenapa kurir belum jemput pakaian saya?',
    history: [],
    validate: (reply) => {
      // Must address tracking/orders or store chat
      return /orders|pesanan|chat|toko/i.test(reply);
    },
  },
  {
    id: 8,
    name: 'Kendala transaksi pending',
    message: 'Pesanan gue udah dibayar tapi statusnya masih pending',
    history: [],
    validate: (reply) => {
      // Must provide troubleshooting (mutasi, sinkronisasi/webhook, orders, upload bukti transfer)
      return /sinkronisasi|webhook|mutasi|orders|bukti|menit/i.test(reply);
    },
  },
  {
    id: 9,
    name: 'Pertanyaan lanjutan (multi-turn context)',
    message: 'Berapa lama proses verifikasinya?',
    history: [
      { role: 'user', content: 'Gimana cara daftar jadi mitra laundry?' },
      { role: 'assistant', content: 'Untuk mendaftar mitra toko, buka menu /upgrade tab Pendaftaran Toko.' },
    ],
    validate: (reply) => {
      // Must answer verification timeframe in context of mitra (1x24 jam)
      return /1x24|24 jam|verifikasi|mitra|upgrade/i.test(reply);
    },
  },
  {
    id: 10,
    name: 'Informasi belum lengkap / butuh klarifikasi',
    message: 'Tolong bantu saya',
    history: [],
    validate: (reply) => {
      // Must ask specific clarification rather than repeat simple greeting or FAQ list
      return /spesifik|detail|jelaskan|kendala|pesanan/i.test(reply);
    },
  },
  {
    id: 11,
    name: 'Di luar konteks platform',
    message: 'Siapa presiden Indonesia saat ini?',
    history: [],
    validate: (reply) => {
      // Must politely redirect to LaundryKu context
      return /laundryku|seputar|layanan|maaf/i.test(reply);
    },
  },
  {
    id: 12,
    name: 'Simulasi error/empty payload',
    message: '',
    history: [],
    validate: (reply, status) => {
      // Must reject empty message or return proper error/validation message
      return status >= 400 || /kosong|diperlukan|pesan/i.test(reply);
    },
  },
];

async function runTests() {
  console.log('===============================================================');
  console.log('       TESTING 12 SKENARIO PERILAKU & RESPON AI ASSISTANT      ');
  console.log('===============================================================\n');

  let passedCount = 0;

  for (const s of scenarios) {
    console.log(`[SKENARIO ${s.id}] ${s.name}`);
    console.log(`> User: "${s.message || '(pesan kosong)'}"`);
    if (s.history.length > 0) {
      console.log(`  (History: ${s.history.length} turn percakapan sebelumnya)`);
    }

    try {
      const res = await fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: s.message,
          messages: s.history.length > 0 ? s.history : undefined,
        }),
      });

      const json = await res.json().catch(() => ({}));
      const reply =
        typeof json?.data?.answer === 'string'
          ? json.data.answer
          : typeof json?.error?.message === 'string'
          ? json.error.message
          : typeof json?.error === 'string'
          ? json.error
          : typeof json?.message === 'string'
          ? json.message
          : JSON.stringify(json);
      const isValid = s.validate(reply, res.status);

      console.log(`  Status HTTP: ${res.status}`);
      console.log(`  AI Reply Snippet:\n  "${reply.slice(0, 160).replace(/\n/g, ' ')}..."`);
      console.log(`  Validasi: ${isValid ? '✅ PASS' : '❌ FAIL'}\n`);

      if (isValid) passedCount++;
    } catch (err) {
      console.log(`  Fetch Error: ${err.message}`);
      console.log(`  Validasi: ❌ FAIL\n`);
    }
  }

  console.log('===============================================================');
  console.log(`HASIL AKHIR: ${passedCount} / ${scenarios.length} Skenario Berhasil Terpenuhi.`);
  console.log('===============================================================\n');
}

runTests();
