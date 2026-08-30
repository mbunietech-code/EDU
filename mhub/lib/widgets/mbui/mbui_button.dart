import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

enum MbuiVariant { primary, secondary, danger, success, ghost }

/// `<x-mbui.button>` — rounded-lg, px-4 py-2, text-sm font-semibold.
class MbuiButton extends StatelessWidget {
  const MbuiButton({
    super.key,
    required this.label,
    this.onPressed,
    this.variant = MbuiVariant.primary,
    this.icon,
    this.loading = false,
    this.fullWidth = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final MbuiVariant variant;
  final IconData? icon;
  final bool loading;
  final bool fullWidth;

  @override
  Widget build(BuildContext context) {
    final (bg, fg, border) = switch (variant) {
      MbuiVariant.primary => (AppColors.indigo600, Colors.white, null),
      MbuiVariant.secondary => (Colors.white, AppColors.gray900, AppColors.gray300),
      MbuiVariant.danger => (AppColors.red600, Colors.white, null),
      MbuiVariant.success => (AppColors.emerald600, Colors.white, null),
      MbuiVariant.ghost => (Colors.transparent, AppColors.gray700, null),
    };

    final child = loading
        ? SizedBox(
            height: 18,
            width: 18,
            child: CircularProgressIndicator(strokeWidth: 2, color: fg),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[Icon(icon, size: 16), const SizedBox(width: 8)],
              Text(label),
            ],
          );

    final style = ButtonStyle(
      backgroundColor: WidgetStatePropertyAll(bg),
      foregroundColor: WidgetStatePropertyAll(fg),
      overlayColor: WidgetStatePropertyAll(fg.withValues(alpha: 0.08)),
      elevation: const WidgetStatePropertyAll(0),
      minimumSize: WidgetStatePropertyAll(Size(fullWidth ? double.infinity : 0, 44)),
      padding: const WidgetStatePropertyAll(
        EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      ),
      textStyle: const WidgetStatePropertyAll(
        TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
      ),
      shape: WidgetStatePropertyAll(
        RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: border != null ? BorderSide(color: border) : BorderSide.none,
        ),
      ),
    );

    return TextButton(
      onPressed: loading ? null : onPressed,
      style: style,
      child: child,
    );
  }
}
