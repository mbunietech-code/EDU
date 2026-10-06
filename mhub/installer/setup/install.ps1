# MHub setup wizard (packed into one MHub-Setup-<version>.exe by build-setup.ps1).
# Runs from the folder IExpress extracts to, next to payload.zip and app_icon.ico.
# Per-user install (no admin needed) into a folder the user can choose.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$AppName    = 'MHub'
$Publisher  = 'MbunieEduHub'
$Version    = '__VERSION__'
$ExeName    = 'mhub.exe'
$UninstKey  = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\MHub'
# The 1.1.0 installer (Inno Setup) used this id; remove that copy on upgrade.
$OldInnoIds = @('{27A97713-D53A-4E61-962D-02BCDB5F4AD1}_is1')
$Here       = Split-Path -Parent $MyInvocation.MyCommand.Path
$Payload    = Join-Path $Here 'payload.zip'
$IconFile   = Join-Path $Here 'app_icon.ico'

function Get-OldInnoKeys {
    foreach ($id in $OldInnoIds) {
        "HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\$id"
        "HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\$id"
        "HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\$id"
    }
}

# Reinstalls and upgrades default to the folder already in use.
function Get-Previous {
    if (Test-Path $UninstKey) { return (Get-ItemProperty $UninstKey).InstallLocation }
    foreach ($k in Get-OldInnoKeys) {
        if (Test-Path $k) { return (Get-ItemProperty $k).InstallLocation }
    }
    return $null
}

