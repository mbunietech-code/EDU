# Firebase push notifications — setup

The app is wired for FCM push (order placed → notification on the phone and in
the app). It needs two files from a free Firebase project.

## 1. Create the Firebase project

1. https://console.firebase.google.com → **Add project** → name it `MHub` →
   Continue (Google Analytics optional) → Create project.

## 2. Android app + `google-services.json`

1. In the project, click **Add app** → **Android**.
2. **Android package name:** `com.mbunie.mhub`
3. Register app → **Download `google-services.json`**.
4. Put it at:  `prod/mhub/android/app/google-services.json`
   *(git-ignored — do not commit it)*

The Android build will fail until this file exists.

## 3. Backend service-account key

1. Firebase console → ⚙ **Project settings** → **Service accounts** tab.
2. **Generate new private key** → downloads a JSON file.
3. Upload it to the server at:  `prod/storage/app/firebase/service-account.json`
   *(git-ignored)*
4. That's it — `config/services.php` → `fcm.credentials` points there and
   `fcm.project_id` is read from the file.

## 4. Apply the DB change

The `device_tokens` table:
- Admin → Database → **Apply** (alter `0004_2026_08_31_device_tokens.sql`), or
- phpMyAdmin: `prod/database/alters/0004_2026_08_31_device_tokens.sql`

## How it works

- On sign-in the app registers its FCM token via `POST /api/device-tokens`.
- Every Laravel database notification (new order, payment approved, chat message,
  …) is also pushed to that user's devices — see
  `App\Listeners\PushDatabaseNotification` + `App\Services\FcmService`.
- The send is deferred until after the HTTP response, so it never slows a request.
- Invalid/expired tokens are pruned automatically.

## Desktop push (later)

Windows/macOS/Linux push needs extra platform config (`firebase_options.dart`
via `flutterfire configure`, plus APNs for macOS). Android is done first.
