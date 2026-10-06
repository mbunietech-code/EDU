# Builds the single-file Windows installer MHub-Setup-<version>.exe with
# IExpress, which ships with Windows (no Inno Setup or other download needed).
#
#   1. flutter build windows --release        (skip with -SkipFlutterBuild)
#   2. adds the Visual C++ runtime DLLs next to mhub.exe, so the app also
#      starts on PCs without the VC++ redistributable
#   3. zips the app, packs install.ps1 + uninstall.ps1 + payload.zip into
#      installer\Output\MHub-Setup-<version>.exe
#
# The .exe opens a setup window: choose the install folder (default
# %LOCALAPPDATA%\Programs\MHub, no admin needed), desktop shortcut, Start
# Menu entry, uninstaller in Apps & features. It replaces the 1.1.0 install.
#
# Run from the mhub/ folder:  powershell -ExecutionPolicy Bypass -File installer\build-setup.ps1

param([switch]$SkipFlutterBuild)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot   # mhub/
Set-Location $root

$version = (Select-String -Path 'pubspec.yaml' -Pattern '^version:\s*([0-9.]+)').Matches[0].Groups[1].Value

if (-not $SkipFlutterBuild) {
    Write-Host '==> flutter build windows --release' -ForegroundColor Cyan
    flutter build windows --release
    if ($LASTEXITCODE -ne 0) { throw 'flutter build failed' }
}

$release = Join-Path $root 'build\windows\x64\runner\Release'
if (-not (Test-Path (Join-Path $release 'mhub.exe'))) { throw "No release build in $release" }

$work  = Join-Path $root 'installer\setup\work'
$stage = Join-Path $work 'app'
$out   = Join-Path $root 'installer\Output'
if (Test-Path $work) { Remove-Item $work -Recurse -Force }
New-Item -ItemType Directory -Force -Path $stage, $out | Out-Null

Write-Host '==> staging the app' -ForegroundColor Cyan
Copy-Item (Join-Path $release '*') $stage -Recurse -Force
foreach ($dll in 'msvcp140.dll', 'vcruntime140.dll', 'vcruntime140_1.dll') {
    $src = Join-Path $env:WINDIR "System32\$dll"
    if (Test-Path $src) { Copy-Item $src $stage -Force } else { Write-Warning "$dll not found; target PCs will need the VC++ runtime" }
}

Write-Host '==> payload.zip' -ForegroundColor Cyan
Compress-Archive -Path (Join-Path $stage '*') -DestinationPath (Join-Path $work 'payload.zip') -CompressionLevel Optimal
Remove-Item $stage -Recurse -Force

(Get-Content (Join-Path $PSScriptRoot 'setup\install.ps1') -Raw).Replace('__VERSION__', $version) |
    Set-Content (Join-Path $work 'install.ps1') -Encoding UTF8
Copy-Item (Join-Path $PSScriptRoot 'setup\uninstall.ps1') $work
Copy-Item (Join-Path $root 'windows\runner\resources\app_icon.ico') $work

$target = Join-Path $out "MHub-Setup-$version.exe"
if (Test-Path $target) { Remove-Item $target -Force }

$sed = @"
[Version]
Class=IEXPRESS
SEDVersion=3
[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=0
HideExtractAnimation=1
UseLongFileName=1
InsideCompressed=0
CAB_FixedSize=0
CAB_ResvCodeSigning=0
RebootMode=N
InstallPrompt=%InstallPrompt%
DisplayLicense=%DisplayLicense%
FinishMessage=%FinishMessage%
TargetName=%TargetName%
FriendlyName=%FriendlyName%
AppLaunched=%AppLaunched%
PostInstallCmd=%PostInstallCmd%
AdminQuietInstCmd=%AdminQuietInstCmd%
UserQuietInstCmd=%UserQuietInstCmd%
SourceFiles=SourceFiles
[Strings]
InstallPrompt=
DisplayLicense=
FinishMessage=
TargetName=$target
FriendlyName=MHub $version Setup
AppLaunched=powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File install.ps1
PostInstallCmd=<None>
AdminQuietInstCmd=
UserQuietInstCmd=
FILE0="install.ps1"
FILE1="uninstall.ps1"
FILE2="payload.zip"
FILE3="app_icon.ico"
[SourceFiles]
SourceFiles0=$work\
[SourceFiles0]
%FILE0%=
%FILE1%=
%FILE2%=
%FILE3%=
"@
$sedFile = Join-Path $work 'mhub.sed'
Set-Content -Path $sedFile -Value $sed -Encoding ASCII

Write-Host '==> iexpress' -ForegroundColor Cyan
$p = Start-Process -FilePath "$env:WINDIR\System32\iexpress.exe" -ArgumentList '/N', '/Q', $sedFile -Wait -PassThru
if (-not (Test-Path $target)) { throw "IExpress did not create $target (exit $($p.ExitCode))" }

$size = [math]::Round((Get-Item $target).Length / 1MB, 1)
Write-Host "`n[OK] Installer ready: $target  ($size MB)" -ForegroundColor Green
