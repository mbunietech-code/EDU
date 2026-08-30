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
      );
}
