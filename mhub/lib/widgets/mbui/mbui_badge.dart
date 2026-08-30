import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

enum MbuiAppearance { success, warning, danger, info, neutral }

/// `<x-mbui.badge>` — rounded-md, px-2 py-1, text-xs font-medium, tinted bg + ring.
class MbuiBadge extends StatelessWidget {
  const MbuiBadge(this.label, {super.key, this.appearance = MbuiAppearance.neutral});

  final String label;
  final MbuiAppearance appearance;

  @override
  Widget build(BuildContext context) {
    final (bg, fg, ring) = switch (appearance) {
      MbuiAppearance.success => (AppColors.emerald50, AppColors.emerald700, AppColors.emerald600),
      MbuiAppearance.warning => (AppColors.amber50, AppColors.amber700, const Color(0xFFD97706)),
      MbuiAppearance.danger => (AppColors.red50, AppColors.red700, AppColors.red600),
      MbuiAppearance.info => (AppColors.sky50, AppColors.sky700, const Color(0xFF0284C7)),
      MbuiAppearance.neutral => (AppColors.gray100, AppColors.gray700, AppColors.gray500),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(color: ring.withValues(alpha: 0.2)),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w500, height: 1.2),
      ),
    );
  }
}

/// `<x-mbui.status-badge>` — maps a status string to an appearance + Title Case.
class MbuiStatusBadge extends StatelessWidget {
  const MbuiStatusBadge(this.status, {super.key});

  final String status;

  static const _map = {
    'active': MbuiAppearance.success,
    'available': MbuiAppearance.success,
    'approved': MbuiAppearance.success,
    'paid': MbuiAppearance.success,
    'confirmed': MbuiAppearance.success,
    'published': MbuiAppearance.success,
    'pending': MbuiAppearance.warning,
    'expiring_soon': MbuiAppearance.warning,
    'suspended': MbuiAppearance.warning,
    'inactive': MbuiAppearance.warning,
    'draft': MbuiAppearance.warning,
    'maintenance': MbuiAppearance.warning,
    'expired': MbuiAppearance.danger,
    'rejected': MbuiAppearance.danger,
    'revoked': MbuiAppearance.danger,
    'cancelled': MbuiAppearance.danger,
    'archived': MbuiAppearance.danger,
    'assigned': MbuiAppearance.info,
  };

  @override
  Widget build(BuildContext context) {
    final appearance = _map[status.toLowerCase()] ?? MbuiAppearance.neutral;
    final label = status
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}')
        .join(' ');
    return MbuiBadge(label, appearance: appearance);
  }
}
