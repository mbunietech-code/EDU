// Models for the admin side of MHub (list + detail payloads from /api/admin/*).

class AdminOrder {
  const AdminOrder({
    required this.id,
    required this.orderNumber,
    required this.title,
    required this.customerName,
    this.plan,
    required this.amountLabel,
    required this.status,
    this.createdAgo,
    this.rejectionReason,
    this.customerEmail,
    this.confirmedAt,
    this.payments = const [],
  });

  final int id;
  final String orderNumber;
  final String title;
  final String customerName;
  final String? plan;
  final String amountLabel;
  final String status;
  final String? createdAgo;
  final String? rejectionReason;
  final String? customerEmail;
  final String? confirmedAt;
  final List<AdminOrderPayment> payments;

  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';

  factory AdminOrder.fromJson(Map<String, dynamic> j) => AdminOrder(
        id: (j['id'] as num).toInt(),
        orderNumber: j['order_number'] as String? ?? '',
        title: j['title'] as String? ?? 'Order',
        customerName: j['customer_name'] as String? ?? '—',
        plan: j['plan'] as String?,
        amountLabel: j['amount_label'] as String? ?? '',
        status: j['status'] as String? ?? 'pending',
        createdAgo: j['created_ago'] as String?,
        rejectionReason: j['rejection_reason'] as String?,
        customerEmail: (j['customer'] as Map<String, dynamic>?)?['email'] as String?,
        confirmedAt: j['confirmed_at'] as String?,
        payments: (j['payments'] as List?)
                ?.map((e) => AdminOrderPayment.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
      );
}

class AdminOrderPayment {
  const AdminOrderPayment({
    required this.id,
    required this.amountLabel,
    required this.method,
    required this.status,
    this.reference,
    this.createdAgo,
  });

  final int id;
  final String amountLabel;
  final String method;
  final String status;
  final String? reference;
  final String? createdAgo;

  factory AdminOrderPayment.fromJson(Map<String, dynamic> j) => AdminOrderPayment(
        id: (j['id'] as num).toInt(),
        amountLabel: j['amount_label'] as String? ?? '',
        method: j['method'] as String? ?? '',
        status: j['status'] as String? ?? '',
        reference: j['reference'] as String?,
        createdAgo: j['created_ago'] as String?,
      );
}

class AdminPayment {
  const AdminPayment({
    required this.id,
    this.orderId,
    required this.title,
    required this.customerName,
    required this.amountLabel,
    required this.method,
    required this.status,
    this.createdAgo,
    this.reference,
    this.adminNote,
    this.customerEmail,
    this.orderNumber,
    this.orderStatus,
    this.proofs = const [],
  });

  final int id;
  final int? orderId;
  final String title;
  final String customerName;
  final String amountLabel;
  final String method;
  final String status;
  final String? createdAgo;
  final String? reference;
  final String? adminNote;
  final String? customerEmail;
  final String? orderNumber;
  final String? orderStatus;
  final List<AdminProof> proofs;

  bool get isPending => status == 'pending';

  factory AdminPayment.fromJson(Map<String, dynamic> j) => AdminPayment(
        id: (j['id'] as num).toInt(),
        orderId: (j['order_id'] as num?)?.toInt(),
        title: j['title'] as String? ?? 'Payment',
        customerName: j['customer_name'] as String? ?? '—',
        amountLabel: j['amount_label'] as String? ?? '',
        method: j['method'] as String? ?? '',
        status: j['status'] as String? ?? 'pending',
        createdAgo: j['created_ago'] as String?,
        reference: j['reference'] as String?,
        adminNote: j['admin_note'] as String?,
        customerEmail: (j['customer'] as Map<String, dynamic>?)?['email'] as String?,
        orderNumber: (j['order'] as Map<String, dynamic>?)?['order_number'] as String?,
        orderStatus: (j['order'] as Map<String, dynamic>?)?['status'] as String?,
        proofs: (j['proofs'] as List?)
                ?.map((e) => AdminProof.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
      );
}

class AdminProof {
  const AdminProof({required this.id, required this.path, this.caption});
  final int id;
  final String path;
  final String? caption;

  factory AdminProof.fromJson(Map<String, dynamic> j) => AdminProof(
        id: (j['id'] as num).toInt(),
        path: j['path'] as String? ?? '',
        caption: j['caption'] as String?,
      );
}

class AdminUser {
  const AdminUser({
    required this.id,
    required this.name,
    required this.email,
    required this.status,
    required this.isAdmin,
    this.ordersCount = 0,
    this.activeSubscriptionsCount = 0,
    this.role,
    this.createdAt,
    this.subscriptions = const [],
    this.orders = const [],
  });

  final int id;
  final String name;
  final String email;
  final String status;
  final bool isAdmin;
  final int ordersCount;
  final int activeSubscriptionsCount;
  final String? role;
  final String? createdAt;
  final List<AdminUserSubscription> subscriptions;
  final List<AdminOrder> orders;

  bool get isSuspended => status == 'suspended';

