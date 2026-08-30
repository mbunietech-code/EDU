// Models for admin "System" area (/api/admin/error-logs, /deleted-records,
// /activity, /settings, /subscriptions, /payment-methods).

class ErrorLogRow {
  const ErrorLogRow({
    required this.id,
    required this.exception,
    required this.message,
    this.location,
    this.occurrences = 1,
    this.resolved = false,
    this.lastSeenAgo,
  });

  final int id;
  final String exception;
  final String message;
  final String? location;
  final int occurrences;
  final bool resolved;
  final String? lastSeenAgo;

  factory ErrorLogRow.fromJson(Map<String, dynamic> j) => ErrorLogRow(
        id: (j['id'] as num).toInt(),
        exception: j['exception'] as String? ?? 'Error',
        message: j['message'] as String? ?? '',
        location: j['location'] as String?,
        occurrences: (j['occurrences'] as num?)?.toInt() ?? 1,
        resolved: j['resolved'] == true,
        lastSeenAgo: j['last_seen_ago'] as String?,
      );
}

class ErrorLogDetail {
  const ErrorLogDetail({
    required this.id,
    required this.exception,
    required this.message,
    this.file,
    this.line,
    this.url,
    this.method,
    this.occurrences = 1,
    this.trace,
    this.resolved = false,
    this.resolvedBy,
    this.userName,
    this.userEmail,
    this.firstSeen,
    this.lastSeen,
  });

  final int id;
  final String exception;
  final String message;
  final String? file;
  final int? line;
  final String? url;
  final String? method;
  final int occurrences;
  final String? trace;
  final bool resolved;
  final String? resolvedBy;
  final String? userName;
  final String? userEmail;
  final String? firstSeen;
  final String? lastSeen;

  factory ErrorLogDetail.fromJson(Map<String, dynamic> j) => ErrorLogDetail(
        id: (j['id'] as num).toInt(),
        exception: j['exception'] as String? ?? 'Error',
        message: j['message'] as String? ?? '',
        file: j['file'] as String?,
        line: (j['line'] as num?)?.toInt(),
        url: j['url'] as String?,
        method: j['method'] as String?,
        occurrences: (j['occurrences'] as num?)?.toInt() ?? 1,
        trace: j['trace'] as String?,
        resolved: j['resolved'] == true,
        resolvedBy: j['resolved_by'] as String?,
        userName: (j['user'] as Map<String, dynamic>?)?['name'] as String?,
        userEmail: (j['user'] as Map<String, dynamic>?)?['email'] as String?,
        firstSeen: j['first_seen'] as String?,
        lastSeen: j['last_seen'] as String?,
      );
}

class DeletedRecordRow {
  const DeletedRecordRow({
    required this.id,
    required this.entity,
    required this.label,
    this.reason,
    this.deletedBy,
    this.deletedAgo,
  });

  final int id;
  final String entity;
  final String label;
  final String? reason;
  final String? deletedBy;
  final String? deletedAgo;

  factory DeletedRecordRow.fromJson(Map<String, dynamic> j) => DeletedRecordRow(
        id: (j['id'] as num).toInt(),
        entity: j['entity'] as String? ?? '',
        label: j['label'] as String? ?? '',
        reason: j['reason'] as String?,
        deletedBy: j['deleted_by'] as String?,
        deletedAgo: j['deleted_ago'] as String?,
      );
}

class DeletedRecordDetail {
  const DeletedRecordDetail({
    required this.id,
    required this.entity,
    required this.label,
    this.reason,
    this.deletedBy,
    this.deletedAt,
    this.snapshot = const {},
  });

  final int id;
  final String entity;
  final String label;
  final String? reason;
  final String? deletedBy;
  final String? deletedAt;
  final Map<String, dynamic> snapshot;

  factory DeletedRecordDetail.fromJson(Map<String, dynamic> j) =>
      DeletedRecordDetail(
        id: (j['id'] as num).toInt(),
        entity: j['entity'] as String? ?? '',
        label: j['label'] as String? ?? '',
        reason: j['reason'] as String?,
        deletedBy: j['deleted_by'] as String?,
        deletedAt: j['deleted_at'] as String?,
        snapshot: (j['snapshot'] as Map?)?.cast<String, dynamic>() ?? const {},
      );
}

class ActivityRow {
  const ActivityRow({
    required this.id,
    required this.action,
    this.entity,
    required this.actor,
    this.metadata = const {},
    this.createdAgo,
  });

  final int id;
  final String action;
  final String? entity;
  final String actor;
  final Map<String, dynamic> metadata;
  final String? createdAgo;

