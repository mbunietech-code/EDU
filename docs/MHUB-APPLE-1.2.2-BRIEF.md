# Brief: MHub 1.2.2 for iPhone and Mac

Read `docs/MHUB-APPLE-HANDOFF.md` first. It has the background, the rules and the plugin table.

**Goal:** the same MHub app as Android and Windows (same features, same layout, **same version 1.2.2 build 5**)
running on iPhone and on Mac. Then install it on this Mac, make it downloadable from
https://mbuniehub.com, and make the in-app update check work for both platforms.

Work through the steps in order. Commit and push to `origin main` after each step (only files under
`mhub/` and `docs/`). If a step needs the owner (an Apple account, a file from Firebase, a click
in a web page), do everything else first, then ask them in short Swahili and list exactly what you
need.

## 0. Check the machine

- `flutter doctor -v`. Xcode, CocoaPods, and the iOS and macOS toolchains must be OK. Fix what you can.
- Check what Apple account is signed in to Xcode and whether it is a **paid Apple Developer Program**
  account (Team ID shown in Xcode → Settings → Accounts). Write the answer down. It decides steps 4 and 5.
- `cd mhub && flutter pub get`.

## 1. macOS app

1. Set the deployment target to at least **macOS 10.15** (already set) in Xcode and in the Podfile if
   one gets generated. Run `flutter build macos --release` and fix every build error.
2. Entitlements (`macos/Runner/DebugProfile.entitlements` and `Release.entitlements`): add
   `com.apple.security.files.user-selected.read-write` (file and image pickers). If sign-in is not
   remembered after quitting and reopening the app, fix the keychain: add Keychain Sharing with the
   team, or set `MacOsOptions(useDataProtectionKeyChain: false)` in `lib/core/storage.dart`.
3. Run the app (`open build/macos/Build/Products/Release/MHub.app`) and check these, with screenshots:
   - It starts and shows the sign-in page with the MHub logo, not an endless spinner.
   - After sign-in, the window shows the dark website-style sidebar. Every sidebar item opens its page,
     the sidebar scrolls, and lists scroll with the trackpad.
   - Sign-in is remembered after quitting and reopening.
   - Opening a detail page (an order) keeps the sidebar visible.
   - Live class: camera and microphone permission prompts appear (no need to join a real class).
4. App icon: the macOS icon must be the MHub logo (`assets/icon/mhub_icon_square.png`,
   `flutter_launcher_icons` in `pubspec.yaml` has `macos: generate: true`).
   Run `dart run flutter_launcher_icons` if it is not.
5. Package a DMG named **`MHub-1.2.2-macos.dmg`** (for example with `hdiutil create -volname MHub
   -srcfolder <folder with MHub.app and an /Applications link> -ov -format UDZO`).
   - Paid account: sign with **Developer ID Application**, turn on hardened runtime, **notarize**
     (`xcrun notarytool submit … --wait`) and **staple** it.
   - No paid account: ad-hoc sign it (`codesign --force --deep -s - MHub.app`). Tell the owner that
     users must allow it once (System Settings → Privacy & Security → Open Anyway).
   - Add a small script `mhub/installer/macos/make-dmg.sh` that builds and packages it, so the next
     release is one command.
6. Install it on this Mac (copy to `/Applications`), open it, and leave it open for the owner.

## 2. iPhone app

1. `cd mhub && flutter create --platforms=ios --org com.mbunie .`. The bundle id must be **`com.mbunie.mhub`**.
   Do not let `flutter create` overwrite anything outside `ios/`; check `git status` afterwards.
2. `ios/Podfile`: `platform :ios, '13.0'` or higher, whatever the plugins need. `pod install` must succeed.
3. `ios/Runner/Info.plist`: display name `MHub`, `NSCameraUsageDescription`,
   `NSMicrophoneUsageDescription` (live classes), `NSPhotoLibraryUsageDescription` (profile photo,
   payment proof), `UIBackgroundModes` = `audio` (live class keeps audio) and `remote-notification`.
4. `ios/Runner/AppDelegate.swift`: set the notification center delegate so local notifications show
   (see the `flutter_local_notifications` iOS setup).
5. Push notifications: the code is ready. It needs `ios/Runner/GoogleService-Info.plist` from the Firebase
   project (app `com.mbunie.mhub`, iOS), which is **git-ignored**. You also need the Push Notifications
   capability and an APNs key uploaded to Firebase (paid account only). If any of this is missing, the
   app must still run (it only logs "FCM init failed"). Ask the owner for the plist and continue.
6. Icon: `flutter_launcher_icons` already has `remove_alpha_ios: true`. Add `ios: true` and generate.
7. `flutter build ios --release` must succeed. Then run on the plugged-in iPhone
   (`flutter run --release -d <iphone>`, or from Xcode with the team selected). Check:
   - Sign-in page with logo, sign-in remembered after closing the app.
   - Bottom bar plus "More" sheet (iPhone width), every menu item opens.
   - Camera and microphone prompts on a live class page.
8. Distribution:
   - Paid account: create the app in App Store Connect (bundle id `com.mbunie.mhub`, name "MHub"),
     `flutter build ipa --release`, upload (Transporter or `xcrun altool`/`notarytool`), and set up
     **TestFlight** with a public link. That public link is what the website's iPhone download button
     and the update check use.
   - No paid account: install on the owner's iPhone from Xcode (free provisioning lasts 7 days) and
     explain to the owner that TestFlight or the App Store needs the $99/year Apple Developer Program.

## 3. Put it on the website (same as Android and Windows)

- macOS: upload `MHub-1.2.2-macos.dmg` and set the macOS version to `v1.2.2`. iPhone: set the iOS link
  (TestFlight public link or App Store link) and the iOS version to `v1.2.2`.
- With SSH (see the handoff): `scp -P 65002` the DMG to
  `domains/mbuniehub.com/public_html/storage/app/public/app-downloads/`, then in
  `php artisan tinker` update the settings `app_macos_path` = `app-downloads/MHub-1.2.2-macos.dmg`,
  `app_macos_version` = `v1.2.2`, `app_ios_url` = `<link>`, `app_ios_version` = `v1.2.2`, and run
  `\App\Support\AppDownloads::forget()`.
- Without SSH: tell the owner to do it in **Admin → Settings → App downloads**.
- Check: `curl -s https://mbuniehub.com/api/app/latest` shows `macos` and `ios` with `1.2.2` and a
  working link, and the home page footer shows the macOS and iPhone versions.

## 4. Update check on Apple

- With the site at 1.2.2 the app must **not** ask to update (same version).
- Temporarily make the app think it is older (run once with `AppConfig.version` changed to `1.2.1`,
  **do not commit that**) and confirm the "MHub 1.2.2 is available" dialog appears. Update must open
  the DMG link on Mac and the TestFlight/App Store link on iPhone. Restore the version afterwards.

## 5. Release notes and handover

- Add a short section to `mhub/README.md` (or `docs/MHUB-APPLE-HANDOFF.md`) covering how to release the
  next version on Apple: bump `pubspec.yaml` and `AppConfig.version`, run the DMG script, upload the IPA,
  and set the site versions.
- Commit and push.
- Final report to the owner in **short Swahili**:
  - What works on Mac and on iPhone.
  - Where the DMG is.
  - What is on the website.
  - What is still waiting on them (Apple account, Firebase plist, upload in admin).
  - Anything not tested.
