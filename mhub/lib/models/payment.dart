class Payment {
  const Payment({
    required this.id,
    required this.orderId,
    required this.title,
    required this.amountLabel,
    required this.method,
    required this.status,
    this.createdAgo,
    this.transactionReference,
    this.adminNote,
  });

  final int id;
  final int? orderId;
  final String title;
  final String amountLabel;
  final String method;
  final String status;
  final String? createdAgo;
  final String? transactionReference;
  final String? adminNote;

  factory Payment.fromJson(Map<String, dynamic> j) => Payment(
        id: (j['id'] as num).toInt(),
        orderId: (j['order_id'] as num?)?.toInt(),
        title: j['title'] as String? ?? 'Payment',
        amountLabel: j['amount_label'] as String? ?? '',
        method: j['method'] as String? ?? '',
        status: j['status'] as String? ?? 'pending',
        createdAgo: j['created_ago'] as String?,
        transactionReference: j['transaction_reference'] as String?,
        adminNote: j['admin_note'] as String?,
      );
}

class PaymentMethod {
  const PaymentMethod({
    required this.code,
    required this.name,
    this.description,
    this.accountNumber,
    this.instructions,
    this.qrImageUrl,
  });

  final String code;
  final String name;
  final String? description;
  final String? accountNumber;
  final String? instructions;
  final String? qrImageUrl;

  factory PaymentMethod.fromJson(Map<String, dynamic> j) => PaymentMethod(
        code: j['code'] as String? ?? '',
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        accountNumber: j['account_number'] as String?,
        instructions: j['instructions'] as String?,
        qrImageUrl: j['qr_image_url'] as String?,
      );
}
