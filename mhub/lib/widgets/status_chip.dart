import 'package:flutter/material.dart';

import 'mbui/mbui.dart';

/// Kept for compatibility — delegates to the site-matching status badge.
class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});

  final String status;

  @override
  Widget build(BuildContext context) => MbuiStatusBadge(status);
}
