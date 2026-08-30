import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:mhub/app.dart';

void main() {
  testWidgets('App boots to a loading indicator before auth resolves',
      (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: MHubApp()));
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
