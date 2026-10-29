<?php

namespace App\Services\Ai;

class AiTextNormalizer
{
    /**
     * Common Indonesian slang, colloquialisms, and abbreviations mapping.
     */
    protected const SLANG_MAP = [
        // Pronouns (Saya / Anda)
        'gw' => 'saya',
        'gue' => 'saya',
        'gua' => 'saya',
        'ane' => 'saya',
        'aku' => 'saya',
        'ak' => 'saya',
        'sy' => 'saya',
        'saia' => 'saya',
        'kulo' => 'saya',
        'aing' => 'saya',
        'ogut' => 'saya',
        'lu' => 'kamu',
        'elu' => 'kamu',
        'lo' => 'kamu',
        'loe' => 'kamu',
        'km' => 'kamu',
        'kmu' => 'kamu',
        'ente' => 'kamu',
        'sampeyan' => 'kamu',
        'situ' => 'kamu',
        'bot' => 'bot',
        'landy' => 'landy',

        // Time / Confirmation (Sudah / Belum)
        'udh' => 'sudah',
        'udah' => 'sudah',
        'sdh' => 'sudah',
        'dah' => 'sudah',
        'wis' => 'sudah',
        'telah' => 'sudah',
        'blm' => 'belum',
        'belom' => 'belum',
        'blom' => 'belum',
        'lom' => 'belum',
        'durung' => 'belum',

        // Quantifier / Questions (Berapa / Bagaimana / Apa / Dimana)
        'brp' => 'berapa',
        'brapa' => 'berapa',
        'piro' => 'berapa',
        'seberapa' => 'berapa',
        'gmn' => 'bagaimana',
        'gmana' => 'bagaimana',
        'gimana' => 'bagaimana',
        'bgmn' => 'bagaimana',
        'kpn' => 'kapan',
        'kpan' => 'kapan',
        'dmn' => 'dimana',
        'mn' => 'mana',
        'knp' => 'kenapa',
        'knapa' => 'kenapa',
        'ap' => 'apa',
        'apkh' => 'apakah',
        'sy' => 'saya',
        'syp' => 'siapa',
        'km' => 'kamu',
        'kmu' => 'kamu',
        'sampe' => 'sampai',
        'smpe' => 'sampai',
        'ampe' => 'sampai',
        'abis' => 'pengeluaran',
        'kalo' => 'kalau',
        'min' => 'admin',
        'kbr' => 'kabar',
        'cra' => 'cara',
        'jdi' => 'jadi',
        'hr' => 'hari',
        'kmrn' => 'kemarin',
        'kmarin' => 'kemarin',
        'pdptn' => 'pendapatan',
        'pndptn' => 'pendapatan',
        'pemasukan' => 'pemasukan',
        'nunggu' => 'menunggu',
        'msh' => 'masih',
        'bestseller' => 'terlaris',
        'bestsellers' => 'terlaris',
        'laku' => 'laris',
        'diapprove' => 'disetujui',
        'approval' => 'persetujuan',
        'approve' => 'setujui',
        'pending' => 'menunggu',
        'kelar' => 'selesai',
        'tko' => 'toko',
        'msk' => 'masuk',
        'bj' => 'pakaian',
        'bju' => 'pakaian',

        // Negations (Tidak / Bukan)
        'gak' => 'tidak',
        'ga' => 'tidak',
        'nggak' => 'tidak',
        'ngak' => 'tidak',
        'ndak' => 'tidak',
        'ora' => 'tidak',
        'g' => 'tidak',
        'tdk' => 'tidak',
        'tak' => 'tidak',
        'bkn' => 'bukan',

        // Connectors & Prepositions
        'yg' => 'yang',
        'ingkang' => 'yang',
        'dr' => 'dari',
        'dri' => 'dari',
        'ke' => 'ke',
        'sm' => 'sama',
        'dgn' => 'dengan',
        'dg' => 'dengan',
        'utk' => 'untuk',
        'buat' => 'untuk',
        'bt' => 'untuk',
        'kpd' => 'kepada',
        'pd' => 'pada',
        'org' => 'orang',
        'aja' => 'saja',
        'aj' => 'saja',
        'doang' => 'saja',
        'cuma' => 'hanya',
        'cm' => 'hanya',
        'trus' => 'terus',
        'trs' => 'terus',
        'lg' => 'lagi',
        'tadi' => 'tadi',
        'td' => 'tadi',
        'skrg' => 'sekarang',
        'skrang' => 'sekarang',
        'bs' => 'bisa',
        'bsa' => 'bisa',
        'kl' => 'kalau',
        'klo' => 'kalau',
        'klu' => 'kalau',
        'jika' => 'kalau',

        // Core Domain: Order / Pesanan
        'pesenan' => 'pesanan',
        'orderan' => 'pesanan',
        'order' => 'pesanan',
        'orders' => 'pesanan',
        'mesen' => 'pesan',
        'pesen' => 'pesan',
        'ngorder' => 'pesan',
        'booking' => 'pesanan',
        'bookingan' => 'pesanan',
        'belanjaan' => 'pengeluaran',
        'cucian' => 'pesanan',
        'cucianku' => 'pesanan',
        'laundryan' => 'pesanan',
        'laundryku' => 'laundryku',
        'psnan' => 'pesanan',
        'psn' => 'pesan',
        'ordran' => 'pesanan',
        'loundry' => 'laundry',
        'landry' => 'laundry',
        'paketanku' => 'pesanan',
        'transaksi' => 'pesanan',
        'transaksiku' => 'pesanan',
        'baju' => 'pakaian',
        'pakaianku' => 'pakaian',
        'sttus' => 'status',
        'trakhr' => 'terakhir',
        'trkhir' => 'terakhir',
        'trakhir' => 'terakhir',
        'trbaru' => 'terbaru',
        'ttal' => 'total',
        'jmlh' => 'jumlah',
        'blanja' => 'pengeluaran',
        'almt' => 'alamat',
        'trsimpan' => 'tersimpan',
        'dftar' => 'daftar',
        'lynan' => 'layanan',
        'hrg' => 'harga',
        'trf' => 'harga',
        'bts' => 'batas',
        'wktu' => 'waktu',
        'max' => 'maksimal',
        'jm' => 'jam',
        'bka' => 'buka',
        'trdkt' => 'terdekat',
        'brt' => 'timbang',
        'slsai' => 'selesai',
        'dcucy' => 'cuci',
        'dcuci' => 'cuci',
        'tgas' => 'tugas',
        'tgs' => 'tugas',
        'possi' => 'posisi',
        'sya' => 'saya',
        'dtmbaang' => 'timbang',
        'tmbangan' => 'timbang',
        'kli' => 'kali',
        'stat' => 'statistik',
        'batas' => 'batas',
        'jmputan' => 'jemput',
        'ad' => 'ada',
        'hbs' => 'habis',
        'ongkos' => 'harga',
        'pngantaran' => 'antar',
        'kmplain' => 'komplain',
        'komplin' => 'komplain',
        'komplen' => 'komplain',
        'ulasn' => 'ulasan',
        'rngksan' => 'ringkasan',
        'prforma' => 'performa',
        'omset' => 'omzet',
        'rfund' => 'refund',
        'pembayarn' => 'bayar',
        'pmbayaran' => 'bayar',
        'batalin' => 'batal',
        'cancle' => 'batal',
        'btl' => 'batal',
        'btlin' => 'batal',
        'pke' => 'pakai',
        'dftr' => 'daftar',
        'tko' => 'toko',
        'nimbang' => 'timbang',
        'makasih' => 'terima kasih',
        'makasi' => 'terima kasih',
        'mksih' => 'terima kasih',
        'tengkyu' => 'terima kasih',
        'thx' => 'terima kasih',
        'byebye' => 'selamat tinggal',
        'dadah' => 'selamat tinggal',
        'tmbah' => 'tambah',
        'bru' => 'baru',
        'mo' => 'mau',
        'cra' => 'cara',
        'na' => 'nya',
        'mksimal' => 'maksimal',
        'mksml' => 'maksimal',
        'kuari' => 'kurir',
        'trima' => 'terima',
        'carany' => 'caranya',
        'bwt' => 'buat',
        'hr' => 'hari',
        'ni' => 'ini',
        'dmna' => 'dimana',
        'bnyk' => 'banyak',
        'jmput' => 'jemput',
        'anter' => 'antar',
        'dianter' => 'diantar',
        'diantar' => 'diantar',
        'nganter' => 'antar',
        'dproses' => 'proses',

        // Core Domain: Washing & Stages
        'dicuci' => 'cuci',
        'nyuci' => 'cuci',
        'cuciin' => 'cuci',
        'mencuci' => 'cuci',
        'kelar' => 'selesai',
        'beres' => 'selesai',
        'rampung' => 'selesai',
        'tuntas' => 'selesai',
        'done' => 'selesai',
        'finish' => 'selesai',
        'diproses' => 'proses',
        'prosesan' => 'proses',
        'ngerjain' => 'proses',
        'pengerjaan' => 'proses',
        'digodok' => 'proses',

        // Core Domain: Payment & Finances
        'byr' => 'bayar',
        'mbayar' => 'bayar',
        'cash on delivery' => 'cod',
        'bayaran' => 'bayar',
        'bayarnya' => 'bayar',
        'pembayaran' => 'bayar',
        'invoice' => 'bayar',
        'tagihan' => 'bayar',
        'bill' => 'bayar',
        'tf' => 'transfer',
        'transfr' => 'transfer',
        'lunasin' => 'lunas',
        'pelunasan' => 'lunas',
        'kelunasan' => 'lunas',
        'duit' => 'uang',
        'uang' => 'biaya',
        'dana' => 'biaya',
        'pengeluaran' => 'pengeluaran',
        'belanja' => 'pengeluaran',
        'ngabisin' => 'pengeluaran',
        'keluarin' => 'pengeluaran',
        'habis' => 'pengeluaran',
        'habiskan' => 'pengeluaran',
        'totalan' => 'total',

        // Core Domain: Logistics & Courier
        'ongkir' => 'ongkir',
        'ongkos' => 'ongkir',
        'driver' => 'kurir',
        'ojol' => 'kurir',
        'kang ojol' => 'kurir',
        'mas kurir' => 'kurir',
        'bang kurir' => 'kurir',
        'kurirnya' => 'kurir',
        'pengantar' => 'kurir',
        'jemput' => 'jemput',
        'pickup' => 'jemput',
        'anter' => 'antar',
        'anterin' => 'antar',
        'nganter' => 'antar',
        'delivery' => 'antar',
        'kirim' => 'antar',

        // Core Domain: Store & Service
        'kios' => 'toko',
        'outlet' => 'toko',
        'gerai' => 'toko',
        'cabang' => 'toko',
        'lapak' => 'toko',
        'servis' => 'layanan',
        'jasa' => 'layanan',
        'menu' => 'menu',
        'paket' => 'layanan',
        'list' => 'daftar',
        'daptar' => 'daftar',
        'tarif' => 'harga',
        'tarifnya' => 'harga',
        'harganya' => 'harga',
        'pricelist' => 'harga',
        'price' => 'harga',
        'biaya' => 'harga',
        'biayanya' => 'harga',

        // Core Domain: Weighing
        'timbang' => 'timbang',
        'timbangan' => 'timbang',
        'ditimbang' => 'timbang',
        'nimbang' => 'timbang',
        'berat' => 'timbang',
        'beratnya' => 'timbang',
        'kilo' => 'kg',
        'kiloan' => 'kiloan',

        // Core Domain: Complaint & Review
        'komplin' => 'komplain',
        'komplainan' => 'komplain',
        'keluhan' => 'komplain',
        'complain' => 'komplain',
        'dispute' => 'komplain',
        'protes' => 'komplain',
        'kecewa' => 'komplain',
        'rusak' => 'komplain',
        'luntur' => 'komplain',
        'hilang' => 'komplain',
        'robek' => 'komplain',
        'review' => 'ulasan',
        'ulasan' => 'ulasan',
        'testi' => 'ulasan',
        'testimoni' => 'ulasan',
        'bintang' => 'rating',
        'rate' => 'rating',

        // Core Domain: Account & Cancellation
        'biodata' => 'akun',
        'profile' => 'akun',
        'profilku' => 'akun',
        'profil' => 'akun',
        'alamat' => 'alamat',
        'cancel' => 'batal',
        'cancelan' => 'batal',
        'batalin' => 'batal',
        'batalkeun' => 'batal',
        'gajadi' => 'batal',
    ];

