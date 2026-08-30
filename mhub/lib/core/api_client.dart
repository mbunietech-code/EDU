import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'config.dart';
import 'storage.dart';

/// Raised for any non-2xx response or transport failure.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});

  final String message;
  final int? statusCode;

  /// Laravel-style field errors: { "email": ["The email field is required."] }
  final Map<String, List<String>>? errors;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}

/// Callback invoked when the server reports the token is no longer valid.
typedef UnauthorizedHandler = void Function();

class ApiClient {
  ApiClient(this._tokenStorage, {UnauthorizedHandler? onUnauthorized})
      : _dio = Dio(
          BaseOptions(
            baseUrl: AppConfig.apiUrl,
            connectTimeout: const Duration(seconds: 15),
            receiveTimeout: const Duration(seconds: 20),
            headers: {'Accept': 'application/json'},
            validateStatus: (code) => code != null && code < 500,
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.read();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onResponse: (response, handler) {
          if (response.statusCode == 401) {
            onUnauthorized?.call();
          }
          handler.next(response);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get(path, queryParameters: query));

  /// Fetch raw bytes (e.g. an authenticated image/file download).
  Future<List<int>> getBytes(String path) async {
    try {
      final res = await _dio.get<List<int>>(
        path,
        options: Options(responseType: ResponseType.bytes),
      );
      final code = res.statusCode ?? 0;
      if (code >= 200 && code < 300 && res.data != null) return res.data!;
      throw ApiException('Request failed ($code).', statusCode: code);
    } on DioException catch (e) {
      throw ApiException(_transportMessage(e), statusCode: e.response?.statusCode);
    }
  }

  Future<dynamic> post(String path, {Object? data}) =>
      _send(() => _dio.post(path, data: data));

  Future<dynamic> put(String path, {Object? data}) =>
      _send(() => _dio.put(path, data: data));

  Future<dynamic> delete(String path, {Object? data}) =>
      _send(() => _dio.delete(path, data: data));

  Future<dynamic> _send(Future<Response> Function() request) async {
    late final Response response;
    try {
      response = await request();
    } on DioException catch (e) {
      throw ApiException(
        _transportMessage(e),
        statusCode: e.response?.statusCode,
      );
    }

    final code = response.statusCode ?? 0;
    if (code >= 200 && code < 300) {
      return response.data;
    }

    final data = response.data;
    String message = 'Request failed ($code).';
    Map<String, List<String>>? errors;
    if (data is Map) {
      if (data['message'] is String && (data['message'] as String).isNotEmpty) {
        message = data['message'] as String;
      }
      if (data['errors'] is Map) {
        errors = (data['errors'] as Map).map(
          (key, value) => MapEntry(
            key.toString(),
            (value as List).map((e) => e.toString()).toList(),
          ),
        );
      }
    }
    throw ApiException(message, statusCode: code, errors: errors);
  }

  String _transportMessage(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'The server took too long to respond.';
      case DioExceptionType.connectionError:
        return 'Cannot reach the server. Check your connection.';
      default:
        return e.message ?? 'Network error.';
    }
  }
}

/// Set by the auth layer so the client can react to 401s.
final unauthorizedSignalProvider = StateProvider<int>((ref) => 0);

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(tokenStorageProvider);
  return ApiClient(
    storage,
    onUnauthorized: () {
      ref.read(unauthorizedSignalProvider.notifier).state++;
    },
  );
});
