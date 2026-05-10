import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class ProductDto {
  const ProductDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten product fields once payload schema is fully frozen.

  int? get id => (raw['id'] as num?)?.toInt();

  String get title =>
      (raw['title'] ?? raw['name'] ?? 'Untitled product').toString();

  String get description =>
      (raw['description'] ?? raw['short_description'] ?? '').toString();

  String get shortInfo =>
      (raw['short_info'] ?? raw['subtitle'] ?? description).toString();

  String get priceLabel {
    final currency = (raw['currency'] ?? '').toString().toUpperCase();
    final amount = raw['base_price'] ?? raw['price'] ?? raw['amount'];
    if (amount == null) {
      return currency.isEmpty ? 'Price unavailable' : currency;
    }
    return currency.isEmpty ? amount.toString() : '$currency $amount';
  }

  String? get primaryImageUrl {
    final direct =
        raw['image_url'] ?? raw['thumbnail_url'] ?? raw['cover_image_url'];
    if (direct is String && direct.isNotEmpty) {
      return direct;
    }
    final rawImages = raw['images'];
    if (rawImages is List) {
      for (final item in rawImages) {
        if (item is String && item.isNotEmpty) {
          return item;
        }
        if (item is Map && item['url'] is String) {
          final url = item['url'].toString();
          if (url.isNotEmpty) {
            return url;
          }
        }
      }
    }
    return null;
  }

  List<String> get imageUrls {
    final values = <String>[];
    final rawImages = raw['images'];
    if (rawImages is List) {
      for (final item in rawImages) {
        if (item is String && item.isNotEmpty) {
          values.add(item);
        } else if (item is Map && item['url'] is String) {
          final url = item['url'].toString();
          if (url.isNotEmpty) {
            values.add(url);
          }
        }
      }
    }
    if (values.isNotEmpty) {
      return values;
    }
    final direct =
        raw['image_url'] ?? raw['thumbnail_url'] ?? raw['cover_image_url'];
    final fallback = direct is String && direct.isNotEmpty ? direct : null;
    return fallback == null ? <String>[] : <String>[fallback];
  }

  String get sellerLabel {
    final seller = raw['seller_name'] ??
        raw['store_name'] ??
        raw['seller'] ??
        raw['seller_profile_name'];
    if (seller == null || seller.toString().isEmpty) {
      return 'Seller unavailable';
    }
    return seller.toString();
  }

  Map<String, dynamic>? get sellerSummary {
    final rawSeller = raw['seller_summary'];
    if (rawSeller is Map) {
      return Map<String, dynamic>.from(rawSeller);
    }
    return null;
  }

  Map<String, dynamic>? get reviewSummary {
    final rawReview = raw['review_summary'];
    if (rawReview is Map) {
      return Map<String, dynamic>.from(rawReview);
    }
    return null;
  }

  Map<String, dynamic> get attributes {
    final value = raw['attributes'];
    return value is Map
        ? Map<String, dynamic>.from(value)
        : const <String, dynamic>{};
  }

  double? get rating {
    final summary = reviewSummary;
    final value = raw['rating'] ??
        raw['rating_avg'] ??
        raw['average_rating'] ??
        summary?['average_rating'];
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  int get reviewCount {
    final summary = reviewSummary;
    final value =
        raw['review_count'] ?? raw['reviews_count'] ?? summary?['review_count'];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  /// Catalog detail payload (`ProductService::productToDetailArray`).
  String get status => (raw['status'] ?? '').toString().trim();

  String get productType => (raw['product_type'] ?? '').toString().trim();

  bool get isInstantDelivery {
    final explicit = raw['is_instant_delivery'];
    if (explicit is bool) {
      return explicit;
    }
    if (explicit is num) {
      return explicit != 0;
    }
    final explicitText = explicit?.toString().trim().toLowerCase();
    if (explicitText == 'true' ||
        explicitText == '1' ||
        explicitText == 'yes') {
      return true;
    }
    final normalized = productType.toLowerCase().replaceAll('-', '_');
    if (normalized == 'instant_delivery' || normalized == 'instant') {
      return true;
    }
    final deliveryType = attributes['delivery_type']?.toString().toLowerCase();
    final fulfillment =
        attributes['fulfillment']?.toString().toLowerCase() ?? '';
    return deliveryType == 'instant_delivery' ||
        deliveryType == 'instant' ||
        fulfillment.contains('instant');
  }

  List<ProductFeature> get homeFeatures {
    final features = <ProductFeature>[];
    if (isInstantDelivery) {
      features.add(
        const ProductFeature(
          label: 'Instant Delivery',
          icon: 'bolt',
          backgroundColor: 0xFFD1F4EF,
          foregroundColor: 0xFF006B97,
        ),
      );
    }
    features.addAll(const <ProductFeature>[
      ProductFeature(
        label: 'Escrow Protected',
        icon: 'shield',
        backgroundColor: 0xFFFFF4DF,
        foregroundColor: 0xFF9A5A00,
      ),
      ProductFeature(
        label: 'Secure Checkout',
        icon: 'lock',
        backgroundColor: 0xFFECEBFF,
        foregroundColor: 0xFF4F46A5,
      ),
    ]);
    return features;
  }

  int? get categoryId => (raw['category_id'] as num?)?.toInt();

  int? get storefrontId => (raw['storefront_id'] as num?)?.toInt();

  int? get sellerProfileId => (raw['seller_profile_id'] as num?)?.toInt();

  String get uuid => (raw['uuid'] ?? '').toString().trim();

  String get publishedLabel {
    final rawValue = raw['published_at'] ?? raw['publishedAt'];
    if (rawValue is String && rawValue.isNotEmpty) {
      final d = DateTime.tryParse(rawValue);
      if (d != null) {
        return _formatYmdAtHm(d.toLocal());
      }
    }
    return '—';
  }

  String get updatedLabel {
    final rawValue = raw['updated_at'] ?? raw['updatedAt'];
    if (rawValue is String && rawValue.isNotEmpty) {
      final d = DateTime.tryParse(rawValue);
      if (d != null) {
        return _formatYmdAtHm(d.toLocal());
      }
    }
    return '—';
  }

  /// Shown under the title when the API does not send `short_info` / `subtitle`.
  String get heroHighlight {
    final explicit = raw['short_info'] ?? raw['subtitle'];
    if (explicit != null && explicit.toString().trim().isNotEmpty) {
      return explicit.toString().trim();
    }
    final d = description.trim();
    if (d.isEmpty) {
      return '';
    }
    final nl = d.indexOf('\n');
    if (nl > 0 && nl <= 200) {
      return d.substring(0, nl).trim();
    }
    if (d.length <= 160) {
      return d;
    }
    return '${d.substring(0, 157)}…';
  }

  /// Optional compare/list price when present in the payload (same [currency] as base price).
  String? get compareAtLabel {
    final keys = <String>[
      'compare_at_price',
      'list_price',
      'original_price',
      'msrp'
    ];
    for (final k in keys) {
      final v = raw[k];
      if (v == null) {
        continue;
      }
      final s = v.toString().trim();
      if (s.isEmpty) {
        continue;
      }
      final c = (raw['currency'] ?? '').toString().toUpperCase();
      return c.isEmpty ? s : '$c $s';
    }
    return null;
  }

  bool get hasMeaningfulComparePrice {
    final compare = compareAtLabel;
    if (compare == null) {
      return false;
    }
    return compare.trim() != priceLabel.trim();
  }
}

class ProductFeature {
  const ProductFeature({
    required this.label,
    required this.icon,
    required this.backgroundColor,
    required this.foregroundColor,
  });

  final String label;
  final String icon;
  final int backgroundColor;
  final int foregroundColor;
}

String _formatYmdAtHm(DateTime local) {
  final y = local.year.toString().padLeft(4, '0');
  final m = local.month.toString().padLeft(2, '0');
  final d = local.day.toString().padLeft(2, '0');
  final h = local.hour.toString().padLeft(2, '0');
  final min = local.minute.toString().padLeft(2, '0');
  return '$y-$m-$d at $h:$min';
}

class ProductRepository {
  ProductRepository(this._apiClient, {required String baseUrl})
      : _baseUrl = baseUrl;

  final ApiClient _apiClient;
  final String _baseUrl;

  Future<PaginatedResult<ProductDto>> list({
    int page = 1,
    int perPage = 10,
    int? categoryId,
    int? storefrontId,
  }) async {
    final json = await _apiClient.get(
      '/api/v1/products',
      queryParameters: <String, dynamic>{
        'page': page,
        'per_page': perPage,
        if (categoryId != null) 'category_id': categoryId,
        if (storefrontId != null) 'storefront_id': storefrontId,
      },
    );
    final result = parsePaginatedObjectList(json);
    return PaginatedResult<ProductDto>(
      items:
          result.items.map(_normalizeProductRaw).map(ProductDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<PaginatedResult<ProductDto>> search({
    required String query,
    int page = 1,
    int perPage = 10,
    int? categoryId,
    int? storefrontId,
  }) async {
    final json = await _apiClient.get(
      '/api/v1/products/search',
      queryParameters: <String, dynamic>{
        'search': query,
        'page': page,
        'per_page': perPage,
        if (categoryId != null) 'category_id': categoryId,
        if (storefrontId != null) 'storefront_id': storefrontId,
      },
    );
    final result = parsePaginatedObjectList(json);
    return PaginatedResult<ProductDto>(
      items:
          result.items.map(_normalizeProductRaw).map(ProductDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<ProductDto> getById(int productId) async {
    final json = await _apiClient.get('/api/v1/products/$productId');
    return ProductDto(_normalizeProductRaw(parseObjectEnvelope(json).data));
  }

  ProductDto normalizeProductDto(ProductDto product) {
    return ProductDto(_normalizeProductRaw(product.raw));
  }

  Map<String, dynamic> _normalizeProductRaw(Map<String, dynamic> raw) {
    final next = Map<String, dynamic>.from(raw);
    final normalizedImages = _normalizeImages(next['images']);

    for (final key in <String>[
      'image_url',
      'thumbnail_url',
      'cover_image_url'
    ]) {
      final normalized = _mediaUrl(next[key]?.toString());
      if (normalized != null) {
        next[key] = normalized;
      }
    }

    if (normalizedImages.isNotEmpty) {
      next['images'] = normalizedImages;
      next['image_url'] ??= normalizedImages.first;
      next['thumbnail_url'] ??= normalizedImages.first;
    }

    return next;
  }

  List<dynamic> _normalizeImages(Object? rawImages) {
    if (rawImages is! List) {
      return const <dynamic>[];
    }

    return rawImages.map((item) {
      if (item is String) {
        return _mediaUrl(item);
      }
      if (item is Map) {
        final next = Map<String, dynamic>.from(item);
        final normalized = _mediaUrl(next['url']?.toString());
        if (normalized != null) {
          next['url'] = normalized;
        }
        return next;
      }
      return item;
    }).where((item) {
      if (item == null) {
        return false;
      }
      if (item is String) {
        return item.trim().isNotEmpty;
      }
      return true;
    }).toList();
  }

  String? _mediaUrl(String? raw) {
    final value = (raw ?? '').trim();
    if (value.isEmpty) {
      return null;
    }
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }
    if (value.startsWith('/api/v1/media/')) {
      return '$_baseUrl$value';
    }
    if (value.startsWith('seller-uploads/')) {
      return '$_baseUrl/api/v1/media/$value';
    }
    return value.startsWith('/') ? '$_baseUrl$value' : value;
  }
}
