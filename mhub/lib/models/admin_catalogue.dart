// Models for admin catalogue management (/api/admin/catalogue/*).

class CatProduct {
  const CatProduct({
    required this.id,
    required this.name,
    required this.slug,
    required this.type,
    required this.priceLabel,
    required this.status,
    this.plansCount = 0,
    this.ordersCount = 0,
    this.isFeatured = false,
  });

  final int id;
  final String name;
  final String slug;
  final String type;
  final String priceLabel;
  final String status;
  final int plansCount;
  final int ordersCount;
  final bool isFeatured;

  factory CatProduct.fromJson(Map<String, dynamic> j) => CatProduct(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        type: j['type'] as String? ?? 'subscription',
        priceLabel: j['price_label'] as String? ?? '',
        status: j['status'] as String? ?? 'draft',
        plansCount: (j['plans_count'] as num?)?.toInt() ?? 0,
        ordersCount: (j['orders_count'] as num?)?.toInt() ?? 0,
        isFeatured: j['is_featured'] == true,
      );
}

class CatProductDetail {
  const CatProductDetail({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.features = const [],
    required this.price,
    required this.type,
    required this.status,
    this.isFeatured = false,
    this.softwareVersion,
    this.softwareKey,
    this.imageUrl,
    this.plans = const [],
  });

  final int id;
  final String name;
  final String slug;
  final String? description;
  final List<String> features;
  final double price;
  final String type;
  final String status;
  final bool isFeatured;
  final String? softwareVersion;
  final String? softwareKey;
  final String? imageUrl;
  final List<CatPlan> plans;

  factory CatProductDetail.fromJson(Map<String, dynamic> j) => CatProductDetail(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        description: j['description'] as String?,
        features: (j['features'] as List?)?.map((e) => e.toString()).toList() ??
            const [],
        price: (j['price'] as num?)?.toDouble() ?? 0,
        type: j['type'] as String? ?? 'subscription',
        status: j['status'] as String? ?? 'draft',
        isFeatured: j['is_featured'] == true,
        softwareVersion: j['software_version'] as String?,
        softwareKey: j['software_key'] as String?,
        imageUrl: j['image_url'] as String?,
        plans: (j['plans'] as List?)
                ?.map((e) => CatPlan.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [],
      );
}

class CatPlan {
  const CatPlan({
    required this.id,
    required this.name,
    this.description,
    required this.durationType,
    this.durationDays,
    required this.durationLabel,
    required this.price,
    required this.priceLabel,
    required this.status,
    this.sortOrder,
  });

  final int id;
  final String name;
  final String? description;
  final String durationType;
  final int? durationDays;
  final String durationLabel;
  final double price;
  final String priceLabel;
  final String status;
  final int? sortOrder;

  factory CatPlan.fromJson(Map<String, dynamic> j) => CatPlan(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        durationType: j['duration_type'] as String? ?? 'days',
        durationDays: (j['duration_days'] as num?)?.toInt(),
        durationLabel: j['duration_label'] as String? ?? '',
        price: (j['price'] as num?)?.toDouble() ?? 0,
        priceLabel: j['price_label'] as String? ?? '',
        status: j['status'] as String? ?? 'active',
        sortOrder: (j['sort_order'] as num?)?.toInt(),
      );
}

class CatTool {
  const CatTool({
    required this.id,
    required this.name,
    required this.slug,
    required this.priceLabel,
    required this.status,
    this.ordersCount = 0,
    this.version,
    this.isFeatured = false,
  });

  final int id;
  final String name;
  final String slug;
  final String priceLabel;
  final String status;
  final int ordersCount;
  final String? version;
  final bool isFeatured;

  factory CatTool.fromJson(Map<String, dynamic> j) => CatTool(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        priceLabel: j['price_label'] as String? ?? '',
        status: j['status'] as String? ?? 'draft',
        ordersCount: (j['orders_count'] as num?)?.toInt() ?? 0,
        version: j['version'] as String?,
        isFeatured: j['is_featured'] == true,
      );
}

class CatToolDetail {
  const CatToolDetail({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.version,
    this.licenseKey,
    required this.price,
    required this.status,
    this.isFeatured = false,
    this.sortOrder,
    this.imageUrl,
  });

  final int id;
  final String name;
  final String slug;
  final String? description;
  final String? version;
  final String? licenseKey;
  final double price;
  final String status;
  final bool isFeatured;
  final int? sortOrder;
  final String? imageUrl;

  factory CatToolDetail.fromJson(Map<String, dynamic> j) => CatToolDetail(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        description: j['description'] as String?,
        version: j['version'] as String?,
        licenseKey: j['license_key'] as String?,
        price: (j['price'] as num?)?.toDouble() ?? 0,
        status: j['status'] as String? ?? 'draft',
        isFeatured: j['is_featured'] == true,
        sortOrder: (j['sort_order'] as num?)?.toInt(),
        imageUrl: j['image_url'] as String?,
      );
}

class CatScholarship {
  const CatScholarship({
    required this.id,
    required this.title,
    required this.slug,
    this.country,
    required this.status,
    this.deadline,
    this.isExpired = false,
    this.isFeatured = false,
  });

  final int id;
  final String title;
  final String slug;
  final String? country;
  final String status;
  final String? deadline;
  final bool isExpired;
  final bool isFeatured;

  factory CatScholarship.fromJson(Map<String, dynamic> j) => CatScholarship(
        id: (j['id'] as num).toInt(),
        title: j['title'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        country: j['country'] as String?,
        status: j['status'] as String? ?? 'draft',
        deadline: j['deadline'] as String?,
        isExpired: j['is_expired'] == true,
        isFeatured: j['is_featured'] == true,
      );
}

class CatScholarshipDetail {
  const CatScholarshipDetail({
    required this.id,
    required this.title,
    required this.slug,
    this.country,
    this.description,
    this.deadline,
    this.applyUrl,
    required this.status,
    this.isFeatured = false,
    this.sortOrder,
    this.imageUrl,
  });

  final int id;
  final String title;
  final String slug;
  final String? country;
  final String? description;
  final String? deadline;
  final String? applyUrl;
  final String status;
  final bool isFeatured;
  final int? sortOrder;
  final String? imageUrl;

  factory CatScholarshipDetail.fromJson(Map<String, dynamic> j) =>
      CatScholarshipDetail(
        id: (j['id'] as num).toInt(),
        title: j['title'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        country: j['country'] as String?,
        description: j['description'] as String?,
        deadline: j['deadline'] as String?,
        applyUrl: j['apply_url'] as String?,
        status: j['status'] as String? ?? 'draft',
        isFeatured: j['is_featured'] == true,
        sortOrder: (j['sort_order'] as num?)?.toInt(),
        imageUrl: j['image_url'] as String?,
      );
}
