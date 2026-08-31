# Builds the MHub Windows installer.
#
#   1. flutter build windows --release
#   2. compiles installer\mhub.iss with Inno Setup
#   3. output: installer\Output\MHub-Setup-<version>.exe
#
# Requires Inno Setup 6:  winget install JRSoftware.InnoSetup
# Run from the mhub/ folder:  powershell -ExecutionPolicy Bypass -File installer\build-installer.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot   # mhub/
Set-Location $root

Write-Host '==> flutter build windows --release' -ForegroundColor Cyan
flutter build windows --release
if ($LASTEXITCODE -ne 0) { throw 'flutter build failed' }

$iscc = @(
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $iscc) { throw 'ISCC.exe not found. Install Inno Setup 6:  winget install JRSoftware.InnoSetup' }

Write-Host "==> $iscc installer\mhub.iss" -ForegroundColor Cyan
& $iscc "installer\mhub.iss"
if ($LASTEXITCODE -ne 0) { throw 'Inno Setup compile failed' }

$out = Get-ChildItem "installer\Output\MHub-Setup-*.exe" | Sort-Object LastWriteTime | Select-Object -Last 1
Write-Host "`n[OK] Installer ready: $($out.FullName)  ($([math]::Round($out.Length/1MB,1)) MB)" -ForegroundColor Green
