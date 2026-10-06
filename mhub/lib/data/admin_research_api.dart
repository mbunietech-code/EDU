import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

/// A research paper in the admin review queue.
class ReviewResearch {
  const ReviewResearch({
    required this.id,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.chaptersCount,
    this.category,
    this.author,
    this.reviewer,
    this.updatedAt,
  });

  final int id;
  final String title;
  final String status;
  final String statusLabel;
  final int chaptersCount;
  final String? category;
  final String? author;
  final String? reviewer;
  final DateTime? updatedAt;

  factory ReviewResearch.fromJson(Map<String, dynamic> j) => ReviewResearch(
        id: (j['id'] as num).toInt(),
        title: j['title'] as String? ?? '',
        status: j['status'] as String? ?? 'draft',
        statusLabel: j['status_label'] as String? ?? '',
        chaptersCount: (j['chapters_count'] as num?)?.toInt() ?? 0,
        category: j['category'] as String?,
        author: j['author'] as String?,
        reviewer: j['reviewer'] as String?,
        updatedAt: DateTime.tryParse(j['updated_at'] as String? ?? '')?.toLocal(),
      );
}

class ReviewQueue {
  const ReviewQueue(this.items, this.reviewCount, this.canManage);
  final List<ReviewResearch> items;
  final int reviewCount;
  final bool canManage;
}

class ReviewSection {
  const ReviewSection(this.id, this.heading, this.body);
  final int id;
  final String heading;
  final String body;
}

class ReviewChapter {
  const ReviewChapter(this.id, this.title, this.sections);
  final int id;
  final String title;
  final List<ReviewSection> sections;
}

class ReviewHistory {
  const ReviewHistory(this.action, this.comment, this.reviewer, this.at);
  final String action;
  final String? comment;
  final String? reviewer;
  final DateTime? at;
}

class ReviewResearchDetail {
  const ReviewResearchDetail({
    required this.row,
    required this.canManage,
    required this.chapters,
    required this.reviews,
    this.summary,
    this.reviewNote,
    this.authorEmail,
  });

  final ReviewResearch row;
  final bool canManage;
  final List<ReviewChapter> chapters;
  final List<ReviewHistory> reviews;
  final String? summary;
  final String? reviewNote;
  final String? authorEmail;

  factory ReviewResearchDetail.fromJson(Map<String, dynamic> j) => ReviewResearchDetail(
        row: ReviewResearch.fromJson(j),
        canManage: j['can_manage'] == true,
        summary: j['summary'] as String?,
        reviewNote: j['review_note'] as String?,
        authorEmail: j['author_email'] as String?,
        chapters: [
          for (final c in (j['chapters'] as List? ?? const []).cast<Map<String, dynamic>>())
            ReviewChapter(
              (c['id'] as num).toInt(),
              c['title'] as String? ?? '',
              [
                for (final s in (c['sections'] as List? ?? const []).cast<Map<String, dynamic>>())
                  ReviewSection((s['id'] as num).toInt(), s['heading'] as String? ?? '', s['body'] as String? ?? ''),
              ],
            ),
        ],
        reviews: [
          for (final r in (j['reviews'] as List? ?? const []).cast<Map<String, dynamic>>())
            ReviewHistory(
              r['action'] as String? ?? '',
              r['comment'] as String?,
              r['reviewer'] as String?,
              DateTime.tryParse(r['created_at'] as String? ?? '')?.toLocal(),
            ),
        ],
      );
}

class ResearchCategoryRow {
  const ResearchCategoryRow({
    required this.id,
    required this.name,
    required this.slug,
    required this.position,
    required this.count,
    this.description,
  });

  final int id;
  final String name;
  final String slug;
  final int position;
  final int count;
  final String? description;

  factory ResearchCategoryRow.fromJson(Map<String, dynamic> j) => ResearchCategoryRow(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        position: (j['position'] as num?)?.toInt() ?? 0,
        count: (j['researches_count'] as num?)?.toInt() ?? 0,
        description: j['description'] as String?,
      );
}

class AdminResearchRepository {
  AdminResearchRepository(this._api);
  final ApiClient _api;

  Future<ReviewQueue> queue(String status, String search) async {
    final body = await _api.get('/admin/research', query: {
      'status': status,
      if (search.isNotEmpty) 'q': search,
    }) as Map<String, dynamic>;
    final meta = body['meta'] as Map<String, dynamic>? ?? const {};
    return ReviewQueue(
      (body['data'] as List).map((e) => ReviewResearch.fromJson(e as Map<String, dynamic>)).toList(),
      (meta['review_count'] as num?)?.toInt() ?? 0,
      meta['can_manage'] == true,
    );
  }

  Future<ReviewResearchDetail> detail(int id) async => ReviewResearchDetail.fromJson(
      ((await _api.get('/admin/research/$id')) as Map<String, dynamic>)['data'] as Map<String, dynamic>);

  /// approve, request-changes, reject or unpublish. Returns the server message.
  Future<String> decide(int id, String action, {String? comment}) async {
    final body = await _api.post('/admin/research/$id/$action', data: {
      if (comment != null && comment.isNotEmpty) 'comment': comment,
    }) as Map<String, dynamic>;
    return body['message'] as String? ?? 'Saved.';
  }

  Future<void> delete(int id, String reason) => _api.delete('/admin/research/$id', data: {'reason': reason});

  Future<List<ResearchCategoryRow>> categories() async =>
      (((await _api.get('/admin/research-categories')) as Map<String, dynamic>)['data'] as List)
          .map((e) => ResearchCategoryRow.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<void> saveCategory({int? id, required String name, String? description, int? position}) {
    final data = {
      'name': name,
      'description': description,
      'position': ?position,
    };
    return id == null
        ? _api.post('/admin/research-categories', data: data)
        : _api.put('/admin/research-categories/$id', data: data);
  }

  Future<void> deleteCategory(int id) => _api.delete('/admin/research-categories/$id');
}

final adminResearchRepositoryProvider =
    Provider((ref) => AdminResearchRepository(ref.watch(apiClientProvider)));

final adminResearchQueueProvider = FutureProvider.autoDispose
    .family<ReviewQueue, ({String status, String search})>(
        (ref, f) => ref.watch(adminResearchRepositoryProvider).queue(f.status, f.search));

final adminResearchDetailProvider = FutureProvider.autoDispose
    .family<ReviewResearchDetail, int>((ref, id) => ref.watch(adminResearchRepositoryProvider).detail(id));

final adminResearchCategoriesProvider =
    FutureProvider.autoDispose((ref) => ref.watch(adminResearchRepositoryProvider).categories());
