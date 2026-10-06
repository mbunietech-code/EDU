import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/tool.dart' show ChatMessage;

class ChatState {
  const ChatState({required this.messages, required this.supportOnline});
  final List<ChatMessage> messages;
  final bool supportOnline;
}

class ChatRepository {
  ChatRepository(this._api);
  final ApiClient _api;

  Future<ChatState> load() async {
    final data = await _api.get('/chat') as Map<String, dynamic>;
    return ChatState(
      messages: (data['messages'] as List)
          .map((e) => ChatMessage.fromJson(e as Map<String, dynamic>))
          .toList(),
      supportOnline: data['support_online'] == true,
    );
  }

  Future<ChatMessage> send(String body) async {
    final data = await _api.post('/chat', data: {'body': body}) as Map<String, dynamic>;
    return ChatMessage.fromJson(data['data'] as Map<String, dynamic>);
  }

  /// Send a photo, video or voice note (type: image, video or audio).
  Future<ChatMessage> sendFile(String path, String type, {String? caption}) async {
    final form = FormData.fromMap({
      'type': type,
      if (caption != null && caption.isNotEmpty) 'body': caption,
      'file': await MultipartFile.fromFile(path),
    });
    final data = await _api.post('/chat', data: form) as Map<String, dynamic>;
    return ChatMessage.fromJson(data['data'] as Map<String, dynamic>);
  }
}

final chatRepositoryProvider =
    Provider((ref) => ChatRepository(ref.watch(apiClientProvider)));

final chatProvider =
    FutureProvider.autoDispose((ref) => ref.watch(chatRepositoryProvider).load());
