class ProductPlan {
  const ProductPlan({
    required this.id,
    required this.name,
    this.description,
    required this.duration,
    required this.price,
    required this.priceLabel,
  });

  final int id;
  final String name;
  final String? description;
  final String duration;
  final double price;
  final String priceLabel;

  factory ProductPlan.fromJson(Map<String, dynamic> j) => ProductPlan(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        duration: j['duration'] as String? ?? '',
        price: (j['price'] as num?)?.toDouble() ?? 0,
        priceLabel: j['price_label'] as String? ?? '',
      );
}

class Product {
  const Product({
    required this.id,
    required this.name,
    required this.slug,
    required this.type,
    this.imageUrl,
    this.isFeatured = false,
    this.shortDescription,
    this.description,
    this.plansCount = 0,
    this.fromPriceLabel,
    this.features = const [],
    this.plans = const [],
  });

  final int id;
  final String name;
  final String slug;
  final String type;
  final String? imageUrl;
  final bool isFeatured;
  final String? shortDescription;
  final String? description;
  final int plansCount;
  final String? fromPriceLabel;
  final List<String> features;
  final List<ProductPlan> plans;

  factory Product.fromJson(Map<String, dynamic> j) => Product(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        type: j['type'] as String? ?? 'service',
        imageUrl: j['image_url'] as String?,
        isFeatured: j['is_featured'] == true,
        shortDescription: j['short_description'] as String?,
        description: j['description'] as String?,
        plansCount: (j['plans_count'] as num?)?.toInt() ?? 0,
        fromPriceLabel: j['from_price_label'] as String?,
        features: (j['features'] as List?)?.map((e) => e.toString()).toList() ?? const [],
        plans: (j['plans'] as List?)
                ?.map((e) => ProductPlan.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
      );
}
