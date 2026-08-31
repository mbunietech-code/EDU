// Research & Consultancy library (reader) models — /api/research/*

class ResearchCategoryRow {
  const ResearchCategoryRow({
    required this.slug,
    required this.name,
    this.description,
    this.count = 0,
  });

  final String slug;
  final String name;
  final String? description;
  final int count;

  factory ResearchCategoryRow.fromJson(Map<String, dynamic> j) =>
      ResearchCategoryRow(
        slug: j['slug'] as String? ?? '',
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        count: (j['count'] as num?)?.toInt() ?? 0,
      );
}

class ResearchCard {
  const ResearchCard({
    required this.slug,
    required this.title,
    this.summary,
    this.category,
    this.author,
    this.publishedAgo,
    this.percent,
  });

  final String slug;
  final String title;
  final String? summary;
  final String? category;
  final String? author;
  final String? publishedAgo;
  final int? percent;

  factory ResearchCard.fromJson(Map<String, dynamic> j) => ResearchCard(
        slug: j['slug'] as String? ?? '',
        title: j['title'] as String? ?? '',
        summary: j['summary'] as String?,
        category: j['category'] as String?,
        author: j['author'] as String?,
        publishedAgo: j['published_ago'] as String?,
        percent: (j['percent'] as num?)?.toInt(),
      );
}

class ResearchHome {
  const ResearchHome({
    required this.categories,
    required this.recent,
    required this.continueReading,
  });

  final List<ResearchCategoryRow> categories;
  final List<ResearchCard> recent;
  final List<ResearchCard> continueReading;

  factory ResearchHome.fromJson(Map<String, dynamic> j) => ResearchHome(
        categories: (j['categories'] as List? ?? [])
            .map((e) => ResearchCategoryRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        recent: (j['recent'] as List? ?? [])
            .map((e) => ResearchCard.fromJson(e as Map<String, dynamic>))
            .toList(),
        continueReading: (j['continue'] as List? ?? [])
            .map((e) => ResearchCard.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class ResearchSectionRef {
  const ResearchSectionRef({required this.id, required this.heading});
  final int id;
  final String heading;

  factory ResearchSectionRef.fromJson(Map<String, dynamic> j) =>
      ResearchSectionRef(
        id: (j['id'] as num).toInt(),
        heading: j['heading'] as String? ?? '',
      );
}

class ResearchChapterRef {
  const ResearchChapterRef({
    required this.id,
    required this.title,
    this.sections = const [],
  });

  final int id;
  final String title;
  final List<ResearchSectionRef> sections;

  factory ResearchChapterRef.fromJson(Map<String, dynamic> j) =>
      ResearchChapterRef(
        id: (j['id'] as num).toInt(),
        title: j['title'] as String? ?? '',
        sections: (j['sections'] as List? ?? [])
            .map((e) => ResearchSectionRef.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class ResearchDetail {
  const ResearchDetail({
    required this.slug,
    required this.title,
    this.summary,
    this.category,
    this.author,
    this.publishedAt,
    this.views = 0,
    this.percent = 0,
    this.chapters = const [],
  });

  final String slug;
  final String title;
  final String? summary;
  final String? category;
  final String? author;
  final String? publishedAt;
  final int views;
  final int percent;
  final List<ResearchChapterRef> chapters;

  factory ResearchDetail.fromJson(Map<String, dynamic> j) => ResearchDetail(
        slug: j['slug'] as String? ?? '',
        title: j['title'] as String? ?? '',
        summary: j['summary'] as String?,
        category: j['category'] as String?,
        author: j['author'] as String?,
        publishedAt: j['published_at'] as String?,
        views: (j['views'] as num?)?.toInt() ?? 0,
        percent: (j['percent'] as num?)?.toInt() ?? 0,
        chapters: (j['chapters'] as List? ?? [])
            .map((e) => ResearchChapterRef.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class ResearchSection {
  const ResearchSection({required this.id, required this.heading, required this.body});
  final int id;
  final String heading;
  final String body;

  factory ResearchSection.fromJson(Map<String, dynamic> j) => ResearchSection(
        id: (j['id'] as num).toInt(),
        heading: j['heading'] as String? ?? '',
        body: j['body'] as String? ?? '',
      );
}

class ChapterRef {
  const ChapterRef({required this.id, required this.title});
  final int id;
  final String title;

  factory ChapterRef.fromJson(Map<String, dynamic> j) =>
      ChapterRef(id: (j['id'] as num).toInt(), title: j['title'] as String? ?? '');
}

class ResearchChapterContent {
  const ResearchChapterContent({
    required this.researchTitle,
    required this.chapterId,
    required this.chapterTitle,
    required this.sections,
    this.prev,
    this.next,
    this.doneSectionIds = const [],
  });

  final String researchTitle;
  final int chapterId;
  final String chapterTitle;
  final List<ResearchSection> sections;
  final ChapterRef? prev;
  final ChapterRef? next;
  final List<int> doneSectionIds;

  factory ResearchChapterContent.fromJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>;
    final ch = d['chapter'] as Map<String, dynamic>;
    return ResearchChapterContent(
      researchTitle: d['research_title'] as String? ?? '',
      chapterId: (ch['id'] as num).toInt(),
      chapterTitle: ch['title'] as String? ?? '',
      sections: (ch['sections'] as List? ?? [])
          .map((e) => ResearchSection.fromJson(e as Map<String, dynamic>))
          .toList(),
      prev: d['prev'] == null
          ? null
          : ChapterRef.fromJson(d['prev'] as Map<String, dynamic>),
      next: d['next'] == null
          ? null
          : ChapterRef.fromJson(d['next'] as Map<String, dynamic>),
      doneSectionIds:
          (d['done_section_ids'] as List? ?? []).map((e) => (e as num).toInt()).toList(),
    );
  }
}

class MyResearchRow {
  const MyResearchRow({
    required this.slug,
    required this.title,
    this.category,
    required this.status,
    required this.statusLabel,
    this.chaptersCount = 0,
    this.reviewNote,
    this.updatedAgo,
  });

  final String slug;
  final String title;
  final String? category;
  final String status;
  final String statusLabel;
  final int chaptersCount;
  final String? reviewNote;
  final String? updatedAgo;

  factory MyResearchRow.fromJson(Map<String, dynamic> j) => MyResearchRow(
        slug: j['slug'] as String? ?? '',
        title: j['title'] as String? ?? '',
        category: j['category'] as String?,
        status: j['status'] as String? ?? 'draft',
        statusLabel: j['status_label'] as String? ?? '',
        chaptersCount: (j['chapters_count'] as num?)?.toInt() ?? 0,
        reviewNote: j['review_note'] as String?,
        updatedAgo: j['updated_ago'] as String?,
      );
}
