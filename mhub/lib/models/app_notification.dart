class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.message,
    required this.read,
    this.createdAgo,
    this.orderId,
    this.paymentId,
  });

  final String id;
  final String type;
  final String message;
  final bool read;
  final String? createdAgo;
  final int? orderId;
  final int? paymentId;

  factory AppNotification.fromJson(Map<String, dynamic> j) => AppNotification(
        id: j['id'].toString(),
        type: j['type'] as String? ?? 'notification',
        message: j['message'] as String? ?? '',
        read: j['read'] == true,
        createdAgo: j['created_ago'] as String?,
        orderId: (j['order_id'] as num?)?.toInt(),
        paymentId: (j['payment_id'] as num?)?.toInt(),
      );
}
