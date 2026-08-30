# MHub — wireless debugging helper for an Android phone (e.g. Infinix).
#
# One-time on the phone (Android 11+):
#   Settings > About phone > tap "Build number" 7x
#   Settings > System > Developer options > enable "USB debugging" AND "Wireless debugging"
#   Put the phone on the SAME Wi-Fi as this PC
#
# Then:
#   1) Phone: Wireless debugging > "Pair device with pairing code"
#      -> note the  IP:PORT  and the 6-digit CODE
#   2) Run:   .\tool\wireless.ps1 -Pair 192.168.x.x:PORT -Code 123456
#   3) Phone: back on the Wireless debugging screen, note "IP address & Port"
#      (a DIFFERENT port) and run:
#             .\tool\wireless.ps1 -Connect 192.168.x.x:PORT
#   4) Run the app:   .\tool\wireless.ps1 -Run
#
param(
    [string]$Pair,
    [string]$Code,
    [string]$Connect,
    [switch]$Run,
    [string]$ApiBase = "http://192.168.118.5:8123"
)

$adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
if (-not (Test-Path $adb)) { $adb = "adb" }

if ($Pair) {
    if (-not $Code) { Write-Error "Pass -Code <6 digits>"; exit 1 }
    & $adb pair $Pair $Code
}
if ($Connect) {
    & $adb connect $Connect
}
& $adb devices -l

if ($Run) {
    $dev = (& $adb devices | Select-String ':\d+\s+device').ToString().Split()[0]
    if (-not $dev) { $dev = (& $adb devices | Select-String '\sdevice$').ToString().Split()[0] }
    if (-not $dev) { Write-Error "No device connected"; exit 1 }
    Write-Host "Running on $dev against API $ApiBase"
    flutter run -d $dev --dart-define=MHUB_API_BASE=$ApiBase
}
