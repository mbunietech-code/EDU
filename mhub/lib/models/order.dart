class OrderPayment {
  const OrderPayment({required this.id, required this.amountLabel, required this.status});

  final int id;
  final String amountLabel;
  final String status;

  factory OrderPayment.fromJson(Map<String, dynamic> j) => OrderPayment(
        id: (j['id'] as num).toInt(),
        amountLabel: j['amount_label'] as String? ?? '',
        status: j['status'] as String? ?? '',
      );
}

/// Product key + download for software / tool orders (API "delivery").
class OrderDelivery {
  const OrderDelivery({required this.type, required this.state, this.key, this.fileName, this.downloadUrl, this.expiresAt});

  /// software | tool
  final String type;

  /// waiting (not paid yet) | open | expired (software window closed)
  final String state;
  final String? key;
  final String? fileName;

  /// Short-lived signed link; fetch the order again for a fresh one.
  final String? downloadUrl;
  final DateTime? expiresAt;

  static OrderDelivery? maybe(dynamic j) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return OrderDelivery(
      type: m['type'] as String? ?? 'software',
      state: m['state'] as String? ?? 'waiting',
      key: m['key'] as String?,
      fileName: m['file_name'] as String?,
      downloadUrl: m['download_url'] as String?,
      expiresAt: m['expires_at'] == null ? null : DateTime.tryParse(m['expires_at'] as String)?.toLocal(),
    );
  }
}

class Order {
  const Order({
    required this.id,
    required this.orderNumber,
    required this.title,
    this.plan,
    required this.amountLabel,
    required this.status,
    this.canCancel = false,
    this.createdAgo,
    this.paymentInstructions,
    this.rejectionReason,
    this.payments = const [],
    this.delivery,
  });

  final int id;
  final String orderNumber;
  final String title;
  final String? plan;
  final String amountLabel;
  final String status;
  final bool canCancel;
  final String? createdAgo;
  final String? paymentInstructions;
  final String? rejectionReason;
  final List<OrderPayment> payments;
  final OrderDelivery? delivery;

  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';
  bool get isRejected => status == 'rejected';

  factory Order.fromJson(Map<String, dynamic> j) => Order(
        id: (j['id'] as num).toInt(),
        orderNumber: j['order_number'] as String? ?? '',
        title: j['title'] as String? ?? 'Order',
        plan: j['plan'] as String?,
        amountLabel: j['amount_label'] as String? ?? '',
        status: j['status'] as String? ?? 'pending',
        canCancel: j['can_cancel'] == true,
        createdAgo: j['created_ago'] as String?,
        paymentInstructions: j['payment_instructions'] as String?,
        rejectionReason: j['rejection_reason'] as String?,
        payments: (j['payments'] as List?)
                ?.map((e) => OrderPayment.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
        delivery: OrderDelivery.maybe(j['delivery']),
      );
}
