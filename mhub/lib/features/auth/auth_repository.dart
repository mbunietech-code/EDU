import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/user.dart';

class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  /// Logs in and returns the plain-text token + user.
  Future<({String token, AppUser user})> login({
    required String email,
    required String password,
    String? deviceName,
  }) async {
    final data = await _api.post(
      '/login',
      data: {
        'email': email,
        'password': password,
        'device_name': deviceName ?? 'mhub-app',
      },
    );
    final map = data as Map<String, dynamic>;
    return (
      token: map['token'] as String,
      user: AppUser.fromJson(map['user'] as Map<String, dynamic>),
    );
  }

  /// Starts registration. The server sends a code and does not create the
  /// real user row until [verifyRegistration] succeeds.
  Future<({String email, String message})> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? deviceName,
  }) async {
    final data = await _api.post(
      '/register',
      data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'device_name': deviceName ?? 'mhub-app',
      },
    );
    final map = data as Map<String, dynamic>;
    return (
      email: map['email'] as String? ?? email,
      message: map['message'] as String? ?? 'Verification code sent.',
    );
  }

  Future<({String token, AppUser user})> verifyRegistration({
    required String email,
    required String code,
    String? deviceName,
  }) async {
    final data = await _api.post(
      '/register/verify',
      data: {
        'email': email,
        'code': code,
        'device_name': deviceName ?? 'mhub-app',
      },
    );
    final map = data as Map<String, dynamic>;
    return (
      token: map['token'] as String,
      user: AppUser.fromJson(map['user'] as Map<String, dynamic>),
    );
  }

  Future<AppUser> me() async {
    final data = await _api.get('/user');
    return AppUser.fromJson(data as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } on ApiException {
      // Even if the call fails we still clear local state.
    }
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(apiClientProvider));
});
