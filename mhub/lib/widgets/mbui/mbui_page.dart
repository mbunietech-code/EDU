import 'package:flutter/material.dart';

import '../../theme/tokens.dart';

/// A standard page: gray-50 background, an app bar matching the site header,
/// and a centred max-width body (`.mbui-container`) so it also reads well on
/// wide desktop windows.
class MbuiPage extends StatelessWidget {
  const MbuiPage({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.onRefresh,
    this.padding = const EdgeInsets.all(16),
    this.maxContentWidth = 760,
    this.floatingActionButton,
  });

  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Future<void> Function()? onRefresh;
  final EdgeInsets padding;
  final double maxContentWidth;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    Widget content = Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxContentWidth),
        child: Padding(padding: padding, child: body),
      ),
    );

    if (onRefresh != null) {
      content = RefreshIndicator(onRefresh: onRefresh!, child: content);
    }

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(title), actions: actions),
      floatingActionButton: floatingActionButton,
      body: content,
    );
  }
}

/// `.mbui-page-header` — title row with optional trailing action.
class MbuiPageHeader extends StatelessWidget {
  const MbuiPageHeader({super.key, required this.title, this.subtitle, this.trailing});

  final String title;
  final String? subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    letterSpacing: -0.3,
                    color: AppColors.gray900,
                  )),
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(subtitle!,
                    style: const TextStyle(fontSize: 13, color: AppColors.gray500)),
              ],
            ],
          ),
        ),
        ?trailing,
      ],
    );
  }
}
