class Subscription {
  const Subscription({
    required this.id,
    required this.product,
    this.plan,
    required this.status,
    this.startDate,
    this.expiryDate,
    required this.expiresIn,
  });

  final int id;
  final String product;
  final String? plan;
  final String status;
  final String? startDate;
  final String? expiryDate;
  final String expiresIn;

  factory Subscription.fromJson(Map<String, dynamic> j) => Subscription(
        id: (j['id'] as num).toInt(),
        product: j['product'] as String? ?? '—',
        plan: j['plan'] as String?,
        status: j['status'] as String? ?? 'active',
        startDate: j['start_date'] as String?,
        expiryDate: j['expiry_date'] as String?,
        expiresIn: j['expires_in'] as String? ?? '',
      );
}
