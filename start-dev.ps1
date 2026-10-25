# start-dev.ps1 — Menjalankan Laravel Dev Server dari Root Laundryku (PHP Laragon 8.3)
$ErrorActionPreference = "Stop"

$phpLaragon = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
$rootPath = "C:\laragon\www\laundryku"
$port = 8000

Write-Host "==> Memeriksa proses PHP di sistem..." -ForegroundColor Cyan

# 1. Cari dan hentikan semua proses php.exe yang berjalan dari folder backend/
$backendPhpProcesses = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
    $_.CommandLine -match "\\laundryku\\backend" -or $_.ExecutablePath -match "\\herd\\bin\\php"
}

if ($backendPhpProcesses) {
    foreach ($proc in $backendPhpProcesses) {
        Write-Host "--> Menghentikan proses php backend lama (PID: $($proc.ProcessId)): $($proc.CommandLine)" -ForegroundColor Yellow
        Stop-Process -Id $proc.ProcessId -Force -ErrorAction SilentlyContinue
    }
}

# 2. Periksa apakah port 8000 sedang digunakan
$conn = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
if ($conn) {
    $ownerProc = Get-CimInstance Win32_Process -Filter "ProcessId = $($conn.OwningProcess)" -ErrorAction SilentlyContinue
    if ($ownerProc) {
        if ($ownerProc.CommandLine -match "\\laundryku\\backend" -or $ownerProc.ExecutablePath -match "\\herd\\bin\\php") {
            Write-Host "--> Port $port digunakan oleh backend lama (PID: $($ownerProc.ProcessId)). Menghentikan..." -ForegroundColor Yellow
            Stop-Process -Id $ownerProc.ProcessId -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 1
        } elseif ($ownerProc.CommandLine -match "\\laundryku\\vendor" -and $ownerProc.ExecutablePath -match "php-8.3.30") {
            Write-Host "--> Server root Laundryku (PHP 8.3) sudah aktif di port $port (PID: $($ownerProc.ProcessId))." -ForegroundColor Green
            exit 0
        } else {
            Write-Host "--> Port $port digunakan oleh proses lain (PID: $($ownerProc.ProcessId)): $($ownerProc.CommandLine)" -ForegroundColor Red
            Write-Host "    Silakan tutup proses tersebut sebelum menjalankan server." -ForegroundColor Red
            exit 1
        }
    }
}

# 3. Jalankan server Laravel dari root dengan PHP Laragon 8.3
Write-Host "==> Menjalankan server Laravel dari root ($rootPath) dengan PHP Laragon 8.3 di port $port..." -ForegroundColor Green
Set-Location -Path $rootPath
& $phpLaragon artisan serve --port=$port
