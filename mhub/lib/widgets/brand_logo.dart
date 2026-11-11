import 'package:flutter/material.dart';

/// The MHub logo (the app icon), with rounded corners like the website's
/// brand mark. Used in the sidebar and on the sign-in / register pages.
class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.size = 32, this.radius});

  final double size;

  /// Corner radius; defaults to a quarter of the size.
  final double? radius;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius ?? size / 4),
      child: Image.asset(
        'assets/icon/mhub_icon_square.png',
        width: size,
        height: size,
        fit: BoxFit.cover,
        filterQuality: FilterQuality.medium,
      ),
    );
  }
}
