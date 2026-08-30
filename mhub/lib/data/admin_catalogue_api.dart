import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/admin_catalogue.dart';

List<T> _list<T>(dynamic data, T Function(Map<String, dynamic>) fromJson) =>
    ((data as Map<String, dynamic>)['data'] as List)
        .map((e) => fromJson(e as Map<String, dynamic>))
        .toList();

Map<String, dynamic> _one(dynamic data) =>
    (data as Map<String, dynamic>)['data'] as Map<String, dynamic>;

class AdminCatalogueRepository {
  AdminCatalogueRepository(this._api);
  final ApiClient _api;

  // Products
  Future<List<CatProduct>> products() async =>
      _list(await _api.get('/admin/catalogue/products'), CatProduct.fromJson);

  Future<CatProductDetail> product(int id) async => CatProductDetail.fromJson(
      _one(await _api.get('/admin/catalogue/products/$id')));

  Future<int> createProduct(Map<String, dynamic> body) async =>
      (_one(await _api.post('/admin/catalogue/products', data: body))['id']
              as num)
          .toInt();

  Future<void> updateProduct(int id, Map<String, dynamic> body) =>
      _api.put('/admin/catalogue/products/$id', data: body);

  Future<void> deleteProduct(int id, String reason) => _api
      .delete('/admin/catalogue/products/$id', data: {'reason': reason});

  Future<void> uploadImage(String kind, int id, String filePath) async {
    final form = FormData.fromMap({
      'image': await MultipartFile.fromFile(filePath),
    });
    await _api.post('/admin/catalogue/$kind/$id/image', data: form);
  }

  // Plans
  Future<void> createPlan(int productId, Map<String, dynamic> body) =>
      _api.post('/admin/catalogue/products/$productId/plans', data: body);

  Future<void> updatePlan(int planId, Map<String, dynamic> body) =>
      _api.put('/admin/catalogue/plans/$planId', data: body);

  Future<void> deletePlan(int planId, String reason) =>
      _api.delete('/admin/catalogue/plans/$planId', data: {'reason': reason});

  // Tools
  Future<List<CatTool>> tools() async =>
      _list(await _api.get('/admin/catalogue/tools'), CatTool.fromJson);

  Future<CatToolDetail> tool(int id) async =>
      CatToolDetail.fromJson(_one(await _api.get('/admin/catalogue/tools/$id')));

  Future<int> createTool(Map<String, dynamic> body) async =>
      (_one(await _api.post('/admin/catalogue/tools', data: body))['id'] as num)
          .toInt();

  Future<void> updateTool(int id, Map<String, dynamic> body) =>
      _api.put('/admin/catalogue/tools/$id', data: body);

  Future<void> deleteTool(int id, String reason) =>
      _api.delete('/admin/catalogue/tools/$id', data: {'reason': reason});

  // Scholarships
  Future<List<CatScholarship>> scholarships() async => _list(
      await _api.get('/admin/catalogue/scholarships'), CatScholarship.fromJson);

  Future<CatScholarshipDetail> scholarship(int id) async =>
      CatScholarshipDetail.fromJson(
          _one(await _api.get('/admin/catalogue/scholarships/$id')));

  Future<int> createScholarship(Map<String, dynamic> body) async =>
      (_one(await _api.post('/admin/catalogue/scholarships', data: body))['id']
              as num)
          .toInt();

  Future<void> updateScholarship(int id, Map<String, dynamic> body) =>
      _api.put('/admin/catalogue/scholarships/$id', data: body);

  Future<void> deleteScholarship(int id, String reason) => _api
      .delete('/admin/catalogue/scholarships/$id', data: {'reason': reason});
}

final adminCatalogueRepositoryProvider = Provider(
    (ref) => AdminCatalogueRepository(ref.watch(apiClientProvider)));

final adminCatProductsProvider = FutureProvider.autoDispose<List<CatProduct>>(
    (ref) => ref.watch(adminCatalogueRepositoryProvider).products());
final adminCatProductProvider =
    FutureProvider.autoDispose.family<CatProductDetail, int>(
        (ref, id) => ref.watch(adminCatalogueRepositoryProvider).product(id));

final adminCatToolsProvider = FutureProvider.autoDispose<List<CatTool>>(
    (ref) => ref.watch(adminCatalogueRepositoryProvider).tools());
final adminCatToolProvider =
    FutureProvider.autoDispose.family<CatToolDetail, int>(
        (ref, id) => ref.watch(adminCatalogueRepositoryProvider).tool(id));

final adminCatScholarshipsProvider =
    FutureProvider.autoDispose<List<CatScholarship>>(
        (ref) => ref.watch(adminCatalogueRepositoryProvider).scholarships());
final adminCatScholarshipProvider =
    FutureProvider.autoDispose.family<CatScholarshipDetail, int>((ref, id) =>
        ref.watch(adminCatalogueRepositoryProvider).scholarship(id));
