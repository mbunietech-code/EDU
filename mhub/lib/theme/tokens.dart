import 'package:flutter/material.dart';

/// The MbunieEduHub web palette (Tailwind defaults + the `mbui-*` component
/// classes from resources/css/app.css). The app is light-only, like the site.
class AppColors {
  const AppColors._();

  // Tailwind gray
  static const gray50 = Color(0xFFF9FAFB);
  static const gray100 = Color(0xFFF3F4F6);
  static const gray200 = Color(0xFFE5E7EB);
  static const gray300 = Color(0xFFD1D5DB);
  static const gray400 = Color(0xFF9CA3AF);
  static const gray500 = Color(0xFF6B7280);
  static const gray600 = Color(0xFF4B5563);
  static const gray700 = Color(0xFF374151);
  static const gray800 = Color(0xFF1F2937);
  static const gray900 = Color(0xFF111827);

  // Tailwind indigo (brand)
  static const indigo50 = Color(0xFFEEF2FF);
  static const indigo100 = Color(0xFFE0E7FF);
  static const indigo500 = Color(0xFF6366F1);
  static const indigo600 = Color(0xFF4F46E5);
  static const indigo700 = Color(0xFF4338CA);
  static const indigo800 = Color(0xFF3730A3);

  // Semantic (badges / status)
  static const emerald50 = Color(0xFFECFDF5);
  static const emerald600 = Color(0xFF059669);
  static const emerald700 = Color(0xFF047857);
  static const amber50 = Color(0xFFFFFBEB);
  static const amber700 = Color(0xFFB45309);
  static const red50 = Color(0xFFFEF2F2);
  static const red600 = Color(0xFFDC2626);
  static const red700 = Color(0xFFB91C1C);
  static const sky50 = Color(0xFFF0F9FF);
  static const sky700 = Color(0xFF0369A1);

  static const pageBackground = gray50;
  static const cardBackground = Colors.white;
  static const cardBorder = gray200;
  static const primary = indigo600;
}

class AppRadius {
  const AppRadius._();
  static const sm = 6.0; // rounded-md
  static const md = 8.0; // rounded-lg
  static const lg = 12.0; // rounded-xl
  static const full = 999.0;
}

class AppShadows {
  const AppShadows._();

  /// Tailwind shadow-sm
  static const card = [
    BoxShadow(color: Color(0x0D000000), blurRadius: 2, offset: Offset(0, 1)),
  ];
}
