import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

// Writing research from the app — /my-research/* (ResearchAuthorController).

class DraftSection {
  DraftSection(Map<String, dynamic> j)
      : id = (j['id'] as num).toInt(),
        chapterId = (j['chapter_id'] as num?)?.toInt() ?? 0,
        heading = j['heading'] as String? ?? '',
        words = (j['words'] as num?)?.toInt() ?? 0,
        body = j['body'] as String?;

  final int id;
  final int chapterId;
  final String heading;
  final int words;

  /// Only present when the section itself was fetched.
  final String? body;
}

class DraftChapter {
  DraftChapter(Map<String, dynamic> j)
      : id = (j['id'] as num).toInt(),
        title = j['title'] as String? ?? '',
        sections = ((j['sections'] as List?) ?? const [])
            .map((e) => DraftSection(e as Map<String, dynamic>))
            .toList();

  final int id;
  final String title;
  final List<DraftSection> sections;
}

class ResearchDraft {
  ResearchDraft(Map<String, dynamic> j)
      : id = (j['id'] as num).toInt(),
        title = j['title'] as String? ?? '',
        summary = j['summary'] as String?,
        categoryId = (j['category_id'] as num?)?.toInt(),
        category = j['category'] as String?,
        status = j['status'] as String? ?? 'draft',
        statusLabel = j['status_label'] as String? ?? '',
        reviewNote = j['review_note'] as String?,
        canEdit = j['can_edit'] as bool? ?? false,
        canSubmit = j['can_submit'] as bool? ?? false,
        chapters = ((j['chapters'] as List?) ?? const [])
            .map((e) => DraftChapter(e as Map<String, dynamic>))
            .toList();

  final int id;
  final String title;
  final String? summary;
  final int? categoryId;
  final String? category;
  final String status;
  final String statusLabel;
  final String? reviewNote;
  final bool canEdit;
  final bool canSubmit;
  final List<DraftChapter> chapters;
}

class ResearchAuthorRepository {
  ResearchAuthorRepository(this._api);
  final ApiClient _api;

  static const _base = '/my-research';

  Map<String, dynamic> _data(dynamic body) => (body as Map<String, dynamic>)['data'] as Map<String, dynamic>;

  Future<List<({int id, String name})>> categories() async {
    final body = await _api.get('$_base/categories') as Map<String, dynamic>;
    return ((body['data'] as List?) ?? const [])
        .map((e) => (id: ((e as Map)['id'] as num).toInt(), name: e['name'] as String))
        .toList();
  }

  Future<ResearchDraft> create({required String title, int? categoryId, String? summary}) async =>
      ResearchDraft(_data(await _api.post(_base, data: {
        'title': title,
        'research_category_id': ?categoryId,
        if (summary != null && summary.isNotEmpty) 'summary': summary,
      })));

  Future<ResearchDraft> get(int id) async => ResearchDraft(_data(await _api.get('$_base/$id')));

  Future<ResearchDraft> updateDetails(int id, {required String title, int? categoryId, String? summary}) async =>
      ResearchDraft(_data(await _api.put('$_base/$id', data: {
        'title': title,
        'research_category_id': categoryId,
        'summary': summary,
      })));

  Future<void> delete(int id) => _api.delete('$_base/$id');

  Future<String> submit(int id) async =>
      ((await _api.post('$_base/$id/submit')) as Map<String, dynamic>)['message'] as String? ?? 'Submitted.';

  /// Import a .docx / .md / .txt file, or pasted text ("# Chapter", "## Section").
  Future<String> import(int id, {String? filePath, String? text}) async {
    final form = FormData.fromMap({
      if (filePath != null) 'document': await MultipartFile.fromFile(filePath),
      if (text != null && text.isNotEmpty) 'text': text,
    });
    return ((await _api.post('$_base/$id/import', data: form)) as Map<String, dynamic>)['message'] as String? ??
        'Imported.';
  }

  Future<void> reorder(int id, String type, List<int> ids) =>
      _api.post('$_base/$id/reorder', data: {'type': type, 'ids': ids});

  Future<void> addChapter(int researchId, String title) => _api.post('$_base/$researchId/chapters', data: {'title': title});

  Future<void> renameChapter(int chapterId, String title) =>
      _api.put('$_base/chapters/$chapterId', data: {'title': title});

  Future<void> deleteChapter(int chapterId) => _api.delete('$_base/chapters/$chapterId');

  Future<DraftSection> addSection(int chapterId, {required String heading, String body = ''}) async =>
      DraftSection(_data(await _api.post('$_base/chapters/$chapterId/sections', data: {'heading': heading, 'body': body})));

  Future<DraftSection> section(int id) async => DraftSection(_data(await _api.get('$_base/sections/$id')));

  Future<void> saveSection(int id, {required String heading, required String body}) =>
      _api.put('$_base/sections/$id', data: {'heading': heading, 'body': body});

  Future<void> deleteSection(int id) => _api.delete('$_base/sections/$id');
}

final researchAuthorRepositoryProvider = Provider((ref) => ResearchAuthorRepository(ref.watch(apiClientProvider)));

final researchDraftProvider =
    FutureProvider.autoDispose.family<ResearchDraft, int>((ref, id) => ref.watch(researchAuthorRepositoryProvider).get(id));

final researchCategoriesProvider =
    FutureProvider.autoDispose((ref) => ref.watch(researchAuthorRepositoryProvider).categories());
