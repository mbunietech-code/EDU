import 'package:flutter/material.dart';

/// Research work a customer asked the team to do (not research they write).
/// Mirrors /api/research-requests.

Color _hex(String? v, [Color fallback = const Color(0xFF4F46E5)]) {
  if (v == null || v.length < 7) return fallback;
  return Color(int.parse('FF${v.substring(1, 7)}', radix: 16));
}

int _int(dynamic v) => (v as num?)?.toInt() ?? 0;

class RrOption {
  const RrOption(this.value, this.label);
  final String value;
  final String label;

  static List<RrOption> list(dynamic raw) => ((raw as List?) ?? const [])
      .map((e) => RrOption((e as Map)['value'].toString(), e['label'].toString()))
      .toList();
}

class RrOptions {
  const RrOptions({
    required this.types,
    required this.levels,
    required this.citationStyles,
    required this.priorities,
    required this.currencies,
    required this.stages,
    required this.extensions,
    required this.maxMb,
    required this.maxFiles,
  });

  final List<RrOption> types;
  final List<RrOption> levels;
  final List<RrOption> citationStyles;
  final List<RrOption> priorities;
  final List<String> currencies;
  final List<RrStep> stages;
  final List<String> extensions;
  final int maxMb;
  final int maxFiles;

  factory RrOptions.fromJson(Map<String, dynamic> j) {
    final files = (j['files'] as Map?) ?? const {};
    return RrOptions(
      types: RrOption.list(j['types']),
      levels: RrOption.list(j['levels']),
      citationStyles: RrOption.list(j['citation_styles']),
      priorities: RrOption.list(j['priorities']),
      currencies: ((j['currencies'] as List?) ?? const ['TZS']).map((e) => e.toString()).toList(),
      stages: ((j['stages'] as List?) ?? const [])
          .map((e) => RrStep.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      extensions: ((files['extensions'] as List?) ?? const []).map((e) => e.toString()).toList(),
      maxMb: _int(files['max_mb']),
      maxFiles: _int(files['max_files']),
    );
  }
}

/// One step of the tracker (or a stage in the "how it works" list).
class RrStep {
  const RrStep({required this.name, this.description, required this.color, required this.progress, this.state = 'upcoming'});

  final String name;
  final String? description;
  final Color color;
  final int progress;

  /// done | current | upcoming
  final String state;

  factory RrStep.fromJson(Map<String, dynamic> j) => RrStep(
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        color: _hex(j['color'] as String?),
        progress: _int(j['progress']),
        state: j['state'] as String? ?? 'upcoming',
      );
}

class RrFile {
  const RrFile({
    required this.id,
    required this.kind,
    required this.kindLabel,
    required this.name,
    required this.extension,
    required this.sizeLabel,
    this.note,
    required this.urlEndpoint,
  });

  final int id;
  final String kind;
  final String kindLabel;
  final String name;
  final String extension;
  final String sizeLabel;
  final String? note;

  /// API path that returns a short-lived download link.
  final String urlEndpoint;

  factory RrFile.fromJson(Map<String, dynamic> j) => RrFile(
        id: _int(j['id']),
        kind: j['kind'] as String? ?? '',
        kindLabel: j['kind_label'] as String? ?? '',
        name: j['name'] as String? ?? 'file',
        extension: j['extension'] as String? ?? '',
        sizeLabel: j['size_label'] as String? ?? '',
        note: j['note'] as String?,
        urlEndpoint: j['url_endpoint'] as String? ?? '',
      );
}

class RrMessage {
  const RrMessage({required this.id, required this.fromTeam, required this.author, this.body, required this.files, required this.time});

  final int id;
  final bool fromTeam;
  final String author;
  final String? body;
  final List<RrFile> files;
  final String time;

  factory RrMessage.fromJson(Map<String, dynamic> j) => RrMessage(
        id: _int(j['id']),
        fromTeam: j['from_team'] == true,
        author: j['author'] as String? ?? '',
        body: j['body'] as String?,
        files: ((j['files'] as List?) ?? const [])
            .map((e) => RrFile.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        time: j['time'] as String? ?? '',
      );
}

class RrHistory {
  const RrHistory({required this.label, this.note, this.progress, this.color, required this.time});

  final String label;
  final String? note;
  final int? progress;
  final Color? color;
  final String time;

  factory RrHistory.fromJson(Map<String, dynamic> j) => RrHistory(
        label: j['label'] as String? ?? '',
        note: j['note'] as String?,
        progress: (j['progress'] as num?)?.toInt(),
        color: j['color'] == null ? null : _hex(j['color'] as String?),
        time: j['time'] as String? ?? '',
      );
}

class RrQuote {
  const RrQuote({required this.label, this.note, this.status});
  final String label;
  final String? note;
  final String? status;

