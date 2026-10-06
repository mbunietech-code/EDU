/// What an order can be paid with (GET /orders/{id}/payment-options).
class PaymentOptions {
  const PaymentOptions({
    required this.orderId,
    required this.amountLabel,
    required this.mobile,
    required this.manual,
    this.card,
    this.payPal,
    this.active,
  });

  final int orderId;
  final String amountLabel;
  final List<MobileGatewayOption> mobile;
  final ChargeOffer? card;
  final ChargeOffer? payPal;
  final bool manual;

  /// A push still waiting for the customer's PIN, if any.
  final GatewayPayment? active;

  factory PaymentOptions.fromJson(Map<String, dynamic> j) => PaymentOptions(
        orderId: (j['order_id'] as num).toInt(),
        amountLabel: j['amount_label'] as String? ?? '',
        mobile: ((j['mobile'] as List?) ?? const [])
            .map((e) => MobileGatewayOption.fromJson(e as Map<String, dynamic>))
            .toList(),
        card: j['card'] == null ? null : ChargeOffer.fromJson(j['card'] as Map<String, dynamic>),
        payPal: j['paypal'] == null ? null : ChargeOffer.fromJson(j['paypal'] as Map<String, dynamic>),
        manual: j['manual'] as bool? ?? false,
        active: j['active'] == null ? null : GatewayPayment.fromJson(j['active'] as Map<String, dynamic>),
      );
}

class MobileGatewayOption {
  const MobileGatewayOption({required this.key, required this.label, required this.networks});

  final String key;
  final String label;

  /// Empty when the provider detects the network from the number.
  final List<NetworkOption> networks;

  factory MobileGatewayOption.fromJson(Map<String, dynamic> j) => MobileGatewayOption(
        key: j['key'] as String,
        label: j['label'] as String? ?? '',
        networks: ((j['networks'] as List?) ?? const [])
            .map((e) => NetworkOption.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class NetworkOption {
  const NetworkOption({required this.value, required this.label});

  final String value;
  final String label;

  factory NetworkOption.fromJson(Map<String, dynamic> j) =>
      NetworkOption(value: j['value'] as String, label: j['label'] as String? ?? '');
}

/// Card and PayPal charge in another currency (USD) at today's rate.
class ChargeOffer {
  const ChargeOffer({required this.amount, required this.currency, required this.label});

  final double amount;
  final String currency;
  final String label;

  String get display => '$currency ${amount.toStringAsFixed(2)}';

  factory ChargeOffer.fromJson(Map<String, dynamic> j) => ChargeOffer(
        amount: (j['amount'] as num).toDouble(),
        currency: j['currency'] as String? ?? 'USD',
        label: j['label'] as String? ?? '',
      );
}

/// One automatic payment attempt and its live status.
class GatewayPayment {
  const GatewayPayment({
    required this.id,
    required this.orderId,
    required this.gateway,
    required this.label,
    required this.usesRedirect,
    required this.status,
    required this.amountLabel,
    required this.reference,
    required this.timeoutMinutes,
    this.message,
    this.phone,
    this.chargedLabel,
    this.redirectUrl,
  });

  final int id;
  final int orderId;
  final String gateway;
  final String label;
  final bool usesRedirect;

  /// pending | success | failed | expired
  final String status;
  final String amountLabel;
  final String reference;
  final int timeoutMinutes;
  final String? message;
  final String? phone;
  final String? chargedLabel;
  final String? redirectUrl;

  bool get isPending => status == 'pending';
  bool get isSuccessful => status == 'success';
  bool get isFailed => status == 'failed';
  bool get isExpired => status == 'expired';

  factory GatewayPayment.fromJson(Map<String, dynamic> j) => GatewayPayment(
        id: (j['id'] as num).toInt(),
        orderId: (j['order_id'] as num).toInt(),
        gateway: j['gateway'] as String? ?? '',
        label: j['label'] as String? ?? '',
        usesRedirect: j['uses_redirect'] as bool? ?? false,
        status: j['status'] as String? ?? 'pending',
        amountLabel: j['amount_label'] as String? ?? '',
        reference: j['reference'] as String? ?? '',
        timeoutMinutes: (j['timeout_minutes'] as num?)?.toInt() ?? 10,
        message: j['message'] as String?,
        phone: j['phone'] as String?,
        chargedLabel: j['charged_label'] as String?,
        redirectUrl: j['redirect_url'] as String?,
      );
}
