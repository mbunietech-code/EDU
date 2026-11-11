# MHub on iPhone and Mac: handoff

Written 2026-11-11 on the Windows PC, for the Claude Code session that continues on a Mac.
It describes the state of the `main` branch of `https://github.com/mbunietech-code/EDU` on that date.
Read this file first, then do the work in `docs/MHUB-APPLE-1.2.3-BRIEF.md`.

## What exists today

| Part | Where | State |
|---|---|---|
| Website + API (Laravel 11) | repo root (`app/`, `routes/`, `resources/`) | Live at https://mbuniehub.com. **Auto-deploys from `main`.** |
| MHub app (Flutter) | `mhub/` | **1.2.3 (build 6)** released for Android and Windows on 2026-11-11 |
| macOS project | `mhub/macos/` | Exists (bundle id `com.mbunie.mhub`, sandboxed, camera/mic entitlements). Not released as 1.2.3 |
| iOS project | `mhub/ios/` | **Does not exist yet.** Create it with `flutter create --platforms=ios .` inside `mhub/` |

The app is one Flutter code base for everyone (customers, instructors, admins). The account type
decides the menu. It talks to `https://mbuniehub.com/api` with a Sanctum bearer token
(`mhub/lib/core/api_client.dart`, token stored by `mhub/lib/core/storage.dart` with
`flutter_secure_storage`).

### Layout (same as Windows/Android, nothing to redesign)

- Window **900 px or wider** (Mac, iPad landscape): the website layout. A dark sidebar with the MHub logo
  wraps the whole navigator (`DesktopFrame` in `mhub/lib/features/shell/home_shell.dart`, used by
  `MaterialApp.builder` in `mhub/lib/app.dart`). Pages, detail pages and dialogs open to the right of it.
- **Narrower** (iPhone): bottom navigation bar plus a "More" sheet, the same as Android.
- Lists can be dragged with mouse/trackpad (`_AppScrollBehavior` in `app.dart`).

### Version (all must be equal: 1.2.3)

| File | Value |
|---|---|
| `mhub/pubspec.yaml` | `version: 1.2.3+6` (iOS/macOS take `CFBundleShortVersionString` 1.2.3 and build 6 from here) |
| `mhub/lib/core/config.dart` | `AppConfig.version = '1.2.3'` (the update check compares it with the site) |
| `mhub/installer/mhub.iss` | Windows only, already 1.2.3 |

### In-app update check (`mhub/lib/core/app_update.dart`)

Four seconds after start the app calls `GET https://mbuniehub.com/api/app/latest`, which returns, per
platform (`android`, `windows`, `macos`, `ios`), the version and download link set in
**Admin → Settings → App downloads** (setting keys `app_<platform>_path|url|version`, group
`app_downloads`, files in `storage/app/public/app-downloads/`). If the site's version is newer it asks
"MHub X is available". Windows downloads and runs the setup itself. Every other platform opens the link:
on macOS that is the DMG, on iPhone it must be a TestFlight or App Store link.
**If the version label for a platform is empty, users of that platform are never told about updates.**

### Plugins that need Apple-side setup

| Plugin | iOS | macOS |
|---|---|---|
| `flutter_secure_storage` (sign-in token) | works | Sandboxed apps need the Keychain entitlement. Without a Team ID it can fail with `-34018`; the app then just asks to sign in on every start (it never hangs since 1.2.2). Fix with Keychain Sharing, or `MacOsOptions(useDataProtectionKeyChain: false)` in `storage.dart` |
| `livekit_client` / `flutter_webrtc` (live classes) | `NSCameraUsageDescription`, `NSMicrophoneUsageDescription`, `UIBackgroundModes: audio`, iOS 13+ | camera + audio-input entitlements already in both `.entitlements` files |
| `image_picker`, `file_picker` | `NSPhotoLibraryUsageDescription` (+ camera text above) | add `com.apple.security.files.user-selected.read-write` |
| `firebase_messaging` (push) | needs `GoogleService-Info.plist` (git-ignored), the Push Notifications capability, Background Modes → Remote notifications, and an APNs key uploaded to the Firebase project | not used (push is Android/iOS only, see `_fcmSupported` in `lib/core/notifications/notification_center.dart`) |
| `flutter_local_notifications` | AppDelegate needs `UNUserNotificationCenter.current().delegate = self` | works |
| `video_player`, `url_launcher`, `path_provider`, `shared_preferences`, `connectivity_plus`, `device_info_plus` | no extra setup | `network.client` entitlement already there |

