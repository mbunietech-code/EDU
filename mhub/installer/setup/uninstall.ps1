# MHub uninstaller (copied into the install folder; run from Apps & features).
Add-Type -AssemblyName System.Windows.Forms

$AppName   = 'MHub'
$UninstKey = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\MHub'
$dir       = Split-Path -Parent $MyInvocation.MyCommand.Path

$answer = [System.Windows.Forms.MessageBox]::Show("Remove $AppName from this computer?", "Uninstall $AppName", 'YesNo', 'Question')
if ($answer -ne 'Yes') { exit }

Get-Process -Name 'mhub' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Milliseconds 500

foreach ($lnk in (Join-Path ([Environment]::GetFolderPath('Programs')) "$AppName.lnk"),
                 (Join-Path ([Environment]::GetFolderPath('Desktop')) "$AppName.lnk")) {
    if (Test-Path $lnk) { Remove-Item $lnk -Force -ErrorAction SilentlyContinue }
}
if (Test-Path $UninstKey) { Remove-Item $UninstKey -Recurse -Force }

# Only delete the folder when it really is an MHub install.
if (Test-Path (Join-Path $dir 'mhub.exe')) {
    # This script lives inside the folder, so let a detached shell remove it after we exit.
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', "timeout /t 2 /nobreak >nul & rmdir /s /q `"$dir`"" -WindowStyle Hidden
}

[System.Windows.Forms.MessageBox]::Show("$AppName was removed.", "Uninstall $AppName", 'OK', 'Information') | Out-Null
