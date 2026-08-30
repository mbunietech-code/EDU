import 'package:flutter/material.dart';

import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';

/// Prompt the admin for a required/optional reason string.
Future<String?> promptReason(
  BuildContext context, {
  required String title,
  required String actionLabel,
  String hint = 'Reason',
  bool required = true,
  MbuiVariant variant = MbuiVariant.danger,
}) {
  final controller = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (ctx) {
      return AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          minLines: 2,
          maxLines: 5,
          decoration: InputDecoration(hintText: hint),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: variant == MbuiVariant.danger
                  ? AppColors.red600
                  : AppColors.indigo600,
            ),
            onPressed: () {
              final text = controller.text.trim();
              if (required && text.isEmpty) return;
              Navigator.pop(ctx, required ? text : (text.isEmpty ? '' : text));
            },
            child: Text(actionLabel),
          ),
        ],
      );
    },
  );
}

/// Key/value row used across admin detail screens.
class AdminRow extends StatelessWidget {
  const AdminRow(this.label, this.value, {super.key, this.valueWidget});
  final String label;
  final String value;
  final Widget? valueWidget;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label,
                style: const TextStyle(fontSize: 13, color: AppColors.gray500)),
          ),
          Expanded(
            child: valueWidget ??
                Text(value,
                    style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: AppColors.gray900)),
          ),
        ],
      ),
    );
  }
}

/// Horizontal chip strip for filtering admin queues by status.
class AdminFilterBar extends StatelessWidget {
  const AdminFilterBar({
    super.key,
    required this.options,
    required this.selected,
    required this.onSelected,
  });

  /// `null` key = "All".
  final Map<String?, String> options;
  final String? selected;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        children: [
          for (final entry in options.entries)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text(entry.value),
                selected: selected == entry.key,
                onSelected: (_) => onSelected(entry.key),
              ),
            ),
        ],
      ),
    );
  }
}
