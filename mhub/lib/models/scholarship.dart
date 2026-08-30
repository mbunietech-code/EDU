class Scholarship {
  const Scholarship({
    required this.id,
    required this.title,
    required this.slug,
    this.country,
    this.isFeatured = false,
    this.imageUrl,
    this.shortDescription,
    this.description,
    this.deadlineLabel,
    this.isExpired = false,
    this.applyUrl,
  });

  final int id;
  final String title;
  final String slug;
  final String? country;
  final bool isFeatured;
  final String? imageUrl;
  final String? shortDescription;
  final String? description;
  final String? deadlineLabel;
  final bool isExpired;
  final String? applyUrl;

  factory Scholarship.fromJson(Map<String, dynamic> j) => Scholarship(
        id: (j['id'] as num).toInt(),
        title: j['title'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        country: j['country'] as String?,
        isFeatured: j['is_featured'] == true,
        imageUrl: j['image_url'] as String?,
        shortDescription: j['short_description'] as String?,
        description: j['description'] as String?,
        deadlineLabel: j['deadline_label'] as String?,
        isExpired: j['is_expired'] == true,
        applyUrl: j['apply_url'] as String?,
      );
}