$default = Get-Previous
if (-not $default) { $default = Join-Path $env:LOCALAPPDATA "Programs\$AppName" }
$default = $default.TrimEnd('\')

# --- Window -------------------------------------------------------------------
$form = New-Object System.Windows.Forms.Form
$form.Text = "$AppName $Version Setup"
$form.Size = New-Object System.Drawing.Size(560, 380)
$form.StartPosition = 'CenterScreen'
$form.FormBorderStyle = 'FixedDialog'
$form.MaximizeBox = $false
$form.Font = New-Object System.Drawing.Font('Segoe UI', 9.5)
if (Test-Path $IconFile) { $form.Icon = New-Object System.Drawing.Icon($IconFile) }

$banner = New-Object System.Windows.Forms.Panel
$banner.BackColor = [System.Drawing.Color]::FromArgb(33, 50, 127)
$banner.Dock = 'Top'; $banner.Height = 70
$form.Controls.Add($banner)
$title = New-Object System.Windows.Forms.Label
$title.Text = "Install $AppName $Version"
$title.ForeColor = 'White'
$title.Font = New-Object System.Drawing.Font('Segoe UI Semibold', 15)
$title.Location = '20,10'; $title.AutoSize = $true
$banner.Controls.Add($title)
$sub = New-Object System.Windows.Forms.Label
$sub.Text = 'MbunieEduHub - Learn. Grow. Succeed.'
$sub.ForeColor = [System.Drawing.Color]::FromArgb(200, 215, 255)
$sub.Location = '22,42'; $sub.AutoSize = $true
$banner.Controls.Add($sub)

$lbl = New-Object System.Windows.Forms.Label
$lbl.Text = 'Install to this folder:'
$lbl.Location = '20,90'; $lbl.AutoSize = $true
$form.Controls.Add($lbl)

$path = New-Object System.Windows.Forms.TextBox
$path.Location = '20,114'; $path.Size = '400,26'; $path.Text = $default
$form.Controls.Add($path)

$browse = New-Object System.Windows.Forms.Button
$browse.Text = 'Browse...'; $browse.Location = '430,112'; $browse.Size = '95,28'
$browse.Add_Click({
    $dlg = New-Object System.Windows.Forms.FolderBrowserDialog
    $dlg.Description = "Choose where to install $AppName"
    $dlg.SelectedPath = Split-Path -Parent $path.Text
    if ($dlg.ShowDialog() -eq 'OK') { $path.Text = Join-Path $dlg.SelectedPath $AppName }
})
$form.Controls.Add($browse)

$hint = New-Object System.Windows.Forms.Label
$hint.Text = 'No administrator rights are needed for a folder in your user profile.'
$hint.ForeColor = 'Gray'; $hint.Location = '20,144'; $hint.AutoSize = $true
$form.Controls.Add($hint)

$desktop = New-Object System.Windows.Forms.CheckBox
$desktop.Text = 'Create a desktop shortcut'; $desktop.Checked = $true
$desktop.Location = '20,176'; $desktop.AutoSize = $true
$form.Controls.Add($desktop)

$launch = New-Object System.Windows.Forms.CheckBox
$launch.Text = "Open $AppName when setup finishes"; $launch.Checked = $true
$launch.Location = '20,202'; $launch.AutoSize = $true
$form.Controls.Add($launch)

$bar = New-Object System.Windows.Forms.ProgressBar
$bar.Location = '20,240'; $bar.Size = '505,18'; $bar.Visible = $false
$form.Controls.Add($bar)

$status = New-Object System.Windows.Forms.Label
$status.Location = '20,262'; $status.Size = '505,20'
$form.Controls.Add($status)

$install = New-Object System.Windows.Forms.Button
$install.Text = 'Install'; $install.Location = '330,292'; $install.Size = '95,32'
$form.Controls.Add($install)
$form.AcceptButton = $install

$cancel = New-Object System.Windows.Forms.Button
$cancel.Text = 'Cancel'; $cancel.Location = '430,292'; $cancel.Size = '95,32'
$cancel.Add_Click({ $form.Close() })
$form.Controls.Add($cancel)

function Step($text, $value) {
    $status.Text = $text; $bar.Value = $value
    [System.Windows.Forms.Application]::DoEvents()
}

function New-Shortcut($file, $target, $workDir) {
    $sh = New-Object -ComObject WScript.Shell
    $lnk = $sh.CreateShortcut($file)
    $lnk.TargetPath = $target
    $lnk.WorkingDirectory = $workDir
    $lnk.IconLocation = "$target,0"
    $lnk.Save()
}

$install.Add_Click({
    $dir = $path.Text.Trim().TrimEnd('\')
    if (-not $dir) { return }
    $install.Enabled = $false; $browse.Enabled = $false; $path.Enabled = $false
    $bar.Visible = $true
    try {
        Step 'Closing MHub if it is running...' 5
        Get-Process -Name 'mhub' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Milliseconds 500

        # Upgrade from the 1.1.0 (Inno Setup) install: remove it quietly first.
        foreach ($k in Get-OldInnoKeys) {
            if (Test-Path $k) {
                $u = (Get-ItemProperty $k).UninstallString
                if ($u) {
                    Step 'Removing the previous version...' 15
                    $exe = $u.Trim('"')
                    if (Test-Path $exe) { Start-Process -FilePath $exe -ArgumentList '/VERYSILENT', '/SUPPRESSMSGBOXES', '/NORESTART' -Wait }
                }
            }
        }

        Step 'Copying files...' 30
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        if (Test-Path (Join-Path $dir 'data')) { Remove-Item (Join-Path $dir 'data') -Recurse -Force }
        Expand-Archive -Path $Payload -DestinationPath $dir -Force
        Copy-Item $IconFile (Join-Path $dir 'app_icon.ico') -Force

        Step 'Creating shortcuts...' 75
        $exePath = Join-Path $dir $ExeName
        $menu = Join-Path ([Environment]::GetFolderPath('Programs')) "$AppName.lnk"
        New-Shortcut $menu $exePath $dir
        $deskLnk = Join-Path ([Environment]::GetFolderPath('Desktop')) "$AppName.lnk"
        if ($desktop.Checked) { New-Shortcut $deskLnk $exePath $dir } elseif (Test-Path $deskLnk) { Remove-Item $deskLnk -Force }

        Step 'Registering the uninstaller...' 90
        $uninstall = Join-Path $dir 'uninstall.ps1'
        Copy-Item (Join-Path $Here 'uninstall.ps1') $uninstall -Force
        $size = [int]((Get-ChildItem $dir -Recurse -File | Measure-Object Length -Sum).Sum / 1KB)
        New-Item -Path $UninstKey -Force | Out-Null
        $props = @{
            DisplayName     = $AppName
            DisplayVersion  = $Version
            Publisher       = $Publisher
            DisplayIcon     = "$exePath,0"
            InstallLocation = $dir
            URLInfoAbout    = 'https://mbuniehub.com'
            UninstallString = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$uninstall`""
            NoModify        = 1
            NoRepair        = 1
            EstimatedSize   = $size
        }
        foreach ($p in $props.GetEnumerator()) {
            $type = if ($p.Value -is [int]) { 'DWord' } else { 'String' }
            New-ItemProperty -Path $UninstKey -Name $p.Key -Value $p.Value -PropertyType $type -Force | Out-Null
        }

        Step 'Done.' 100
        if ($launch.Checked) { Start-Process -FilePath $exePath -WorkingDirectory $dir }
        [System.Windows.Forms.MessageBox]::Show("$AppName $Version is installed in:`n$dir", "$AppName Setup", 'OK', 'Information') | Out-Null
        $form.Close()
    } catch [System.UnauthorizedAccessException] {
        [System.Windows.Forms.MessageBox]::Show("Windows did not allow writing to:`n$dir`n`nChoose a folder in your user profile, or run the setup as administrator.", "$AppName Setup", 'OK', 'Warning') | Out-Null
        $install.Enabled = $true; $browse.Enabled = $true; $path.Enabled = $true; $bar.Visible = $false; $status.Text = ''
    } catch {
        [System.Windows.Forms.MessageBox]::Show("Setup could not finish:`n$($_.Exception.Message)", "$AppName Setup", 'OK', 'Error') | Out-Null
        $install.Enabled = $true; $browse.Enabled = $true; $path.Enabled = $true; $bar.Visible = $false; $status.Text = ''
    }
})

[void]$form.ShowDialog()
