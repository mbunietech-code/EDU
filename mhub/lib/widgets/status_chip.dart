import 'package:flutter/material.dart';

class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});

  final String status;

  @override
  Widget build(BuildContext context) {
    final s = status.toLowerCase();
    final (bg, fg) = switch (s) {
      'confirmed' || 'active' || 'approved' || 'paid' => (
          const Color(0xFFDCFCE7),
          const Color(0xFF166534),
        ),
      'pending' || 'expiring_soon' || 'awaiting_payment' => (
          const Color(0xFFFEF9C3),
          const Color(0xFF854D0E),
        ),
      'rejected' || 'expired' || 'cancelled' || 'suspended' => (
          const Color(0xFFFEE2E2),
          const Color(0xFF991B1B),
        ),
      _ => (const Color(0xFFE5E7EB), const Color(0xFF374151)),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(
        status.replaceAll('_', ' '),
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}