  factory ActivityRow.fromJson(Map<String, dynamic> j) => ActivityRow(
        id: (j['id'] as num).toInt(),
        action: j['action'] as String? ?? '',
        entity: j['entity'] as String?,
        actor: j['actor'] as String? ?? 'System',
        metadata: (j['metadata'] as Map?)?.cast<String, dynamic>() ?? const {},
        createdAgo: j['created_ago'] as String?,
      );
}

class AdminSettings {
  const AdminSettings({
    required this.adminDebugEnabled,
    this.logoUrl,
    this.faviconUrl,
    this.downloads = const [],
    this.note,
  });

  final bool adminDebugEnabled;
  final String? logoUrl;
  final String? faviconUrl;
  final List<DownloadRow> downloads;
  final String? note;

  factory AdminSettings.fromJson(Map<String, dynamic> j) {
    final d = (j['data'] as Map<String, dynamic>?) ?? j;
    final branding = (d['branding'] as Map<String, dynamic>?) ?? const {};
    return AdminSettings(
      adminDebugEnabled: d['admin_debug_enabled'] == true,
      logoUrl: branding['logo_url'] as String?,
      faviconUrl: branding['favicon_url'] as String?,
      downloads: (d['downloads'] as List?)
              ?.map((e) => DownloadRow.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      note: d['note'] as String?,
    );
  }
}

class DownloadRow {
  const DownloadRow({
    required this.platform,
    required this.label,
    this.version,
    this.url,
    this.hasFile = false,
  });

  final String platform;
  final String label;
  final String? version;
  final String? url;
  final bool hasFile;

  factory DownloadRow.fromJson(Map<String, dynamic> j) => DownloadRow(
        platform: j['platform'] as String? ?? '',
        label: j['label'] as String? ?? '',
        version: j['version'] as String?,
        url: j['url'] as String?,
        hasFile: j['has_file'] == true,
      );
}

class AdminSubscription {
  const AdminSubscription({
    required this.id,
    required this.customerName,
    required this.product,
    this.plan,
    required this.status,
    this.expiryDate,
    this.daysRemaining,
    this.customerEmail,
    this.orderNumber,
    this.startDate,
  });

  final int id;
  final String customerName;
  final String product;
  final String? plan;
  final String status;
  final String? expiryDate;
  final int? daysRemaining;
  final String? customerEmail;
  final String? orderNumber;
  final String? startDate;

  factory AdminSubscription.fromJson(Map<String, dynamic> j) => AdminSubscription(
        id: (j['id'] as num).toInt(),
        customerName: j['customer_name'] as String? ?? '—',
        product: j['product'] as String? ?? '—',
        plan: j['plan'] as String?,
        status: j['status'] as String? ?? '',
        expiryDate: j['expiry_date'] as String?,
        daysRemaining: (j['days_remaining'] as num?)?.toInt(),
        customerEmail: j['customer_email'] as String?,
        orderNumber: j['order_number'] as String?,
        startDate: j['start_date'] as String?,
      );
}

class ContactMessageRow {
  const ContactMessageRow({
    required this.id,
    required this.name,
    required this.email,
    this.subject,
    required this.message,
    this.isRead = false,
    this.createdAgo,
  });

  final int id;
  final String name;
  final String email;
  final String? subject;
  final String message;
  final bool isRead;
  final String? createdAgo;

  factory ContactMessageRow.fromJson(Map<String, dynamic> j) => ContactMessageRow(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        email: j['email'] as String? ?? '',
        subject: j['subject'] as String?,
        message: j['message'] as String? ?? '',
        isRead: j['is_read'] == true,
        createdAgo: j['created_ago'] as String?,
      );
}

class AdminPaymentMethod {
  const AdminPaymentMethod({
    required this.id,
    required this.code,
    required this.name,
    this.description,
    this.accountNumber,
    this.instructions,
    required this.enabled,
    this.sortOrder,
    this.hasQr = false,
    this.qrUrl,
  });

  final int id;
  final String code;
  final String name;
  final String? description;
  final String? accountNumber;
  final String? instructions;
  final bool enabled;
  final int? sortOrder;
  final bool hasQr;
  final String? qrUrl;

  factory AdminPaymentMethod.fromJson(Map<String, dynamic> j) =>
      AdminPaymentMethod(
        id: (j['id'] as num).toInt(),
        code: j['code'] as String? ?? '',
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        accountNumber: j['account_number'] as String?,
        instructions: j['instructions'] as String?,
        enabled: j['enabled'] == true,
        sortOrder: (j['sort_order'] as num?)?.toInt(),
        hasQr: j['has_qr'] == true,
        qrUrl: j['qr_url'] as String?,
      );
}
