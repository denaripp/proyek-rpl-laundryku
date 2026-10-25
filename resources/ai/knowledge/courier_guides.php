<?php

return [
    'guide_courier_tasks' => [
        'id' => 'guide_courier_tasks',
        'role' => 'courier',
        'title' => 'Panduan Menjalankan Tugas Penjemputan & Pengantaran Kurir',
        'keywords' => [
            'tugas kurir', 'ambil tugas', 'terima tugas', 'cara ambil tugas',
            'alur kurir', 'tugas jemput', 'tugas antar', 'menjalankan tugas',
            'trima tgas', 'tgas kuari',
        ],
        'steps' => [
            'Buka "Portal Kurir" (/courier) di aplikasi LaundryKu.',
            'Pastikan status ketersediaan Anda dalam kondisi "Aktif / Siap Tugas".',
            'Saat ada pesanan baru di area Anda, tugas penjemputan/pengantaran akan muncul pada tab "Tugas Tersedia".',
            'Klik "Terima Tugas". Rincian alamat pelanggan dan nomor telepon akan terbuka.',
            'Saat tiba di lokasi pelanggan, klik tombol "Tiba di Lokasi" -> lakukan verifikasi dan serah terima pakaian -> antarkan cucian ke outlet toko laundry terkait.',
            'Untuk tugas pengantaran pakaian bersih: ambil pakaian dari toko -> antar ke alamat pelanggan -> konfirmasi penyelesaian tugas di aplikasi.',
        ],
        'note' => 'Selalu periksa kondisi bungkusan cucian dan foto serah terima sebagai bukti sah pengiriman.',
    ],

    'guide_courier_cod' => [
        'id' => 'guide_courier_cod',
        'role' => 'courier',
        'title' => 'Panduan Pembayaran COD & Penyetoran Tunai Kurir',
        'keywords' => [
            'cod kurir', 'setor cod', 'tagih cod', 'bayar cod tunai',
            'penagihan cod', 'uang tunai cod', 'aturan cod kurir',
            'setoran cod', 'cara tagih cod',
        ],
        'steps' => [
            'Periksa keterangan pesanan: jika berlabel "COD (Cash on Delivery)", kurir wajib menagih uang tunai langsung dari pelanggan saat serah terima pakaian.',
            'Pastikan jumlah uang tunai yang diterima pelanggan tepat sesuai total tagihan di aplikasi (maksimal nilai order COD adalah Rp500.000).',
            'Setelah menerima uang tunai, klik tombol "Konfirmasi Pembayaran COD Diterima" pada detail tugas di aplikasi.',
            'Setorkan uang tunai yang terkumpul ke kasir toko mitra atau transfer sesuai jadwal rekonsiliasi harian toko/platform.',
        ],
        'note' => 'Jangan pernah menyerahkan pakaian bersih jika pelanggan menolak membayar tagihan COD.',
    ],

    'guide_courier_availability' => [
        'id' => 'guide_courier_availability',
        'role' => 'courier',
        'title' => 'Panduan Mengatur Status Ketersediaan Kurir (Online / Offline)',
        'keywords' => [
            'status kurir', 'ketersediaan kurir', 'online kurir', 'offline kurir',
            'siap tugas', 'istirahat kurir', 'toggle ketersediaan',
        ],
        'steps' => [
            'Masuk ke halaman utama "Portal Kurir" (/courier).',
            'Di bagian atas layar terdapat tombol/sakelar (toggle) "Status Ketersediaan".',
            'Geser ke posisi "Aktif / Online" saat Anda siap menerima tugas penjemputan dan pengantaran di lapangan.',
            'Jika sedang istirahat atau selesai jam kerja, geser ke posisi "Nonaktif / Offline" agar sistem tidak menugaskan pesanan baru kepada Anda.',
        ],
        'note' => 'Pastikan GPS dan koneksi internet perangkat selalu aktif selama bertugas.',
    ],
];
