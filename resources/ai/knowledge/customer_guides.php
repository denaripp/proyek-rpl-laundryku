<?php

return [
    'guide_order' => [
        'id' => 'guide_order',
        'role' => 'customer',
        'title' => 'Panduan Cara Pesan Laundry / Memesan Laundry (Order Baru)',
        'keywords' => [
            'pesan laundry', 'cara pesan', 'order baru', 'buat pesanan', 'cara order',
            'pesan cuci', 'mau pesan', 'gimana cara pesan', 'langkah pesan',
            'cara memesan', 'tutorial order', 'cara ordr', 'cra psn', 'jemput cucian',
            'bisa pesan', 'pesan jemput', 'jemput pesanan', 'bisa jemput', 'jemput ke rumah',
        ],
        'steps' => [
            'Buka menu "Cari Toko" (/stores) di aplikasi LaundryKu.',
            'Pilih outlet toko laundry terdekat yang Anda inginkan, lalu pilih jenis layanan (Cuci Komplit Kiloan, Dry Clean, atau Setrika Saja).',
            'Pilih atau tambahkan alamat penjemputan Anda (bisa gunakan fitur GPS otomatis) dan tentukan catatan khusus.',
            'Konfirmasi pesanan. Kurir akan ditugaskan untuk menjemput pakaian kotor ke lokasi Anda.',
            'Setelah tiba di toko, pakaian akan ditimbang dan rincian harga final akan terbit di aplikasi untuk Anda bayar.',
        ],
        'note' => 'Penjemputan dilakukan oleh kurir resmi LaundryKu langsung ke alamat Anda.',
    ],

    'guide_payment' => [
        'id' => 'guide_payment',
        'role' => 'customer',
        'title' => 'Panduan Cara Pembayaran Pesanan',
        'keywords' => [
            'cara bayar', 'metode pembayaran', 'cara pembayaran', 'bayar laundry',
            'bayar pesanan', 'qris', 'virtual account', 'bayar cod', 'cra byr',
            'gimana bayarnya', 'tata cara bayar', 'bayar tagihan',
        ],
        'steps' => [
            'Buka menu "Pesanan Saya" (/orders) dan pilih pesanan yang sudah berstatus "Menunggu Pembayaran" (setelah selesai ditimbang toko).',
            'Klik tombol "Bayar Sekarang" pada rincian pesanan.',
            'Pilih metode pembayaran yang diinginkan:',
            '  a. Online Payment: QRIS (instan) atau Virtual Account Bank (BCA, Mandiri, BNI, BRI) dengan batas waktu bayar invoice 1 jam.',
            '  b. Cash on Delivery (COD): Pembayaran tunai langsung ke kurir saat pakaian bersih diantar (maksimum Rp500.000).',
            'Selesaikan pembayaran sesuai instruksi. Status pesanan akan otomatis terverifikasi dan masuk proses pencucian.',
        ],
        'note' => 'Untuk pembayaran COD, siapkan uang pas saat kurir tiba mengantarkan pakaian.',
    ],

    'guide_tracking' => [
        'id' => 'guide_tracking',
        'role' => 'customer',
        'title' => 'Panduan Cara Melacak Status & Posisi Pesanan',
        'keywords' => [
            'cara lacak', 'lacak pesanan', 'tracking pesanan', 'cara tracking',
            'posisi cucian', 'status pesanan', 'pantau cucian', 'cek tracking',
            'tahap pencucian', 'lacak status', 'daftar status pesanan', 'daftar status',
            'ada berapa status', 'jumlah status pesanan',
        ],
        'steps' => [
            'Buka menu "Pesanan Saya" (/orders) di bilah navigasi aplikasi.',
            'Pilih pesanan aktif yang ingin Anda lacak.',
            'Klik tombol atau tab "Lacak Pesanan" (/orders/{id}/tracking).',
            'Anda dapat melihat linimasa status riil pesanan yang mencakup tepat 19 status pesanan dalam 4 tahapan alur sistem:',
            '  a. Pra-Pencucian (10 status): Pesanan Dibuat (created), Menunggu Penjemputan (waiting_pickup), Kurir Ditugaskan (courier_assigned), Kurir Menuju Lokasi (courier_to_customer), Cucian Dijemput (picked_up), Dalam Perjalanan ke Toko (in_transit_to_store), Diterima di Toko (received_by_store), Selesai Ditimbang (weighed), Menunggu Pembayaran (payment_required), Pembayaran Berhasil (paid).',
            '  b. Pencucian (1 status): Sedang Dicuci & Diproses (processing).',
            '  c. Pasca-Pencucian & Pengantaran (5 status): Siap Diantar (ready_for_delivery), Kurir Pengantaran Ditugaskan (courier_assigned_for_delivery), Sedang Diantar (out_for_delivery), Terkirim (delivered), Selesai (completed).',
            '  d. Kendala & Pembatalan (3 status): Dibatalkan (cancelled), Penjemputan Gagal (pickup_failed), Pengantaran Gagal (delivery_failed).',
        ],
        'note' => 'Status diperbarui secara langsung (real-time) oleh pihak toko dan kurir.',
    ],

    'guide_cancel' => [
        'id' => 'guide_cancel',
        'role' => 'customer',
        'title' => 'Panduan Cara Membatalkan Pesanan (Cancel Order)',
        'keywords' => [
            'batalkan pesanan', 'cara cancel', 'batal order', 'cara membatalkan',
            'batalin order', 'cancel pesanan', 'syarat cancel', 'langkah pembatalan',
            'tolong batalkan', 'batal pesanan',
        ],
        'steps' => [
            'Buka menu "Pesanan Saya" (/orders) dan klik pesanan yang ingin dibatalkan.',
            'Periksa status pesanan Anda. Pembatalan HANYA dapat dilakukan jika pesanan masih berstatus "Pesanan Dibuat" (created) atau "Menunggu Penjemputan" (waiting_pickup).',
            'Klik tombol "Batalkan Pesanan" di bagian bawah detail pesanan, lalu pilih alasan pembatalan.',
            'Konfirmasi pembatalan. Status pesanan akan otomatis berubah menjadi "Dibatalkan".',
        ],
        'note' => 'Jika pakaian sudah dijemput kurir atau sedang dalam proses cuci di outlet toko, pesanan tidak dapat dibatalkan melalui aplikasi. Hubungi Customer Service jika ada keadaan darurat.',
    ],

    'guide_complaint' => [
        'id' => 'guide_complaint',
        'role' => 'customer',
        'title' => 'Panduan Cara Mengajukan Komplain & Garansi Layanan',
        'keywords' => [
            'cara komplain', 'ajukan komplain', 'garansi rusak', 'pakaian luntur',
            'baju hilang', 'komplain cucian', 'klaim garansi', 'cara dispute',
            'baju rusak', 'luntur', 'mw komplin',
        ],
        'steps' => [
            'Komplain wajib diajukan maksimal 1x24 jam sejak pesanan diterima/selesai (status delivered/completed).',
            'Buka menu "Pesanan Saya" (/orders), lalu pilih pesanan terkait yang baru saja selesai.',
            'Klik tombol "Ajukan Komplain" pada halaman Detail Pesanan.',
            'Pilih kategori keluhan (pakaian rusak, luntur, tertukar, atau hilang), tulis deskripsi kendala secara jelas, dan lampirkan foto bukti yang jelas.',
            'Kirim formulir komplain. Tim toko dan penengah LaundryKu akan meninjau keluhan Anda maksimal 1x24 jam kerja.',
        ],
        'note' => 'Pastikan menyimpan nota/bukti fisik dan pakaian dalam kondisi belum digunakan kembali saat mengajukan komplain.',
    ],

    'guide_review' => [
        'id' => 'guide_review',
        'role' => 'customer',
        'title' => 'Panduan Cara Memberi Rating & Ulasan Toko',
        'keywords' => [
            'cara ulas', 'beri ulasan', 'rating toko', 'tulis review', 'cara review',
            'kasih bintang', 'penilaian toko', 'ulasan pelanggan', 'menulis review',
            'menulis ulasan', 'tulis ulasan', 'review setelah cucian', 'memberi review',
            'ulasan', 'review',
        ],
        'steps' => [
            'Buka menu "Pesanan Saya" (/orders) setelah pesanan Anda berstatus "Selesai" (completed).',
            'Klik pesanan yang ingin diulas, lalu klik tombol "Beri Ulasan / Rating".',
            'Berikan nilai bintang 1 sampai 5 untuk kepuasan layanan toko laundry.',
            'Tulis ulasan pengalaman Anda (kebersihan, kerapian, aroma, ketepatan waktu).',
            'Klik "Kirim Ulasan". Ulasan Anda akan tampil di profil publik toko tersebut.',
        ],
        'note' => 'Ulasan yang jujur dan konstruktif sangat membantu mitra toko dan pelanggan lainnya.',
    ],

    'guide_address' => [
        'id' => 'guide_address',
        'role' => 'customer',
        'title' => 'Panduan Cara Menambah atau Mengubah Alamat Penjemputan',
        'keywords' => [
            'tambah alamat', 'cara ubah alamat', 'setting alamat', 'daftar alamat',
            'atur alamat', 'lokasi gps', 'ganti alamat', 'alamat baru', 'tmbah almt',
        ],
        'steps' => [
            'Masuk ke menu "Profil Saya" (/profile) di pojok kanan atas aplikasi.',
            'Pilih tab atau menu "Daftar Alamat".',
            'Klik tombol "Tambah Alamat Baru".',
            'Gunakan tombol "Ambil Lokasi GPS" untuk mendeteksi koordinat lintang/bujur Anda secara akurat (wajib izinkan akses lokasi di browser), atau isi form secara manual (Provinsi, Kota, Kecamatan, Kelurahan, dan alamat detail).',
            'Beri label alamat (contoh: Rumah, Kantor, Kos) dan centang "Jadikan Alamat Utama" jika diinginkan, lalu klik "Simpan".',
        ],
        'note' => 'Pastikan patokan atau nomor rumah tertulis jelas agar kurir mudah menemukan alamat Anda.',
    ],

    'guide_upgrade' => [
        'id' => 'guide_upgrade',
        'role' => 'customer',
        'title' => 'Panduan Cara Bergabung Menjadi Mitra Toko atau Kurir',
        'keywords' => [
            'gabung mitra', 'daftar mitra', 'buka toko laundry', 'daftar jadi kurir',
            'jadi mitra', 'cara jadi kurir', 'cara buka toko', 'upgrade akun',
            'dftr mitra', 'jdi kurir',
        ],
        'steps' => [
            'Pilih jalur pendaftaran kemitraan yang sesuai:',
            '  a. Calon Mitra Toko Baru: Buka portal pendaftaran mitra di /mitra/register, lengkapi identitas pemilik (nama, email, no HP, sandi) serta profil outlet toko (nama toko, alamat outlet, kota, provinsi).',
            '  b. Pengguna yang Sudah Login: Masuk ke akun Anda dan buka menu "Gabung Mitra" (/upgrade), lalu pilih upgrade menjadi Mitra Toko atau Kurir Mitra.',
            'Lengkapi dokumen dan data pendukung:',
            '  - Mitra Toko: Titik peta GPS outlet, radius layanan antar-jemput, dan dokumen legalitas usaha (NIB/SIUP/KTP).',
            '  - Kurir Mitra: Pilih toko mitra naungan, lengkapi data diri, NIK KTP, jenis kendaraan (motor/mobil), nomor pelat polisi, dan SIM.',
            'Kirimkan formulir pendaftaran. Calon mitra toko baru dapat memantau status verifikasi berkas langsung di /mitra/status, atau melalui menu /upgrade bagi akun yang di-upgrade.',
            'Tim Platform Admin LaundryKu akan memverifikasi berkas melalui /admin/approvals. Setelah disetujui (approved), Mitra Toko dapat masuk melalui Portal Mitra (/mitra/login) dan Kurir melalui Portal Kurir (/courier/login).',
        ],
        'note' => 'Pendaftaran mitra tidak dipungut biaya pendaftaran (gratis).',
    ],
];