  factory RrQuote.fromJson(Map<String, dynamic> j) => RrQuote(
        label: j['label'] as String? ?? '',
        note: j['note'] as String?,
        status: j['status'] as String?,
      );
}

class ResearchRequest {
  const ResearchRequest({
    required this.id,
    required this.reference,
    required this.title,
    required this.typeLabel,
    required this.levelLabel,
    required this.status,
    required this.stateLabel,
    this.stageName,
    this.stageDescription,
    required this.color,
    required this.progress,
    required this.priority,
    required this.priorityLabel,
    this.deadline,
    this.deadlineLabel,
    required this.overdue,
    required this.dueSoon,
    this.pages,
    required this.quotePending,
    this.quoteLabel,
    this.createdAgo,
    required this.hasUpdate,
    // detail only
    this.description,
    this.fieldOfStudy,
    this.purpose,
    this.citationLabel,
    this.budgetLabel,
    this.quote,
    this.handledBy,
    this.rating,
    this.review,
    this.cancelReason,
    this.canCancel = false,
    this.canAnswerQuote = false,
    this.canRequestRevision = false,
    this.canComplete = false,
    this.canMessage = false,
    this.tracker = const [],
    this.deliverables = const [],
    this.attachments = const [],
    this.messages = const [],
    this.history = const [],
  });

  final int id;
  final String reference;
  final String title;
  final String typeLabel;
  final String levelLabel;
  final String status;
  final String stateLabel;
  final String? stageName;
  final String? stageDescription;
  final Color color;
  final int progress;
  final String priority;
  final String priorityLabel;
  final String? deadline;
  final String? deadlineLabel;
  final bool overdue;
  final bool dueSoon;
  final int? pages;
  final bool quotePending;
  final String? quoteLabel;
  final String? createdAgo;
  final bool hasUpdate;

  final String? description;
  final String? fieldOfStudy;
  final String? purpose;
  final String? citationLabel;
  final String? budgetLabel;
  final RrQuote? quote;
  final String? handledBy;
  final int? rating;
  final String? review;
  final String? cancelReason;
  final bool canCancel;
  final bool canAnswerQuote;
  final bool canRequestRevision;
  final bool canComplete;
  final bool canMessage;
  final List<RrStep> tracker;
  final List<RrFile> deliverables;
  final List<RrFile> attachments;
  final List<RrMessage> messages;
  final List<RrHistory> history;

  bool get isActive => status == 'active';

  factory ResearchRequest.fromJson(Map<String, dynamic> j) {
    final stage = j['stage'] as Map?;
    final can = (j['can'] as Map?) ?? const {};
    final handled = j['handled_by'] as Map?;
    List<T> list<T>(String key, T Function(Map<String, dynamic>) f) => ((j[key] as List?) ?? const [])
        .map((e) => f(Map<String, dynamic>.from(e as Map)))
        .toList();

    return ResearchRequest(
      id: _int(j['id']),
      reference: j['reference'] as String? ?? '',
      title: j['title'] as String? ?? '',
      typeLabel: j['type_label'] as String? ?? '',
      levelLabel: j['level_label'] as String? ?? '',
      status: j['status'] as String? ?? 'active',
      stateLabel: j['state_label'] as String? ?? '',
      stageName: stage?['name'] as String?,
      stageDescription: stage?['description'] as String?,
      color: _hex(j['color'] as String?),
      progress: _int(j['progress']),
      priority: j['priority'] as String? ?? 'normal',
      priorityLabel: j['priority_label'] as String? ?? '',
      deadline: j['deadline'] as String?,
      deadlineLabel: j['deadline_label'] as String?,
      overdue: j['overdue'] == true,
      dueSoon: j['due_soon'] == true,
      pages: (j['pages'] as num?)?.toInt(),
      quotePending: j['quote_pending'] == true,
      quoteLabel: j['quote_label'] as String?,
      createdAgo: j['created_ago'] as String?,
      hasUpdate: j['has_update'] == true,
      description: j['description'] as String?,
      fieldOfStudy: j['field_of_study'] as String?,
      purpose: j['purpose'] as String?,
      citationLabel: j['citation_label'] as String?,
      budgetLabel: j['budget_label'] as String?,
      quote: j['quote'] is Map ? RrQuote.fromJson(Map<String, dynamic>.from(j['quote'] as Map)) : null,
      handledBy: handled == null ? null : [handled['name'], handled['job_title']].whereType<String>().join(' · '),
      rating: (j['rating'] as num?)?.toInt(),
      review: j['review'] as String?,
      cancelReason: j['cancel_reason'] as String?,
      canCancel: can['cancel'] == true,
      canAnswerQuote: can['answer_quote'] == true,
      canRequestRevision: can['request_revision'] == true,
      canComplete: can['complete'] == true,
      canMessage: can['message'] == true,
      tracker: list('tracker', RrStep.fromJson),
      deliverables: list('deliverables', RrFile.fromJson),
      attachments: list('attachments', RrFile.fromJson),
      messages: list('messages', RrMessage.fromJson),
      history: list('history', RrHistory.fromJson),
    );
  }
}
