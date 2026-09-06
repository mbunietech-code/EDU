import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../core/notifications/notification_center.dart';
import '../../core/storage.dart';
import '../../models/user.dart';
import 'auth_repository.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  const AuthState({
    this.status = AuthStatus.unknown,
    this.user,
    this.error,
    this.busy = false,
  });

  final AuthStatus status;
  final AppUser? user;
  final String? error;
  final bool busy;

  AuthState copyWith({
    AuthStatus? status,
    AppUser? user,
    String? error,
    bool? busy,
    bool clearError = false,
    bool clearUser = false,
  }) {
    return AuthState(
      status: status ?? this.status,
      user: clearUser ? null : (user ?? this.user),
      error: clearError ? null : (error ?? this.error),
      busy: busy ?? this.busy,
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._ref) : super(const AuthState()) {
    _restore();
    _ref.listen(unauthorizedSignalProvider, (_, _) => _forceLogout());
  }

  final Ref _ref;

  AuthRepository get _repo => _ref.read(authRepositoryProvider);
  TokenStorage get _tokens => _ref.read(tokenStorageProvider);

  Future<void> _restore() async {
    final token = await _tokens.read();
    if (token == null || token.isEmpty) {
      state = state.copyWith(status: AuthStatus.unauthenticated);
      return;
    }
    try {
      final user = await _repo.me();
      state = state.copyWith(status: AuthStatus.authenticated, user: user);
      unawaited(_ref.read(notificationCenterProvider).start());
    } on ApiException {
      await _tokens.clear();
      state = state.copyWith(
        status: AuthStatus.unauthenticated,
        clearUser: true,
      );
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(busy: true, clearError: true);
    try {
      final result = await _repo.login(email: email, password: password);
      await _tokens.write(result.token);
      state = state.copyWith(
        status: AuthStatus.authenticated,
        user: result.user,
        busy: false,
      );
      // Register this device for push (fire-and-forget).
      unawaited(_ref.read(notificationCenterProvider).start());
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(busy: false, error: e.message);
      return false;
    }
  }

  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    state = state.copyWith(busy: true, clearError: true);
    try {
      final result = await _repo.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      await _tokens.write(result.token);
      state = state.copyWith(
        status: AuthStatus.authenticated,
        user: result.user,
        busy: false,
      );
      unawaited(_ref.read(notificationCenterProvider).start());
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(busy: false, error: e.message);
      return false;
    }
  }

  /// Re-fetch the current user (e.g. after editing the profile).
  Future<void> refreshUser() async {
    try {
      state = state.copyWith(user: await _repo.me());
    } on ApiException {
      // keep the current user
    }
  }

  Future<void> logout() async {
    state = state.copyWith(busy: true);
    unawaited(_ref.read(notificationCenterProvider).stop());
    await _repo.logout();
    await _tokens.clear();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  Future<void> _forceLogout() async {
    await _tokens.clear();
    if (state.status == AuthStatus.authenticated) {
      state = const AuthState(
        status: AuthStatus.unauthenticated,
        error: 'Your session has expired. Please sign in again.',
      );
    }
  }
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(ref);
});
