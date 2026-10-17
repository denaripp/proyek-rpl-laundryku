import { NextRequest, NextResponse } from 'next/server';

export const SYSTEM_PROMPT = `Kamu adalah AI Assistant untuk marketplace laundry LaundryKu. Tugasmu membantu customer, mitra laundry, dan kurir memahami layanan, menyelesaikan kendala, serta menggunakan fitur platform.

Pahami maksud pengguna berdasarkan bahasa natural dan konteks percakapan, meskipun pertanyaannya singkat, informal, mengandung typo, atau tidak menyebutkan detail teknis.

Jangan mewajibkan pengguna menggunakan kata kunci, istilah resmi, atau format pertanyaan tertentu.

KETIKA PENGGUNA MENYAMPAIKAN MASALAH:
1. Identifikasi kebutuhan atau masalah yang paling mungkin dimaksud.
2. Berikan penjelasan atau langkah awal yang relevan dan solutif.
3. Sebutkan fitur atau prosedur platform yang dapat membantu jika keberadaannya terverifikasi (misal /orders, /chat, /upgrade, /profile, /stores).
4. Tanyakan satu klarifikasi singkat jika memang dibutuhkan untuk menentukan tindakan berikutnya.

JANGAN PERNAH hanya menjawab "bisa dijelaskan lebih spesifik?", "mohon berikan detail pertanyaan", atau "ada yang bisa saya bantu?" ketika maksud utama pengguna sudah dapat dipahami. Berikan jawaban awal yang bermanfaat terlebih dahulu!

PANDUAN KHUSUS BERDASARKAN KASUS:
- Jika pengguna ingin membatalkan pesanan, jelaskan prosedur pembatalan berdasarkan status pesanan dan kebijakan platform (gratis di tahap persetujuan harga final; koordinasi via Chat Toko jika sedang dicuci).
- Jika pengguna ingin komplain, bantu menentukan jalur pengaduan yang tepat (menu Pesanan Saya -> Ajukan Komplain maks 1x24 jam setelah COMPLETED dengan foto bukti, atau Chat Toko).
- Jika pengguna mengalami masalah kurir (belum datang/telat), berikan langkah penanganan sesuai tahap pengiriman (cek status PENDING_PICKUP vs PICKUP_ASSIGNED di /orders, hubungi toko/kurir via /chat).
- Jika pengguna bertanya tentang refund (uang kembali), jelaskan prosedur yang benar (otomatis jika pesanan batal sebelum cuci atau hasil komplain disetujui, dana dikembalikan via payment gateway Xendit ke rekening asal) tanpa menjanjikan hasil yang belum diketahui.
- Jika pengguna menanyakan sesuatu yang tidak dapat dipastikan (misal jadwal toko tertentu di hari libur atau data pesanan pribadi tanpa login), jelaskan apa yang diketahui secara umum, apa yang belum dapat diverifikasi, dan bagaimana pengguna dapat memperoleh kepastian.

KNOWLEDGE BASE AKTUAL MARKETPLACE LAUNDRYKU:

1. PESANAN & PEMBATALAN:
   - Buat Pesanan: Cari toko di /stores (radius maks 10 km) -> pilih layanan (Kiloan, Satuan, Dry Clean, Express) -> kurir jemput -> toko timbang riil -> pelanggan setujui harga final -> bayar -> cuci -> siap antar -> diantar kembali.
   - Batal Pesanan: Di tahap AWAITING_PRICE_APPROVAL (setelah timbangan toko), customer berhak membatalkan bebas biaya. Jika sudah PROCESSING (dicuci), koordinasikan langsung dengan toko mitra via /chat.
   - Perubahan Alamat: Jika pesanan baru dibuat dan kurir belum berangkat, segera beri tahu toko/kurir via /chat. Untuk pesanan berikutnya, ubah di /profile.
   - Pengerjaan Terasa Lama: Reguler memakan waktu 1-3 hari, Express 6-24 jam. Pantau tahapan WASHING/DRYING/IRONING di /orders atau tanyakan antrean via /chat.

2. KURIR & PENGIRIMAN:
   - Kurir Belum Datang / Telat: Cek /orders (status PENDING_PICKUP berarti toko sedang mencari kurir, PICKUP_ASSIGNED berarti kurir sedang menuju alamat). Hubungi via /chat untuk memastikan perkiraan jam tiba.
   - Daftar Kurir: Buka /upgrade -> tab Pengajuan Kurir. Syarat: KTP & NIK valid, SIM C aktif, sepeda motor & plat nomor valid, nomor WhatsApp aktif. Verifikasi 1x24 jam kerja.

3. CUSTOMER SERVICE & KOMPLAIN:
   - Jalur CS & Bantuan: Komunikasi langsung dengan toko via menu Pesan & Chat Toko (/chat). Pengaduan resmi via /orders -> Ajukan Komplain. Kontak WhatsApp resmi platform di /profile.
   - Pakaian Rusak / Luntur / Noda: Foto kondisi pakaian, ajukan komplain di /orders maksimal 1x24 jam sejak selesai untuk pencucian ulang gratis atau kompensasi garansi.

4. PEMBAYARAN & REFUND:
   - Metode: QRIS instan (GoPay, OVO, DANA, ShopeePay, m-Banking), Virtual Account (BCA, Mandiri, BNI, BRI, Permata), dan Cash on Delivery (COD) tunai ke kurir.
   - Pembayaran Bermasalah / Gagal: Jika gagal atau kedaluwarsa, klik Bayar Ulang di /orders. Jika saldo terpotong tapi status pending, tunggu jeda webhook 1-5 menit; jika lewat 15 menit, kirim bukti mutasi ke admin via /chat tanpa bayar ganda.
   - Refund: Diproses ke rekening/e-wallet asal jika pesanan batal sebelum cuci atau komplain disetujui.

5. CUSTOMER & AKUN:
   - Daftar & Login: Passwordless OTP di /auth via WhatsApp/Email (6 digit, aktif 5 menit). Tidak ada kata sandi yang perlu diingat.

6. MITRA TOKO LAUNDRY:
   - Daftar: Buka /upgrade -> tab Pendaftaran Toko. Syarat: Nama toko, alamat & titik GPS (radius 10 km), jam operasional, kontak WA, foto outlet & KTP/NIB. Verifikasi 1x24 jam kerja.
   - Dashboard Toko: Kelola layanan, harga, input timbangan riil, dan penugasan kurir.

7. STRICT GUARDRAILS & ATURAN TOPIK:
   - HANYA menjawab topik seputar layanan LaundryKu (cara pesan, estimasi harga/tarif, pembayaran, status/pelacakan cucian, kebijakan komplain/refund, pendaftaran mitra toko atau kurir, dan kontak bantuan).
   - Untuk topik di luar LaundryKu (coding/pemrograman bahasa apa pun seperti python/javascript/php/dll, pengetahuan umum seperti presiden/politik, resep masakan, cuaca, identitas model AI dasar, dll.): TOLAK DENGAN SOPAN dan arahkan balik ke topik LaundryKu.
   - JANGAN PERNAH menulis, membuatkan, atau mengoreksi kode program apa pun atas permintaan pengguna. Tolak dengan ramah menggunakan kalimat: "Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Mau tanya soal itu?"`;