  factory AdminUser.fromJson(Map<String, dynamic> j) => AdminUser(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        email: j['email'] as String? ?? '',
        status: j['status'] as String? ?? 'active',
        isAdmin: j['is_admin'] == true,
        ordersCount: (j['orders_count'] as num?)?.toInt() ?? 0,
        activeSubscriptionsCount:
            (j['active_subscriptions_count'] as num?)?.toInt() ?? 0,
        role: j['role'] as String?,
        createdAt: j['created_at'] as String?,
        subscriptions: (j['subscriptions'] as List?)
                ?.map((e) => AdminUserSubscription.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
        orders: (j['orders'] as List?)
                ?.map((e) => AdminOrder.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
      );
}

class AdminUserSubscription {
  const AdminUserSubscription({required this.product, required this.status, this.endsAt});
  final String product;
  final String status;
  final String? endsAt;

  factory AdminUserSubscription.fromJson(Map<String, dynamic> j) =>
      AdminUserSubscription(
        product: j['product'] as String? ?? '—',
        status: j['status'] as String? ?? '',
        endsAt: j['ends_at'] as String?,
      );
}

class AdminStat {
  const AdminStat(this.label, this.value);
  final String label;
  final String value;

  factory AdminStat.fromJson(Map<String, dynamic> j) =>
      AdminStat(j['label'] as String? ?? '', '${j['value'] ?? ''}');
}

class AdminRevenuePoint {
  const AdminRevenuePoint(this.label, this.value, this.valueLabel);
  final String label;
  final double value;
  final String valueLabel;

  factory AdminRevenuePoint.fromJson(Map<String, dynamic> j) => AdminRevenuePoint(
        j['label'] as String? ?? '',
        (j['value'] as num?)?.toDouble() ?? 0,
        j['value_label'] as String? ?? '',
      );
}

class AdminOrdersPoint {
  const AdminOrdersPoint(this.label, this.total, this.byStatus);
  final String label;
  final int total;
  final Map<String, int> byStatus;

  factory AdminOrdersPoint.fromJson(Map<String, dynamic> j) => AdminOrdersPoint(
        j['label'] as String? ?? '',
        (j['total'] as num?)?.toInt() ?? 0,
        ((j['by_status'] as Map?) ?? {})
            .map((k, v) => MapEntry(k.toString(), (v as num).toInt())),
      );
}

class AdminProductStat {
  const AdminProductStat(this.name, this.orders, this.subscriptions);
  final String name;
  final int orders;
  final int subscriptions;

  factory AdminProductStat.fromJson(Map<String, dynamic> j) => AdminProductStat(
        j['name'] as String? ?? '—',
        (j['orders'] as num?)?.toInt() ?? 0,
        (j['subscriptions'] as num?)?.toInt() ?? 0,
      );
}

class AdminReports {
  const AdminReports({
    required this.metrics,
    required this.revenue,
    required this.orders,
    required this.products,
    required this.subscriptions,
    required this.accounts,
  });

  final List<AdminStat> metrics;
  final List<AdminRevenuePoint> revenue;
  final List<AdminOrdersPoint> orders;
  final List<AdminProductStat> products;
  final Map<String, int> subscriptions;
  final Map<String, int> accounts;

  factory AdminReports.fromJson(Map<String, dynamic> j) => AdminReports(
        metrics: (j['metrics'] as List? ?? [])
            .map((e) => AdminStat.fromJson(e as Map<String, dynamic>))
            .toList(),
        revenue: (j['revenue'] as List? ?? [])
            .map((e) => AdminRevenuePoint.fromJson(e as Map<String, dynamic>))
            .toList(),
        orders: (j['orders'] as List? ?? [])
            .map((e) => AdminOrdersPoint.fromJson(e as Map<String, dynamic>))
            .toList(),
        products: (j['products'] as List? ?? [])
            .map((e) => AdminProductStat.fromJson(e as Map<String, dynamic>))
            .toList(),
        subscriptions: ((j['subscriptions'] as Map?) ?? {})
            .map((k, v) => MapEntry(k.toString(), (v as num).toInt())),
        accounts: ((j['accounts'] as Map?) ?? {})
            .map((k, v) => MapEntry(k.toString(), (v as num).toInt())),
      );
}

class AdminConversation {
  const AdminConversation({
    required this.id,
    required this.userName,
    required this.userEmail,
    required this.unread,
    this.lastMessage,
    this.lastFromAdmin = false,
    this.updatedAgo,
  });

  final int id;
  final String userName;
  final String userEmail;
  final int unread;
  final String? lastMessage;
  final bool lastFromAdmin;
  final String? updatedAgo;

  factory AdminConversation.fromJson(Map<String, dynamic> j) => AdminConversation(
        id: (j['id'] as num).toInt(),
        userName: j['user_name'] as String? ?? '—',
        userEmail: j['user_email'] as String? ?? '',
        unread: (j['unread'] as num?)?.toInt() ?? 0,
        lastMessage: j['last_message'] as String?,
        lastFromAdmin: j['last_from_admin'] == true,
        updatedAgo: j['updated_ago'] as String?,
      );
}
