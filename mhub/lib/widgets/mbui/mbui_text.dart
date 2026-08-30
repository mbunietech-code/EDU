import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

/// `.mbui-title` — text-2xl font-bold tracking-tight gray-900.
class MbuiTitle extends StatelessWidget {
  const MbuiTitle(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text,
        style: const TextStyle(
          fontSize: 24,
          fontWeight: FontWeight.bold,
          letterSpacing: -0.4,
          color: AppColors.gray900,
        ),
      );
}

/// `.mbui-section-label` — text-xs font-semibold uppercase tracking-wider gray-500.
class MbuiSectionLabel extends StatelessWidget {
  const MbuiSectionLabel(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text.toUpperCase(),
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.6,
          color: AppColors.gray500,
        ),
      );
}

/// `.mbui-anchor` — font-medium indigo-600.
class MbuiAnchor extends StatelessWidget {
  const MbuiAnchor(this.text, {super.key, required this.onTap});
  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: AppColors.indigo600,
          ),
        ),
      );
}