    /**
     * Canonical keywords dictionary for typo tolerance (fuzzy matching via Levenshtein).
     */
    protected const CANONICAL_KEYWORDS = [
        'pesanan', 'cucian', 'laundry', 'cuci', 'selesai', 'proses', 'bayar',
        'lunas', 'pengeluaran', 'harga', 'layanan', 'kurir', 'toko', 'timbang',
        'komplain', 'ulasan', 'rating', 'akun', 'alamat', 'batal', 'status',
        'jemput', 'antar', 'ongkir', 'kapan', 'berapa', 'aturan', 'refund',
        'kemarin', 'terakhir', 'daftar', 'kiloan', 'satuan', 'komplit', 'setrika',
        'batas', 'tenggat', 'statistik',
    ];

    /**
     * Normalize input text by:
     * 1. Preserving order numbers (ORD-...)
     * 2. Lowercasing
     * 3. Removing emojis and excessive punctuation
     * 4. Expanding Indonesian slang & abbreviations
     * 5. Fixing typos with Levenshtein distance on canonical keywords
     */
    public function normalize(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        // 1. Extract and protect Order Numbers (e.g. ORD-20261018-PROC01 or ord-abc-123)
        $orderPlaceholders = [];
        $orderCounter = 0;
        $preservedText = preg_replace_callback(
            '/\b(ORD[-_][A-Za-z0-9\-]+|ORD\d+)\b/i',
            function ($matches) use (&$orderPlaceholders, &$orderCounter) {
                $token = '___ORDERNUM_' . ($orderCounter++) . '___';
                $orderPlaceholders[$token] = strtoupper(str_replace('_', '-', $matches[1]));
                return $token;
            },
            $text
        );

        // 2. Lowercase UTF-8
        $clean = mb_strtolower($preservedText, 'UTF-8');

        // 3. Remove Emojis (standard emoji byte ranges in UTF-8)
        $clean = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F1E0}-\x{1F1FF}]/u', '', $clean);

        // 4. Remove all punctuation and symbols except letters, digits, spaces, and placeholder underscores
        $clean = preg_replace('/[^\p{L}\p{N}\s_]/u', ' ', $clean);

        // Collapse multiple whitespaces
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // 4b. Pre-replace multi-word compound phrases before tokenization
        $clean = str_replace(
            ['cash on delivery', 'customer service', 'terima kasih', 'sama sama', 'se-platform', 'seplatform', 'uang masuk', 'duit masuk', 'top customer'],
            ['cod', 'customer service', 'terima kasih', 'sama sama', 'se platform', 'se platform', 'pemasukan', 'pemasukan', 'top customer'],
            $clean
        );

        // 5. Tokenize and apply Slang/Abbreviation Dictionary + Typo Tolerance
        $words = explode(' ', $clean);
        $normalizedWords = [];

        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') {
                continue;
            }

            // If it is an order placeholder, keep as is
            if (str_starts_with($w, '___ordernum_')) {
                $normalizedWords[] = strtoupper($w);
                continue;
            }

            // Slang dictionary check
            if (isset(self::SLANG_MAP[$w])) {
                $normalizedWords[] = self::SLANG_MAP[$w];
                continue;
            }

            // Typo / Fuzzy match against canonical keywords
            $fuzzyWord = $this->fuzzyMatchKeyword($w);
            if ($fuzzyWord !== null) {
                // Also check if the matched canonical keyword has a slang redirect
                $normalizedWords[] = self::SLANG_MAP[$fuzzyWord] ?? $fuzzyWord;
                continue;
            }

            $normalizedWords[] = $w;
        }

        $result = implode(' ', $normalizedWords);

        // 6. Restore Order Number Placeholders
        foreach ($orderPlaceholders as $token => $realOrderNum) {
            $result = str_replace(strtolower($token), $realOrderNum, $result);
            $result = str_replace($token, $realOrderNum, $result);
        }

        return trim(preg_replace('/\s+/', ' ', $result));
    }

    /**
     * Fuzzy match token against canonical keywords using Levenshtein distance.
     * Threshold:
     * - length 4 to 6: max distance 1
     * - length >= 7: max distance 2
     */
    protected function fuzzyMatchKeyword(string $token): ?string
    {
        $len = strlen($token);
        if ($len < 4) {
            return null;
        }

        $maxDist = $len >= 7 ? 2 : 1;
        $bestMatch = null;
        $lowestDist = $maxDist + 1;

        foreach (self::CANONICAL_KEYWORDS as $canon) {
            $cLen = strlen($canon);
            // Quick length filter to save CPU
            if (abs($len - $cLen) > $maxDist) {
                continue;
            }

            $dist = levenshtein($token, $canon);
            if ($dist <= $maxDist && $dist < $lowestDist) {
                $lowestDist = $dist;
                $bestMatch = $canon;
                if ($dist === 0) {
                    break;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Extract any order numbers mentioned anywhere in the query string.
     * E.g. "ORD-20261018-PROC01", "ord-1234", "ord_5678", "ORD12345"
     */
    public function extractOrderNumber(string $text): ?string
    {
        if (preg_match('/\b(ORD[-_][A-Za-z0-9\-]+|ORD\d+)\b/i', $text, $matches)) {
            $clean = strtoupper(str_replace('_', '-', $matches[1]));
            // Ensure format has a hyphen: ORD-XXX
            if (!str_contains($clean, '-')) {
                $clean = 'ORD-' . substr($clean, 3);
            }
            return $clean;
        }

        return null;
    }

    /**
     * Sanitize PII (emails, phone numbers) from query text for safe audit logging and reviews.
     */
    public function sanitizePii(string $text): string
    {
        // Mask emails
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL_MASKED]', $text);

        // Mask phone numbers (Indonesian style: +628..., 08...)
        $text = preg_replace('/(\+?62|0)8[0-9]{8,12}/', '[PHONE_MASKED]', $text);

        return $text;
    }
}
