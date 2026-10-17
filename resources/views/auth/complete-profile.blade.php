<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Profil - LaundryKu</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, sans-serif; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; }
        .card { background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-width: 420px; width: 100%; }
        h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem; color: #1f2937; }
        p { color: #6b7280; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 500; font-size: 0.9rem; color: #374151; margin-bottom: 0.3rem; }
        input { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; transition: border 0.2s; }
        input:focus { outline: none; border-color: #6366f1; ring: 2px solid #6366f1; }
        .btn { width: 100%; padding: 0.7rem; background: #6366f1; color: white; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #4f46e5; }
        .skip { display: block; text-align: center; margin-top: 1rem; color: #6b7280; font-size: 0.9rem; text-decoration: none; }
        .skip:hover { color: #374151; }
        .error { color: #ef4444; font-size: 0.85rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Lengkapi Profil</h1>
        <p>Halo <strong>{{ $user->name ?? '' }}</strong>, isi data tambahan agar pengalamanmu lebih lengkap.</p>
        <form method="POST" action="{{ route('auth.complete-profile.update') }}">
            @csrf
            <div class="form-group">
                <label for="phone">Nomor Telepon</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone ?? '') }}" placeholder="08123456789">
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label for="address">Alamat (opsional)</label>
                <input type="text" id="address" name="address" value="{{ old('address') }}" placeholder="Jl. Contoh No. 123">
            </div>
            <button type="submit" class="btn">Simpan Profil</button>
            <a href="{{ route('home') }}" class="skip">Lewati & selesaikan nanti</a>
        </form>
    </div>
</body>
</html>