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
}

final chatRepositoryProvider =
    Provider((ref) => ChatRepository(ref.watch(apiClientProvider)));

final chatProvider =
    FutureProvider.autoDispose((ref) => ref.watch(chatRepositoryProvider).load());
