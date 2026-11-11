#!/bin/bash
# Builds MHub for macOS and packages it as installer/Output/MHub-<version>-macos.dmg.
#
#   ./installer/macos/make-dmg.sh            # ad-hoc signed (no Apple Developer account)
#   SIGN_ID="Developer ID Application: NAME (TEAMID)" NOTARY_PROFILE=mhub ./installer/macos/make-dmg.sh
#
# With SIGN_ID the app is signed with hardened runtime; with NOTARY_PROFILE too
# (created once with `xcrun notarytool store-credentials mhub`) the DMG is
# notarized and stapled.
set -euo pipefail

cd "$(dirname "$0")/../.."   # mhub/
VERSION=$(sed -n 's/^version: *\([0-9.]*\)+.*/\1/p' pubspec.yaml)
APP=build/macos/Build/Products/Release/MHub.app
OUT=installer/Output
DMG="$OUT/MHub-$VERSION-macos.dmg"

grep -q "version = '$VERSION'" lib/core/config.dart \
  || { echo "AppConfig.version in lib/core/config.dart is not $VERSION"; exit 1; }

flutter build macos --release

if [ -n "${SIGN_ID:-}" ]; then
  codesign --force --deep --options runtime --timestamp \
    --entitlements macos/Runner/Release.entitlements -s "$SIGN_ID" "$APP"
else
  codesign --force --deep --entitlements macos/Runner/Release.entitlements -s - "$APP"
fi
codesign --verify --deep --strict "$APP"

STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
cp -R "$APP" "$STAGE/"
ln -s /Applications "$STAGE/Applications"
mkdir -p "$OUT"
rm -f "$DMG"
hdiutil create -volname "MHub $VERSION" -srcfolder "$STAGE" -ov -format UDZO "$DMG"

if [ -n "${SIGN_ID:-}" ]; then
  codesign --force --timestamp -s "$SIGN_ID" "$DMG"
  if [ -n "${NOTARY_PROFILE:-}" ]; then
    xcrun notarytool submit "$DMG" --keychain-profile "$NOTARY_PROFILE" --wait
    xcrun stapler staple "$DMG"
  fi
fi

echo "Done: $(pwd)/$DMG"
