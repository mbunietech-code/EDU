import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/research_request.dart';

/// /api/research-requests — the signed-in customer's own requests only.
class ResearchRequestsRepository {
  ResearchRequestsRepository(this._api);

  final ApiClient _api;

  Future<({List<ResearchRequest> items, Map<String, int> counts})> list({String status = 'all', String q = ''}) async {
    final data = await _api.get('/research-requests', query: {
      if (status != 'all') 'status': status,
      if (q.isNotEmpty) 'q': q,
    }) as Map<String, dynamic>;
    final items = ((data['data'] as List?) ?? const [])
        .map((e) => ResearchRequest.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
    final counts = ((data['counts'] as Map?) ?? const {})
        .map((k, v) => MapEntry(k.toString(), (v as num).toInt()));
    return (items: items, counts: counts);
  }

  Future<RrOptions> options() async =>
      RrOptions.fromJson(await _api.get('/research-requests/options') as Map<String, dynamic>);

  Future<ResearchRequest> detail(int id) async => _one(await _api.get('/research-requests/$id'));

  Future<ResearchRequest> create(Map<String, dynamic> fields, List<String> filePaths) async {
    final clean = Map<String, dynamic>.from(fields)
      ..removeWhere((_, v) => v == null || (v is String && v.isEmpty));
    final form = FormData.fromMap(clean);
    await _attach(form, filePaths);
    return _one(await _api.post('/research-requests', data: form));
  }

  Future<void> message(int id, String body, List<String> filePaths) async {
    final form = FormData.fromMap({if (body.isNotEmpty) 'body': body});
    await _attach(form, filePaths);
    await _api.post('/research-requests/$id/messages', data: form);
  }

  Future<ResearchRequest> acceptQuote(int id) async => _one(await _api.post('/research-requests/$id/quote/accept'));

  Future<ResearchRequest> declineQuote(int id, String reason) async =>
      _one(await _api.post('/research-requests/$id/quote/decline', data: {'reason': reason}));

  Future<ResearchRequest> revision(int id, String reason) async =>
      _one(await _api.post('/research-requests/$id/revision', data: {'reason': reason}));

  Future<ResearchRequest> complete(int id, int rating, String review) async =>
      _one(await _api.post('/research-requests/$id/complete', data: {'rating': rating, if (review.isNotEmpty) 'review': review}));

  Future<ResearchRequest> cancel(int id, String reason) async =>
      _one(await _api.post('/research-requests/$id/cancel', data: {if (reason.isNotEmpty) 'reason': reason}));

  /// A 5-minute signed link to download a file in the browser.
  Future<String> fileUrl(RrFile file) async {
    final data = await _api.get(file.urlEndpoint) as Map<String, dynamic>;
    return data['url'] as String;
  }

  /// Laravel reads repeated `files[]` parts as the `files` array.
  Future<void> _attach(FormData form, List<String> paths) async {
    for (final p in paths) {
      form.files.add(MapEntry('files[]', await MultipartFile.fromFile(p)));
    }
  }

  ResearchRequest _one(dynamic data) =>
      ResearchRequest.fromJson(Map<String, dynamic>.from((data as Map)['data'] as Map));
}

final researchRequestsRepositoryProvider = Provider<ResearchRequestsRepository>(
  (ref) => ResearchRequestsRepository(ref.watch(apiClientProvider)),
);

/// List filter: all | active | completed | cancelled.
final researchRequestsFilterProvider = StateProvider.autoDispose<String>((ref) => 'all');

final researchRequestsProvider = FutureProvider.autoDispose((ref) {
  final status = ref.watch(researchRequestsFilterProvider);
  return ref.watch(researchRequestsRepositoryProvider).list(status: status);
});

final researchRequestOptionsProvider = FutureProvider<RrOptions>(
  (ref) => ref.watch(researchRequestsRepositoryProvider).options(),
);

final researchRequestDetailProvider = FutureProvider.autoDispose.family<ResearchRequest, int>(
  (ref, id) => ref.watch(researchRequestsRepositoryProvider).detail(id),
);