The server already accepts `platform: ios|macos` for push device tokens.

## Rules that must not be broken

- **Pushing to `main` deploys the live website.** On the Mac, only change files under `mhub/` and `docs/`.
  Do not touch Laravel code, migrations, `.env` or anything outside those two folders.
- Do not change the version: Apple builds are **1.2.3 build 6**, the same as Android and Windows.
- Android and Windows 1.2.3 are already released. Any Dart change must not break them. Keep
  Apple-only fixes behind `Platform.isIOS` / `Platform.isMacOS`, or in the `ios/` and `macos/` folders.
- English only in the app's text.
- Never commit secrets: `GoogleService-Info.plist`, `google-services.json`, signing certificates,
  provisioning profiles, App Store Connect keys, `.env`. Add any new one to `mhub/.gitignore`.
- Commit and push to `origin main` after each finished step (short message, what and why).
- Report to the owner in **short Swahili**. The owner's keyboard has no `z`/`x`, so they type `s` for `z`
  (`sote` = `zote`).

## Server access (only if it works from the Mac)

`ssh -p 65002 u125644160@46.202.182.173`, app at `~/domains/mbuniehub.com/public_html`.
If there is no SSH key on the Mac, do not try to get one. The owner uploads the files in
**Admin → Settings → App downloads** (upload the file, type the version `v1.2.3`, save).

## Mac setup checklist

1. Xcode (App Store) + `xcode-select --install`, CocoaPods (`brew install cocoapods`).
2. Flutter SDK stable (the PC uses Flutter 3.47.x), `flutter doctor` green for iOS and macOS.
3. `gh auth login`, then `git clone https://github.com/mbunietech-code/EDU.git` (private repo).
4. Sign in to Xcode with the Apple ID (Xcode → Settings → Accounts). Plug in the iPhone and turn on
   Developer Mode (Settings → Privacy & Security).
5. Start Claude Code in the cloned folder and give it the one-line brief from the owner.

## State on the Mac (2026-11-12)

- Mac: macOS 27.2, Xcode 27.0, Flutter 3.47.2, CocoaPods 1.17. `flutter doctor` green.
- **No Apple account is signed in to Xcode and there is no signing certificate**, so: macOS is ad-hoc
  signed (not notarized), iOS builds with `--no-codesign` only, no TestFlight, no iOS screen sharing
  (needs App Groups + a Broadcast Upload Extension, paid account only; the button stays hidden on iPhone).
- No SSH access from this Mac: the owner uploads the DMG and sets the versions in Admin → App downloads.
- macOS: window opens at 1280x720 (same as Windows) and does not restore an old size, so the sidebar
  layout always shows. The sign-in token uses the login keychain on macOS (`useDataProtectionKeyChain: false`).
- iOS: `mhub/ios/` exists (bundle id `com.mbunie.mhub`, iOS 15+). Tested in the iPhone 17 Pro simulator
  (starts on the sign-in page with the logo). Not tested on a real iPhone (none was plugged in).

## Releasing the next version on Apple

1. Bump `version:` in `mhub/pubspec.yaml` (e.g. `1.2.4+7`) and `AppConfig.version` in
   `mhub/lib/core/config.dart` (`1.2.4`). They must match (the DMG script checks this).
2. macOS: `cd mhub && ./installer/macos/make-dmg.sh` → `mhub/installer/Output/MHub-<version>-macos.dmg`.
   With a paid account: `SIGN_ID="Developer ID Application: … (TEAMID)" NOTARY_PROFILE=mhub ./installer/macos/make-dmg.sh`
   (create the profile once with `xcrun notarytool store-credentials mhub`). It then signs with hardened
   runtime, notarizes and staples.
   Without a paid account users must allow it once: System Settings → Privacy & Security → Open Anyway.
3. iPhone (paid account only): open `mhub/ios/Runner.xcworkspace`, pick the team in Signing & Capabilities,
   then `flutter build ipa --release` and upload `build/ios/ipa/*.ipa` with Transporter. Add the build to
   the TestFlight public group.
4. Website: Admin → Settings → App downloads. macOS: upload the DMG, version `v<version>`.
   iPhone: TestFlight public link (or App Store link), version `v<version>`. Then check
   `curl -s https://mbuniehub.com/api/app/latest`.
