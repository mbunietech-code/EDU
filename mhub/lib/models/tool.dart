class Tool {
  const Tool({
    required this.id,
    required this.name,
    required this.slug,
    this.version,
    this.isFeatured = false,
    this.imageUrl,
    this.shortDescription,
    this.description,
    this.price = 0,
    required this.priceLabel,
  });

  final int id;
  final String name;
  final String slug;
  final String? version;
  final bool isFeatured;
  final String? imageUrl;
  final String? shortDescription;
  final String? description;
  final double price;
  final String priceLabel;

  factory Tool.fromJson(Map<String, dynamic> j) => Tool(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        slug: j['slug'] as String? ?? '',
        version: j['version'] as String?,
        isFeatured: j['is_featured'] == true,
        imageUrl: j['image_url'] as String?,
        shortDescription: j['short_description'] as String?,
        description: j['description'] as String?,
        price: (j['price'] as num?)?.toDouble() ?? 0,
        priceLabel: j['price_label'] as String? ?? '',
      );
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.fromAdmin,
    required this.body,
    this.time,
  });

  final int id;
  final bool fromAdmin;
  final String? body;
  final String? time;

  factory ChatMessage.fromJson(Map<String, dynamic> j) => ChatMessage(
        id: (j['id'] as num).toInt(),
        fromAdmin: j['from_admin'] == true,
        body: j['body'] as String?,
        time: j['time'] as String?,
      );
}
