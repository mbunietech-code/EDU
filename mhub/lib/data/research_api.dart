import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/research.dart';

class ResearchRepository {
  ResearchRepository(this._api);
  final ApiClient _api;

  Future<ResearchHome> home() async =>
      ResearchHome.fromJson(await _api.get('/research') as Map<String, dynamic>);

  Future<({String name, String? description, List<ResearchCard> items})> category(
      String slug) async {
    final data = await _api.get('/research/category/$slug') as Map<String, dynamic>;
    final c = data['category'] as Map<String, dynamic>;
    return (
      name: c['name'] as String? ?? '',
      description: c['description'] as String?,
      items: (data['data'] as List)
          .map((e) => ResearchCard.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<ResearchDetail> detail(String slug) async => ResearchDetail.fromJson(
      (await _api.get('/research/$slug') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<ResearchChapterContent> chapter(String slug, int chapterId) async =>
      ResearchChapterContent.fromJson(
          await _api.get('/research/$slug/chapters/$chapterId')
              as Map<String, dynamic>);

  Future<({int percent, List<int> done})> markSection(
      String slug, int sectionId, bool done) async {
    final res = await _api.post('/research/$slug/progress', data: {
      'section_id': sectionId,
      'done': done,
    }) as Map<String, dynamic>;
    final d = res['data'] as Map<String, dynamic>;
    return (
      percent: (d['percent'] as num).toInt(),
      done: (d['done_section_ids'] as List).map((e) => (e as num).toInt()).toList(),
    );
  }

  Future<List<MyResearchRow>> mine() async =>
      ((await _api.get('/research/mine') as Map<String, dynamic>)['data'] as List)
          .map((e) => MyResearchRow.fromJson(e as Map<String, dynamic>))
          .toList();
}

final researchRepositoryProvider =
    Provider((ref) => ResearchRepository(ref.watch(apiClientProvider)));

final researchHomeProvider = FutureProvider.autoDispose<ResearchHome>(
    (ref) => ref.watch(researchRepositoryProvider).home());

final researchCategoryProvider = FutureProvider.autoDispose
    .family<({String name, String? description, List<ResearchCard> items}), String>(
        (ref, slug) => ref.watch(researchRepositoryProvider).category(slug));

final researchDetailProvider = FutureProvider.autoDispose
    .family<ResearchDetail, String>(
        (ref, slug) => ref.watch(researchRepositoryProvider).detail(slug));

final researchChapterProvider = FutureProvider.autoDispose
    .family<ResearchChapterContent, ({String slug, int chapterId})>(
        (ref, args) => ref
            .watch(researchRepositoryProvider)
            .chapter(args.slug, args.chapterId));

final myResearchProvider = FutureProvider.autoDispose<List<MyResearchRow>>(
    (ref) => ref.watch(researchRepositoryProvider).mine());
