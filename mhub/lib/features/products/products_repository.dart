import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/product.dart';

class ProductsRepository {
  ProductsRepository(this._api);

  final ApiClient _api;

  Future<List<Product>> list() async {
    final data = await _api.get('/products');
    final items = (data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Product.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Product> detail(String slug) async {
    final data = await _api.get('/products/$slug');
    return Product.fromJson((data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }
}

final productsRepositoryProvider = Provider<ProductsRepository>((ref) {
  return ProductsRepository(ref.watch(apiClientProvider));
});

final productsProvider = FutureProvider.autoDispose<List<Product>>((ref) {
  return ref.watch(productsRepositoryProvider).list();
});

final productDetailProvider =
    FutureProvider.autoDispose.family<Product, String>((ref, slug) {
  return ref.watch(productsRepositoryProvider).detail(slug);
});
