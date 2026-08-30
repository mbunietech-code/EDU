import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

/// Temporary screen for features whose native version is not built yet.
class PlaceholderScreen extends StatelessWidget {
  const PlaceholderScreen({super.key, required this.title, this.icon});

  final String title;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon ?? Icons.construction_outlined,
                size: 44, color: AppColors.gray400),
            const SizedBox(height: 12),
            Text('$title is coming soon',
                style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.gray700)),
            const SizedBox(height: 4),
            const Text('This screen is being built to match the website.',
                style: TextStyle(fontSize: 13, color: AppColors.gray500)),
          ],
        ),
      ),
    );
  }
}
