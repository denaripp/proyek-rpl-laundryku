<?php

return [
    'guide_store_orders' => [
        'id' => 'guide_store_orders',
        'role' => 'store_admin',
        'title' => 'Panduan Mengelola Pesanan Masuk di Toko Mitra',
        'keywords' => [
            'pesanan masuk', 'kelola pesanan', 'proses pesanan', 'update status order',
            'pesanan toko', 'orderan toko', 'tahap cuci toko', 'pesanan diterima',
        ],
        'steps' => [
            'Buka "Dashboard Mitra" (/store) dan masuk ke menu "Pesanan Toko" (/store/orders).',
            'Saat kurir menyerahkan cucian kotor ke outlet toko, periksa kesesuaian fisik pakaian lalu ubah status pesanan menjadi "Diterima di Toko" (received_by_store).',
            'Lakukan penimbangan berat riil (kiloan) dan catat item pakaian di aplikasi.',
            'Setelah pelanggan melunasi tagihan, masukkan cucian ke antrean cuci dan ubah status ke "Sedang Diproses / Dicuci" (processing).',
            'Setelah cucian selesai dicuci, dikeringkan, disetrika, dan dikemas rapi, ubah status pesanan menjadi "Siap Diantar" (ready_for_delivery). Kurir pengantaran akan ditugaskan otomatis.',
        ],
        'note' => 'Pembaruan status yang disiplin menjaga kepuasan dan kepercayaan pelanggan toko Anda.',
    ],

    'guide_store_weighing' => [
        'id' => 'guide_store_weighing',
        'role' => 'store_admin',
        'title' => 'Panduan Penimbangan Cucian & Penetapan Tagihan Final Toko',
        'keywords' => [
            'timbang cucian', 'cara timbang', 'input berat', 'berat riil',
            'penimbangan cucian', 'masukkan berat', 'tagihan final',
        ],
        'steps' => [
            'Di halaman "Pesanan Toko" (/store/orders), pilih pesanan yang baru tiba di outlet.',
            'Klik tombol "Input Timbangan & Rincian".',
            'Timbang cucian pada timbangan akurat toko, lalu masukkan angka berat riil dalam kilogram (kg) serta jumlah potong/helai pakaian.',
            'Jika ada layanan tambahan (misal selimut atau dry clean), masukkan item layanan terkait.',
            'Sistem akan otomatis menghitung total harga final berdasarkan tarif aktif toko Anda.',
            'Klik "Simpan Timbangan". Notifikasi tagihan akan otomatis dikirimkan ke aplikasi pelanggan untuk disetujui dan dibayar.',
        ],
        'note' => 'Penimbangan yang transparan dan akurat mencegah perselisihan atau komplain di kemudian hari.',
    ],

    'guide_store_services' => [
        'id' => 'guide_store_services',
        'role' => 'store_admin',
        'title' => 'Panduan Menambah & Mengatur Layanan Serta Harga Toko',
        'keywords' => [
            'tambah layanan', 'atur harga', 'daftar harga toko', 'layanan toko',
            'edit tarif', 'ubah harga', 'menu layanan toko', 'tmbah lyanan',
        ],
        'steps' => [
            'Masuk ke "Dashboard Mitra" (/store) dan klik menu "Layanan & Harga" (/store/services).',
            'Untuk menambah layanan baru: klik tombol "Tambah Layanan Baru".',
            'Pilih kategori layanan (Cuci Komplit Kiloan, Dry Clean, Setrika Saja, Satuan/Khusus).',
            'Tentukan satuan hitung (per kg atau per potong), masukkan tarif harga per unit (Rp), dan estimasi lama pengerjaan (jam/hari).',
            'Klik tombol "Simpan Layanan". Layanan dan tarif terbaru akan langsung tampil pada profil publik toko Anda di marketplace.',
        ],
        'note' => 'Anda dapat mengaktifkan atau menonaktifkan layanan tertentu kapan saja dari daftar layanan.',
    ],

    'guide_store_hours' => [
        'id' => 'guide_store_hours',
        'role' => 'store_admin',
        'title' => 'Panduan Mengatur Jam Buka & Operasional Toko Laundry',
        'keywords' => [
            'jam operasional', 'jam buka toko', 'atur jam buka', 'tutup toko',
            'pengaturan toko', 'jam kerja toko', 'operasional toko', 'atur jm',
        ],
        'steps' => [
            'Buka "Dashboard Mitra" (/store) dan klik menu "Pengaturan Toko" (/store/settings).',
            'Pilih bagian "Jam Operasional & Hari Buka".',
            'Tentukan hari-hari toko buka (misal: Senin - Sabtu) serta jam operasional buka dan tutup harian (misal: 08:00 - 20:00 WIB).',
            'Anda juga dapat mengatur radius layanan jemput-antar (km) dan nomor kontak operasional toko.',
            'Klik "Simpan Perubahan". Jadwal jam buka toko akan otomatis diperbarui pada profil publik toko Anda.',
        ],
        'note' => 'Jika toko sedang libur atau renovasi, Anda dapat mengubah status toko menjadi nonaktif sementara dari menu ini.',
    ],

    'guide_store_complaints' => [
        'id' => 'guide_store_complaints',
        'role' => 'store_admin',
        'title' => 'Panduan Menangani Komplain Pelanggan di Toko Mitra',
        'keywords' => [
            'komplain toko', 'keluhan pelanggan', 'tangani komplain', 'respons komplain',
            'dispute toko', 'klaim pelanggan', 'komplain di toko',
        ],
        'steps' => [
            'Di "Dashboard Mitra" (/store), buka menu "Komplain Pelanggan" (/store/complaints).',
            'Pilih keluhan pelanggan yang masuk (berlabel "Menunggu Tanggapan Toko"). Pelanggan berhak mengajukan komplain dalam jendela waktu 1x24 jam setelah pesanan diterima (delivered).',
            'Periksa nomor pesanan, deskripsi masalah (rusak/luntur/tertukar), dan bukti foto yang dilampirkan pelanggan.',
            'Berikan tanggapan resmi dari toko: Anda dapat menawarkan cuci ulang gratis, kompensasi ganti rugi, atau penjelasan klarifikasi.',
            'Jika tercapai kesepakatan, konfirmasi penyelesaian komplain bersama tim penengah LaundryKu.',
        ],
        'note' => 'Merespons komplain dengan cepat dan sopan akan menjaga reputasi bintang dan rating toko Anda.',
    ],
];
