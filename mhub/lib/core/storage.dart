import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Thin wrapper around secure storage for the auth token.
class TokenStorage {
  TokenStorage(this._storage);

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'mhub_api_token';

  /// Never throws: a storage failure (e.g. an unreadable file on Windows)
  /// counts as "no saved token", so the app shows the sign-in screen instead
  /// of waiting forever.
  Future<String?> read() async {
    try {
      return await _storage.read(key: _tokenKey).timeout(const Duration(seconds: 5));
    } catch (_) {
      return null;
    }
  }

  Future<void> write(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> clear() async {
    try {
      await _storage.delete(key: _tokenKey);
    } catch (_) {}
  }
}

final tokenStorageProvider = Provider<TokenStorage>((ref) {
  return TokenStorage(
    const FlutterSecureStorage(
      aOptions: AndroidOptions(encryptedSharedPreferences: true),
    ),
  );
});