/**
 * Normalizes colloquial Indonesian slang, abbreviations, and common typos into standard terms
 * so that natural conversational messages can be processed reliably.
 */
export function normalizeUserQuery(text: string): string {
  let normalized = text.toLowerCase().trim();

  // Strip punctuation
  normalized = normalized.replace(/[.,\/#!$%\^&\*;:{}=\-_`~()?"'’]/g, ' ');
  normalized = normalized.replace(/\s+/g, ' ');

  const slangMap: Record<string, string> = {
    blm: 'belum',
    blom: 'belum',
    belom: 'belum',
    dateng: 'datang',
    dtg: 'datang',
    nyampe: 'sampai',
    sampe: 'sampai',
    batalin: 'batal',
    cancelin: 'batal',
    cancel: 'batal',
    ga: 'tidak',
    gak: 'tidak',
    ngga: 'tidak',
    nggak: 'tidak',
    ndak: 'tidak',
    gue: 'saya',
    gw: 'saya',
    gua: 'saya',
    aku: 'saya',
    sy: 'saya',
    lu: 'kamu',
    lo: 'kamu',
    elu: 'kamu',
    laundryan: 'cucian',
    londri: 'laundry',
    loundry: 'laundry',
    cucian: 'laundry',
    duit: 'uang',
    fulus: 'uang',
    ongkos: 'uang',
    balikin: 'refund',
    balik: 'refund',
    kembali: 'refund',
    hub: 'hubungi',
    hbngi: 'hubungi',
    kontak: 'hubungi',
    telpon: 'telepon',
    tlp: 'telepon',
    baju: 'pakaian',
    kaos: 'pakaian',
    celana: 'pakaian',
    sobek: 'rusak',
    robek: 'rusak',
    rusakk: 'rusak',
    ilang: 'hilang',
    lelet: 'lama',
    lambat: 'lama',
    telat: 'lama',
    almt: 'alamat',
    gmn: 'gimana',
    bgmn: 'bagaimana',
    bs: 'bisa',
    syrt: 'syarat',
    verif: 'verifikasi',
    acc: 'disetujui',
  };

  const words = normalized.split(' ');
  const replaced = words.map((w) => slangMap[w] || w);
  return replaced.join(' ');
}

/**
 * Intelligent Multi-Turn Knowledge Fallback Engine.
 * Evaluates user intent, context, and prior message history to provide thorough, non-generic assistance.
 */
export function getLocalKnowledgeReply(
  userMessage: string,
  history: Array<{ role: string; content: string }> = []
): string {
  const rawQ = userMessage.trim().toLowerCase();
  const q = normalizeUserQuery(userMessage);

  // 1. Pure Greeting Detection (Only when message is strictly a greeting, not a question or complaint)
  const isPureGreeting =
    /^(halo|hai|hi|hey|pagi|siang|sore|malam|selamat\s+(pagi|siang|sore|malam)|assalamu\s*alaikum|halo\s+(min|bot|asisten|admin)|tes|ping)[\s.!]*$/i.test(
      rawQ
    );
  if (isPureGreeting) {
    return 'Halo! Ada yang bisa saya bantu seputar pemesanan laundry, status pesanan, pembayaran, atau kemitraan di LaundryKu hari ini?';
  }

  // 1b. Explicit Out-of-Scope Off-Topic Rejection (Coding, general knowledge, politics, etc.)
  const isOffTopicExplicit =
    /\b(coding|koding|ngoding|code|python|javascript|php|html|css|java|c\+\+|golang|sql|script|program|pemrograman|bikin program|buatkan coding|buatkan kode|tulis kode|presiden|menteri|politik|pemilu|pilpres|resep|masak|makanan|cuaca|saham|crypto|bitcoin|judi|slot|game|film|selebriti|artis|chord|gitar|fisika|matematika|sejarah|biologi)\b/i.test(
      q
    );
  if (isOffTopicExplicit) {
    return 'Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Layanan ini difokuskan untuk membantu seluruh penggunaan marketplace LaundryKu. Mau tanya soal itu?';
  }

  // 2. Identify Context from Recent History (for follow-up questions)
  const priorText = history
    .slice(-4)
    .map((m) => normalizeUserQuery(m.content))
    .join(' ');

  const isPriorAboutMitra = priorText.includes('mitra') || priorText.includes('toko') || priorText.includes('outlet');
  const isPriorAboutKurir = priorText.includes('kurir') || priorText.includes('antar') || priorText.includes('jemput');
  const isPriorAboutCustomer = priorText.includes('daftar') || priorText.includes('akun') || priorText.includes('customer');

  // Contextual follow-up: "Terus syaratnya apa?" or "Syaratnya apa aja?"
  if (q.includes('syarat') && (q.includes('apa') || q.length < 25)) {
    if (isPriorAboutKurir) {
      return (
        '🛵 **Persyaratan Pendaftaran Kurir LaundryKu:**\n\n' +
        '1. KTP & NIK valid (disimpan aman terenkripsi).\n' +
        '2. SIM C aktif.\n' +
        '3. Sepeda motor pribadi dengan plat nomor kendaraan valid.\n' +
        '4. Nomor WhatsApp aktif untuk koordinasi penjemputan & pengantaran.\n\n' +
        'Daftar langsung melalui menu **Gabung Mitra** (`/upgrade`) -> tab **Pengajuan Kurir**.'
      );
    }
    if (isPriorAboutMitra) {
      return (
        '🏪 **Persyaratan Pendaftaran Mitra Toko LaundryKu:**\n\n' +
        '1. Nama dan foto fisik outlet toko laundry.\n' +
        '2. Alamat lengkap dan titik koordinat GPS (radius jangkauan layanan maks. 10 km).\n' +
        '3. Foto KTP pemilik dan izin usaha / NIB (jika ada).\n' +
        '4. Jam operasional dan nomor kontak WhatsApp bisnis.\n\n' +
        'Daftar langsung melalui menu **Gabung Mitra** (`/upgrade`) -> tab **Pendaftaran Toko**.'
      );
    }
    return (
      '📋 **Persyaratan Pendaftaran di LaundryKu:**\n\n' +
      '• **Sebagai Pelanggan (Customer):** Cukup nama lengkap dan nomor WhatsApp aktif (atau email) via Passwordless OTP di menu `/auth`.\n' +
      '• **Sebagai Mitra Toko:** KTP pemilik, foto outlet, titik GPS, dan nomor kontak di menu `/upgrade`.\n' +
      '• **Sebagai Kurir:** KTP valid, SIM C aktif, motor berplat nomor resmi, dan nomor WA di menu `/upgrade`.\n\n' +
      'Bagian mana yang ingin Anda daftarkan?'
    );
  }

  // Contextual follow-up: Verification timeframe
  if (
    (q.includes('berapa lama') || q.includes('kapan')) &&
    (q.includes('verifikasi') || q.includes('disetujui') || q.includes('approval') || isPriorAboutMitra || isPriorAboutKurir)
  ) {
    return (
      '⏱️ **Waktu Verifikasi Pendaftaran di LaundryKu:**\n\n' +
      'Proses verifikasi data pendaftaran mitra toko maupun pengajuan kurir diproses oleh tim Platform Admin dalam waktu maksimal **1x24 jam kerja** setelah formulir dan dokumen lengkap dikirimkan.\n\n' +
      'Status persetujuan diperbarui langsung pada menu **Gabung Mitra** (`/upgrade`).'
    );
  }

  // 3. Pembatalan Pesanan ("Mau batalin pesanan", "Mau batalin", "Gimana cara batalin pesanan?")
  if (
    q.includes('batal') ||
    q.includes('cancel') ||
    (q.includes('mau') && q.includes('batal'))
  ) {
    return (
      '🛑 **Panduan Pembatalan Pesanan LaundryKu:**\n\n' +
      '1. **Tahap Konfirmasi Harga (*AWAITING_PRICE_APPROVAL*):**\n' +
      '   • Saat pakaian selesai ditimbang oleh pihak toko, Anda menerima rincian harga final.\n' +
      '   • Anda berhak membatalkan pesanan secara mandiri di menu **Pesanan Saya** (`/orders`) — **100% bebas biaya** jika tidak menyetujui hasil timbangan.\n\n' +
      '2. **Tahap Sedang Diproses (*PROCESSING*):**\n' +
      '   • Jika cucian sudah dalam proses cuci fisik oleh toko, pembatalan otomatis dinonaktifkan.\n' +
      '   • Segera hubungi pihak toko mitra melalui menu **Chat Toko** (`/chat`) untuk koordinasi penghentian pencucian.\n\n' +
      '3. **Tahap Pengantaran (*OUT_FOR_DELIVERY*):**\n' +
      '   • Pesanan tidak dapat dibatalkan karena pakaian sudah selesai dicuci dan sedang dibawa kurir ke alamat Anda.\n\n' +
      '💡 *Apakah pesanan Anda saat ini masih menunggu penimbangan atau sudah mulai dicuci? Silakan cek status terbarunya di menu Pesanan Saya.*'
    );
  }

  // 4. Kurir Belum Datang / Pengiriman Terlambat ("Kurir gue belum dateng", "Kurir blm dateng", "Kurir telat")
  if (
    (q.includes('kurir') && (q.includes('belum datang') || q.includes('belum') || q.includes('lama') || q.includes('kapan') || q.includes('jemput'))) ||
    q.includes('belum jemput') ||
    q.includes('kapan jemput')
  ) {
    return (
      '🛵 **Panduan Jika Kurir Belum Tiba di Lokasi Anda:**\n\n' +
      '1. **Pantau Status Real-Time di Menu Pesanan Saya (`/orders`):**\n' +
      '   • *PENDING_PICKUP*: Pesanan baru dibuat, pihak toko sedang mencari dan menugaskan kurir terdekat.\n' +
      '   • *PICKUP_ASSIGNED*: Kurir telah menerima tugas dan sedang dalam perjalanan menuju alamat Anda.\n\n' +
      '2. **Hubungi Toko atau Kurir Langsung:**\n' +
      '   • Buka menu **Chat Toko** (`/chat`) untuk mengonfirmasi perkiraan waktu kedatangan kurir atau memberikan patokan alamat rumah Anda agar kurir lebih mudah menemukan lokasi.\n\n' +
      '💡 *Apakah Anda sedang menunggu kurir untuk penjemputan pakaian kotor atau pengantaran cucian bersih?*'
    );
  }

  // 5. Customer Service & Kontak CS ("Cara hubungi CS?", "Cara hubungi CS gimana?", "Kontak admin")
  if (
    q.includes('hubungi cs') ||
    q.includes('kontak cs') ||
    (q.includes('cs') && (q.includes('cara') || q.includes('mana') || q.includes('gimana') || q.includes('nomor') || q.includes('bantuan'))) ||
    q.includes('customer service')
  ) {
    return (
      '📞 **Cara Menghubungi Customer Service (CS) & Bantuan LaundryKu:**\n\n' +
      '1. **Komunikasi Langsung dengan Toko & Kurir (Paling Cepat):**\n' +
      '   • Buka menu **Pesan & Chat Toko** (`/chat`) di navbar atas untuk berkomunikasi langsung dengan outlet laundry atau kurir yang menangani pesanan Anda.\n\n' +
      '2. **Pengaduan Resmi Kendala Pesanan:**\n' +
      '   • Buka menu **Pesanan Saya** (`/orders`) -> pilih pesanan terkait -> klik **Ajukan Komplain** untuk kendala pakaian, selisih timbangan, atau masalah kurir yang butuh mediasi tim platform.\n\n' +
      '3. **Layanan Bantuan Resmi Platform:**\n' +
      '   • Kunjungi menu **Profil** (`/profile`) untuk menemukan kontak resmi WhatsApp Customer Care (CS) LaundryKu untuk kendala teknis akun atau pembayaran sistem.'
    );
  }

  // 6. Komplain & Kerusakan Pakaian ("Gue mau komplain", "Mau komplain", "Baju gue rusak", "Luntur")
  if (
    q.includes('komplain') ||
    (q.includes('pakaian') && (q.includes('rusak') || q.includes('luntur') || q.includes('hilang') || q.includes('noda'))) ||
    (q.includes('rusak') && q.includes('cuci')) ||
    q.includes('tidak bersih')
  ) {
    return (
      '⚠️ **Panduan Pengajuan Komplain & Klaim Pakaian:**\n\n' +
      '1. **Ambil Foto Bukti:** Foto kondisi pakaian yang bermasalah secara jelas (bagian sobek, luntur, noda tertinggal, atau kancing lepas).\n' +
      '2. **Ajukan Komplain Resmi:** Buka menu **Pesanan Saya** (`/orders`) -> pilih pesanan terkait -> klik tombol **Ajukan Komplain** (tersedia maksimal **1x24 jam** setelah status pesanan selesai/*COMPLETED*).\n' +
      '3. **Penyelesaian Garansi:** Admin toko mitra dan platform akan meninjau laporan untuk solusi pencucian ulang bebas biaya atau penggantian kompensasi.\n' +
      '4. **Diskusi dengan Toko:** Anda juga dapat mengirim foto dan berdiskusi langsung dengan kasir toko melalui menu **Chat Toko** (`/chat`).\n\n' +
      '💡 *Boleh tahu apakah kendala Anda terkait pakaian rusak/luntur, barang tertukar, atau hasil cucian yang kurang bersih?*'
    );
  }

  // 7. Pengembalian Dana / Refund ("Uang gue balik ga?", "Uang gue bisa balik nggak?", "Refund")
  if (
    q.includes('refund') ||
    (q.includes('uang') && (q.includes('balik') || q.includes('kembali') || q.includes('hangus')))
  ) {
    return (
      '💰 **Ketentuan & Prosedur Pengembalian Dana (Refund) di LaundryKu:**\n\n' +
      '1. **Pembatalan di Tahap Penolakan Harga:** Jika Anda membatalkan pesanan di tahap persetujuan harga timbangan (*AWAITING_PRICE_APPROVAL*) atau sebelum pembayaran diproses, saldo Anda aman dan tidak ada dana yang terpotong.\n' +
      '2. **Pesanan yang Sudah Dibayar Dibatalkan / Komplain Disetujui:** Dana Anda berhak dikembalikan (refund) jika pesanan dibatalkan oleh pihak toko atau pengajuan komplain Anda disetujui dalam mediasi platform.\n' +
      '3. **Alur & Estimasi Refund:** Pengembalian dana diproses via payment gateway Xendit ke rekening bank atau saldo e-wallet asal Anda dalam waktu 1-3 hari kerja.\n\n' +
      '⚠️ *Catatan: Asisten AI tidak dapat menyatakan transaksi refund telah berhasil tanpa verifikasi data sistem. Anda dapat memantau status di menu Pesanan Saya (`/orders`) atau hubungi toko via Chat Toko.*'
    );
  }

  // 8. Pengerjaan Laundry Terasa Lama ("Laundry gue udah lama banget", "Laundry gue belum selesai", "Pesanan kok lama")
  if (
    (q.includes('laundry') && (/\blama\b/.test(q) || q.includes('belum selesai') || q.includes('kapan selesai'))) ||
    (q.includes('pesanan') && /\blama\b/.test(q))
  ) {
    return (
      '⏱️ **Panduan Jika Pengerjaan Laundry Terasa Lama:**\n\n' +
      '1. **Cek Estimasi Paket Layanan:**\n' +
      '   • Layanan Regular Kiloan / Satuan umumnya membutuhkan waktu 1-3 hari kerja sesuai antrean toko.\n' +
      '   • Layanan Express diproses kilat prioritas selesai dalam 6-24 jam.\n' +
      '2. **Pantau Tahapan Pengerjaan di Menu Pesanan Saya (`/orders`):**\n' +
      '   • Anda dapat melihat apakah pakaian sedang dalam proses cuci (*WASHING*), pengeringan (*DRYING*), penyetrikaan (*IRONING*), atau sudah siap diantar (*READY_FOR_DELIVERY*).\n' +
      '3. **Hubungi Toko Langsung:** Jika waktu pengerjaan melebihi estimasi, tanyakan langsung status antrean cucian Anda ke staf toko via menu **Chat Toko** (`/chat`).\n\n' +
      '💡 *Boleh tahu apakah pesanan Anda menggunakan paket Reguler atau Express?*'
    );
  }

  // 9. Pembayaran Bermasalah / Gagal ("Pembayaran gue bermasalah", "Pembayaran gue gagal", "Bayar gagal")
  if (
    (q.includes('pembayaran') || q.includes('bayar')) &&
    (q.includes('bermasalah') || q.includes('gagal') || q.includes('error') || q.includes('tidak bisa'))
  ) {
    return (
      '💳 **Solusi Kendala Pembayaran di LaundryKu:**\n\n' +
      '1. **Pembayaran Gagal atau Waktu Habis (Expired):**\n' +
      '   • Buka menu **Pesanan Saya** (`/orders`), pilih pesanan terkait, lalu klik tombol **Bayar Sekarang** untuk membuat kode QRIS atau nomor Virtual Account baru.\n\n' +
      '2. **Saldo Terpotong tapi Status Masih Belum Berubah:**\n' +
      '   • Sistem payment gateway Xendit membutuhkan waktu 1-5 menit untuk sinkronisasi webhook otomatis. Segarkan (*refresh*) halaman `/orders`.\n' +
      '   • Jika lewat 15 menit belum berubah, jangan bayar ulang; kirimkan foto bukti transfer ke admin toko melalui menu **Chat Toko** (`/chat`) agar diverifikasi manual.\n\n' +
      '3. **Opsi Tunai (COD):** Anda juga dapat memilih metode Cash on Delivery (bayar tunai ke kurir saat pengantaran).'
    );
  }

  // 10. Pembayaran Pending / Sudah Bayar tapi Belum Berubah
  if (
    (q.includes('bayar') || q.includes('pembayaran')) &&
    (q.includes('pending') || q.includes('belum masuk') || q.includes('belum berubah') || q.includes('sudah transfer'))
  ) {
    return (
      '💳 **Solusi Status Pembayaran Masih Pending:**\n\n' +
      '1. **Waktu Sinkronisasi Otomatis:**\n' +
      '   • Pembayaran via QRIS & Virtual Account Xendit biasanya tersinkronisasi dalam 1-5 menit ke sistem LaundryKu.\n' +
      '2. **Langkah Pengecekan:**\n' +
      '   • Pastikan saldo rekening atau e-wallet Anda sudah terpotong dan simpan struk transfer.\n' +
      '   • Buka menu **Pesanan Saya** (`/orders`) lalu segarkan (*refresh*) halaman.\n' +
      '3. **Jika Status Belum Berubah Setelah 15 Menit:**\n' +
      '   • Jangan bayar ganda. Hubungi toko via menu **Chat Toko** (`/chat`) dengan mengirim nomor pesanan dan foto bukti transfer agar dikonfirmasi manual oleh toko.'
    );
  }

  // 11. Ubah Alamat / Alamat Pesanan Salah ("Bisa ubah alamat nggak?", "Alamat pesanan salah")
  if (
    q.includes('alamat') &&
    (q.includes('ubah') || q.includes('ganti') || q.includes('salah') || q.includes('pindah'))
  ) {
    return (
      '📍 **Panduan Perubahan & Kendala Alamat Penjemputan / Pengantaran:**\n\n' +
      '1. **Pesanan yang Sedang Berjalan:**\n' +
      '   • Jika pesanan sudah dibuat dan kurir belum berangkat menjemput/mengantar, segera buka menu **Chat Toko** (`/chat`) dan informasikan patokan alamat yang benar langsung ke pihak toko atau kurir (selama masih dalam radius 10 km dari toko).\n\n' +
      '2. **Ubah Alamat Akun untuk Pesanan Berikutnya:**\n' +
      '   • Anda dapat memperbarui detail jalan, catatan patokan, dan titik koordinat GPS alamat utama Anda kapan saja di menu **Profil & Alamat** (`/profile`).\n\n' +
      '💡 *Apakah kurir saat ini sedang menuju lokasi Anda untuk menjemput atau mengantar cucian?*'
    );
  }

  // 12. Status Pesanan Belum Berubah / Stuck ("Pesanan gue belum berubah status")
  if (
    q.includes('status') &&
    (q.includes('belum berubah') || q.includes('tidak berubah') || q.includes('stuck') || q.includes('sama saja'))
  ) {
    return (
      '🔄 **Penjelasan Mengapa Status Pesanan Belum Berubah:**\n\n' +
      '1. **Pembaruan Manual di Toko:** Perubahan status (seperti dari *PICKED_UP* ke *AT_STORE*, *WEIGHED*, lalu *PROCESSING*) diperbarui oleh staf toko sesuai antrean pengerjaan fisik cucian di outlet.\n' +
      '2. **Antrean Penimbangan & Cuci:** Jika status masih di tahap outlet (*AT_STORE*), pakaian Anda kemungkinan sedang dalam antrean timbang atau pemilahan bahan kain.\n' +
      '3. **Konfirmasi Langsung:** Jika status tidak berubah melebihi batas waktu wajar, kirim pesan ke kasir toko melalui menu **Chat Toko** (`/chat`) untuk memastikan progres antrean pakaian Anda.'
    );
  }

  // 13. Pendaftaran Mitra Toko ("Gimana cara daftar jadi mitra?", "Gimana cara daftar mitra?")
  if (
    q.includes('daftar mitra') ||
    q.includes('jadi mitra') ||
    q.includes('gabung mitra') ||
    (q.includes('daftar') && q.includes('toko')) ||
    (q.includes('buka') && q.includes('mitra')) ||
    (q.includes('buka toko') && !q.includes('jam') && !q.includes('hari') && !q.includes('pas') && !q.includes('ga') && !q.includes('tidak'))
  ) {
    return (
      '🏪 **Panduan & Syarat Menjadi Mitra Toko LaundryKu:**\n\n' +
      '**1. Cara Mendaftar:**\n' +
      '• Buka menu **Gabung Mitra** (`/upgrade`) lalu pilih tab **Pendaftaran Toko**.\n' +
      '• Isi data toko: Nama Outlet, Alamat Lengkap, Titik Koordinat GPS (radius jangkauan maks 10 km), Jam Operasional, dan Kontak WhatsApp.\n' +
      '• Unggah dokumen pendukung: Foto KTP Pemilik, Foto Fisik Outlet Toko, dan Dokumen NIB/Izin Usaha (jika ada).\n\n' +
      '**2. Waktu Verifikasi:**\n' +
      '• Tim Platform Admin memvalidasi data toko dalam waktu **1x24 jam kerja**.\n\n' +
      '**3. Setelah Disetujui:**\n' +
      '• Anda dapat mengakses **Dashboard Toko** untuk mengatur paket layanan (kiloan/satuan/express), harga per kg, minimal order, menerima order baru, input timbangan riil, dan menugaskan kurir toko.'
    );
  }

  // 14. Pendaftaran Kurir ("Mau jadi kurir", "Cara jadi kurir gimana?")
  if (
    (q.includes('daftar') || q.includes('jadi') || q.includes('mau')) &&
    q.includes('kurir')
  ) {
    return (
      '🛵 **Panduan & Syarat Bergabung Sebagai Kurir LaundryKu:**\n\n' +
      '**1. Persyaratan yang Diperlukan:**\n' +
      '• KTP & NIK valid.\n' +
      '• SIM C aktif.\n' +
      '• Sepeda motor pribadi dengan plat nomor kendaraan yang valid.\n' +
      '• Nomor WhatsApp aktif untuk koordinasi penjemputan/pengantaran.\n\n' +
      '**2. Cara Mendaftar:**\n' +
      '• Buka menu **Gabung Mitra** (`/upgrade`) -> pilih tab **Pengajuan Kurir**.\n' +
      '• Lengkapi formulir identitas, data kendaraan, dan unggah foto SIM C serta KTP.\n' +
      '• Verifikasi diproses dalam waktu **1x24 jam kerja** oleh admin toko atau platform.\n\n' +
      '**3. Tugas Kurir:** Menerima penugasan jemput (*Pickup*), antar (*Delivery*), upload bukti serah terima foto, dan menerima pembayaran tunai COD.'
    );
  }

  // 15. Metode Pembayaran Resmi yang Didukung
  if (
    q.includes('metode pembayaran') ||
    q.includes('cara bayar') ||
    q.includes('bayar pakai') ||
    q.includes('bisa bayar') ||
    q.includes('qris') ||
    q.includes('virtual account') ||
    /\bcod\b/i.test(q)
  ) {
    return (
      '💳 **Metode Pembayaran Resmi di LaundryKu:**\n\n' +
      '1. **QRIS Instan:**\n' +
      '   • Scan QR code melalui aplikasi GoPay, OVO, DANA, ShopeePay, LinkAja, BCA Mobile, Livin Mandiri, dan semua m-Banking berstandar QRIS.\n' +
      '2. **Virtual Account (VA Bank):**\n' +
      '   • Transfer otomatis melalui bank BCA, Mandiri, BNI, BRI, atau Permata via payment gateway Xendit dengan konfirmasi instan.\n' +
      '3. **COD (Cash on Delivery):**\n' +
      '   • Bayar tunai langsung kepada kurir saat cucian diantar kembali ke lokasi Anda tanpa biaya tambahan.'
    );
  }

  // 15b. Informasi Harga & Tarif Layanan (Cuci Kiloan, Satuan, Express)
  if (
    q.includes('harga') ||
    q.includes('tarif') ||
    q.includes('biaya cuci') ||
    q.includes('kiloan') ||
    q.includes('per kilo') ||
    q.includes('perkilo') ||
    q.includes('berapa harga') ||
    q.includes('ongkos cuci')
  ) {
    return (
      '🏷️ **Informasi Harga & Tarif Layanan LaundryKu:**\n\n' +
      'Setiap outlet mitra toko laundry di LaundryKu menetapkan tarif layanannya secara transparan yang dapat dilihat langsung pada profil masing-masing toko:\n\n' +
      '• **Cuci Kiloan (Reguler):** Umumnya berkisar antara **Rp 6.000 – Rp 10.000 / kg** (waktu pengerjaan 2–3 hari kerja).\n' +
      '• **Cuci Kiloan (Express):** Berkisar antara **Rp 12.000 – Rp 18.000 / kg** (selesai dalam 6–24 jam prioritas).\n' +
      '• **Cuci Satuan / Dry Clean:** Dihitung per helai pakaian (misal: jas, selimut, bedcover, gamis) sesuai rincian pada toko.\n' +
      '• **Transparansi Penimbangan:** Biaya awal saat checkout adalah estimasi. Total harga final yang wajib dibayar dihitung berdasarkan **timbangan riil** saat cucian tiba di toko dan wajib disetujui di menu Pesanan Saya (`/orders`).\n\n' +
      'Untuk melihat daftar harga lengkap toko terdekat di sekitar Anda, buka menu **Cari Toko** (`/stores`).'
    );
  }

  // 16. Cara Pesan Laundry & Estimasi Biaya
  if (
    q.includes('cara pesan') ||
    q.includes('cara memesan') ||
    q.includes('buat pesanan') ||
    q.includes('alur pemesanan') ||
    q.includes('estimasi biaya') ||
    q.includes('timbangan')
  ) {
    return (
      '📋 **Panduan Membuat Pesanan & Sistem Penimbangan LaundryKu:**\n\n' +
      '**1. Langkah Pemesanan:**\n' +
      '• Buka menu **Cari Toko** (`/stores`) dan pilih outlet laundry terdekat dalam radius 10 km.\n' +
      '• Pilih paket layanan (Kiloan, Satuan, Dry Clean, atau Express).\n' +
      '• Masukkan estimasi berat (misal 3 kg) dan tentukan alamat penjemputan Anda.\n' +
      '• Pilih opsi pembayaran (QRIS, VA Bank, atau COD) lalu klik **Pesan Sekarang**.\n\n' +
      '**2. Transparansi Penimbangan:**\n' +
      '• Biaya awal adalah estimasi perkiraan.\n' +
      '• Kurir menjemput cucian dan membawanya ke outlet toko.\n' +
      '• Admin toko menimbang riil dengan timbangan terkalibrasi dan mengunggah rincian harga final.\n' +
      '• Anda menerima notifikasi tagihan final: Anda berhak **Menyetujui Harga Final** atau **Membatalkan Pesanan** bebas biaya jika harga tidak sesuai!'
    );
  }

  // 17. Pendaftaran Akun Pelanggan (Customer)
  if (
    q.includes('daftar akun') ||
    q.includes('buat akun') ||
    q.includes('registrasi') ||
    q.includes('bikin akun') ||
    q.includes('cara daftar') ||
    (q.includes('daftar') && !q.includes('mitra') && !q.includes('kurir'))
  ) {
    return (
      '📱 **Panduan Mendaftar Akun di LaundryKu:**\n\n' +
      '1. Klik menu **Masuk / Daftar** (`/auth`) di navbar pojok kanan atas.\n' +
      '2. Pilih tab **Daftar Akun Baru**.\n' +
      '3. Masukkan **Nama Lengkap** dan **Nomor WhatsApp** aktif (atau Email).\n' +
      '4. Klik tombol **Daftar & Dapatkan OTP**.\n' +
      '5. Masukkan 6 digit kode OTP yang diterima via WhatsApp/Email. Akun Anda langsung aktif dengan sistem **Passwordless OTP** tanpa perlu mengingat password!'
    );
  }

  // 18. Perbedaan Status Pesanan (Processing vs Ready vs Out)
  if (
    (q.includes('beda') && q.includes('status')) ||
    (q.includes('arti') && q.includes('status')) ||
    (q.includes('proses') && q.includes('siap diantar'))
  ) {
    return (
      '🔄 **Penjelasan Perbedaan Status Pesanan di LaundryKu:**\n\n' +
      '• **PROCESSING (Sedang Diproses/Dicuci):** Pakaian Anda sedang dalam penanganan aktif oleh tim toko (tahap pencucian *washing*, pengeringan *drying*, atau setrika/lipat rapi *ironing*).\n' +
      '• **READY_FOR_DELIVERY (Siap Diantar):** Proses pencucian dan pengepakan selesai 100%. Pakaian sudah bersih, harum, dan dipacking rapi menunggu kurir mengambilnya di outlet.\n' +
      '• **OUT_FOR_DELIVERY (Sedang Diantar):** Kurir sudah membawa paket pakaian bersih Anda dan sedang dalam perjalanan menuju alamat pengantaran Anda.'
    );
  }

  // 19. Melacak Status Pesanan (Guest Guidance vs Authenticated)
  if (
    q.includes('lacak pesanan') ||
    q.includes('melacak pesanan') ||
    q.includes('cara melacak') ||
    q.includes('cara lacak') ||
    q.includes('lacak') ||
    q.includes('melacak') ||
    q.includes('tracking') ||
    q.includes('cek pesanan') ||
    q.includes('status pesanan') ||
    q.includes('pesanan saya') ||
    q.includes('cucian saya')
  ) {
    return (
      '🔍 **Cara Melacak Status Pesanan Cucian Anda:**\n\n' +
      '• **Untuk Pengguna Tamu (Belum Login):** Jika Anda saat ini belum login, silakan masuk terlebih dahulu melalui menu **Masuk / Daftar** (`/auth`). Demi privasi dan keamanan akun, asisten AI tidak dapat mengakses riwayat transaksi pribadi tanpa autentikasi yang sah.\n\n' +
      '• **Cek Melalui Menu Pesanan Saya (`/orders`):** Setelah masuk ke akun, Anda dapat melihat rincian progres cucian secara langsung:\n' +
      '   - *PENDING_PICKUP*: Toko sedang mencari/menugaskan kurir terdekat.\n' +
      '   - *PICKUP_ASSIGNED*: Kurir telah ditugaskan dan sedang menuju alamat Anda.\n' +
      '   - *PICKED_UP / AT_STORE*: Cucian telah diambil kurir dan tiba di outlet toko.\n' +
      '   - *WEIGHED*: Cucian selesai ditimbang dan menunggu persetujuan harga final Anda.\n' +
      '   - *PROCESSING*: Pakaian sedang dicuci, dikeringkan, atau disetrika.\n' +
      '   - *READY_FOR_DELIVERY / OUT_FOR_DELIVERY*: Pakaian bersih siap atau sedang diantar ke alamat Anda.\n\n' +
      '• **Hubungi Toko Langsung:** Anda juga dapat memanfaatkan fitur **Chat Toko** (`/chat`) untuk mengonfirmasi perkiraan waktu tiba kurir atau patokan alamat penjemputan.'
    );
  }

  // 20. Out-of-Scope Redirection (Polite redirection)
  if (
    q.includes('resep') ||
    q.includes('presiden') ||
    q.includes('politik') ||
    q.includes('pemilu') ||
    q.includes('cuaca') ||
    q.includes('saham') ||
    q.includes('crypto') ||
    q.includes('game') ||
    q.includes('film') ||
    q.includes('chord') ||
    q.includes('ngoding') ||
    q.includes('coding') ||
    q.includes('code') ||
    q.includes('javascript') ||
    q.includes('python')
  ) {
    return 'Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Layanan ini difokuskan untuk membantu seluruh penggunaan marketplace LaundryKu. Mau tanya soal itu?';
  }

  // 21. Specific Information Not Known to System (Threshold Relevansi)
  const isLaundryRelevant =
    q.includes('laundry') ||
    q.includes('cucian') ||
    q.includes('cuci') ||
    q.includes('pesan') ||
    q.includes('order') ||
    q.includes('toko') ||
    q.includes('outlet') ||
    q.includes('kurir') ||
    q.includes('batal') ||
    q.includes('bayar') ||
    q.includes('harga') ||
    q.includes('tarif') ||
    q.includes('buka') ||
    q.includes('tutup') ||
    q.includes('operasional') ||
    q.includes('jam') ||
    q.includes('libur');

  if (isLaundryRelevant) {
    return (
      'Informasi spesifik tersebut tergantung pada outlet toko laundry mitra yang bersangkutan. Di platform LaundryKu, setiap toko memiliki jam operasional, kebijakan hari libur, dan tarif layanan masing-masing yang dapat Anda cek secara langsung pada kartu profil toko di menu **Cari Toko** (`/stores`).\n\n' +
      'Anda juga dapat menanyakan hal tersebut langsung ke pihak toko melalui fitur **Chat Toko** (`/chat`). Ada hal lain seputar layanan LaundryKu yang bisa saya bantu jelaskan?'
    );
  }

  return 'Maaf, aku cuma bisa bantu soal layanan LaundryKu, seperti cara pesan, pembayaran, status cucian, dan harga. Layanan ini difokuskan untuk membantu seluruh penggunaan marketplace LaundryKu. Mau tanya soal itu?';
}

export async function POST(req: NextRequest) {
  try {
    let body: any = {};
    try {
      body = await req.json();
    } catch {
      try {
        const text = await req.text();
        body = JSON.parse(text);
      } catch {
        body = {};
      }
    }

    const userMessage =
      typeof body.message === 'string'
        ? body.message
        : Array.isArray(body.messages) && body.messages.length > 0
        ? body.messages[body.messages.length - 1]?.content || ''
        : '';

    if (!userMessage.trim()) {
      return NextResponse.json(
        { success: false, error: { message: 'Pesan tidak boleh kosong.' } },
        { status: 400 }
      );
    }

    // 1. Read environment variables (server-side only, no NEXT_PUBLIC_ prefix)
    const apiKey = process.env.AI_API_KEY || process.env.OPENAI_API_KEY || '';
    let baseUrl = process.env.AI_BASE_URL || process.env.OPENAI_BASE_URL || 'https://openrouter.ai/api/v1';
    const model = process.env.AI_MODEL || process.env.OPENAI_MODEL || 'liquid/lfm-2.5-2.6b:free';
    const timeoutSeconds = parseInt(process.env.AI_TIMEOUT || '30', 10);
    const mockInLocal = process.env.AI_MOCK_IN_LOCAL === 'true';

    // 2. Normalize Base URL
    if (baseUrl.includes('openrouter.io')) {
      baseUrl = baseUrl.replace('openrouter.io', 'openrouter.ai');
    }
    baseUrl = baseUrl.replace(/\/+$/, '');
    const endpointUrl = `${baseUrl}/chat/completions`;

    // 3. Prepare multi-turn message history for OpenAI-compatible payload
    const rawHistory = Array.isArray(body.messages) ? body.messages : [];
    const validHistory = rawHistory
      .filter(
        (m: any) =>
          m &&
          typeof m === 'object' &&
          (m.role === 'user' || m.role === 'assistant') &&
          typeof m.content === 'string'
      )
      .map((m: any) => ({ role: m.role as 'user' | 'assistant', content: m.content }));

    const isLastMessageInHistory =
      validHistory.length > 0 &&
      validHistory[validHistory.length - 1].role === 'user' &&
      validHistory[validHistory.length - 1].content === userMessage;

    const messagesPayload = [
      { role: 'system', content: SYSTEM_PROMPT },
      ...validHistory,
      ...(isLastMessageInHistory ? [] : [{ role: 'user', content: userMessage }]),
    ];

    const isApiKeyInvalid =
      !apiKey ||
      apiKey.length < 10 ||
      apiKey.includes('...') ||
      apiKey === 'dummy_openai_api_key_placeholder';

    // 4. Fallback if API key is not configured or in local mock mode
    if (isApiKeyInvalid) {
      console.warn(
        `[Next.js /api/chat Warning] API Key tidak terkonfigurasi/terpotong (panjang: ${apiKey?.length || 0}). Menggunakan Asisten Pengetahuan Lokal LaundryKu.`
      );

      const localAnswer = getLocalKnowledgeReply(userMessage, validHistory);
      return NextResponse.json({
        success: true,
        data: {
          question: userMessage,
          answer: localAnswer,
          is_fallback: true,
          provider: 'local_knowledge_engine',
        },
      });
    }

    // 5. Attempt remote AI provider call
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeoutSeconds * 1000);

    try {
      const response = await fetch(endpointUrl, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${apiKey}`,
          'Content-Type': 'application/json',
          'HTTP-Referer': process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
          'X-Title': 'LaundryKu',
        },
        body: JSON.stringify({
          model,
          messages: messagesPayload,
          temperature: 0.4,
          max_tokens: 1200,
        }),
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (response.ok) {
        const data = await response.json();
        const content = data?.choices?.[0]?.message?.content;

        if (content && typeof content === 'string' && content.trim().length > 0) {
          return NextResponse.json({
            success: true,
            data: {
              question: userMessage,
              answer: content.trim(),
              is_fallback: false,
              provider: 'openrouter',
            },
          });
        }
      }

      // Log non-OK status code & response snippet
      const errorText = await response.text();
      console.error(
        `[Next.js /api/chat Error] Provider status: ${response.status} ${response.statusText} | URL: ${endpointUrl} | Body: ${errorText.slice(0, 300)}`
      );

      // Graceful fallback to local knowledge
      const fallbackAnswer = getLocalKnowledgeReply(userMessage, validHistory);
      return NextResponse.json({
        success: true,
        data: {
          question: userMessage,
          answer: fallbackAnswer,
          is_fallback: true,
          provider: 'local_fallback',
        },
      });
    } catch (fetchErr: any) {
      clearTimeout(timeoutId);
      console.error(`[Next.js /api/chat Exception] Error connecting to ${endpointUrl}:`, fetchErr.message);

      const fallbackAnswer = getLocalKnowledgeReply(userMessage, validHistory);
      return NextResponse.json({
        success: true,
        data: {
          question: userMessage,
          answer: fallbackAnswer,
          is_fallback: true,
          provider: 'local_fallback',
        },
      });
    }
  } catch (err: any) {
    console.error('[Next.js /api/chat Fatal]', err);
    return NextResponse.json(
      {
        success: false,
        error: {
          message: 'Terjadi kesalahan saat memproses permintaan bantuan AI.',
        },
      },
      { status: 500 }
    );
  }
}
