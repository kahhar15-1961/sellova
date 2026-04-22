import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class ProductDto {
  const ProductDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten product fields once payload schema is fully frozen.

  int? get id => (raw['id'] as num?)?.toInt();

  String get title => (raw['title'] ?? raw['name'] ?? 'Untitled product').toString();

  String get description => (raw['description'] ?? raw['short_description'] ?? '').toString();

  String get shortInfo => (raw['short_info'] ?? raw['subtitle'] ?? description).toString();

  String get priceLabel {
    final currency = (raw['currency'] ?? '').toString().toUpperCase();
    final amount = raw['base_price'] ?? raw['price'] ?? raw['amount'];
    if (amount == null) {
      return currency.isEmpty ? 'Price unavailable' : currency;
    }
    return currency.isEmpty ? amount.toString() : '$currency $amount';
  }

  String? get primaryImageUrl {
    final direct = raw['image_url'] ?? raw['thumbnail_url'] ?? raw['cover_image_url'];
    if (direct is String && direct.isNotEmpty) {
      return direct;
    }
    final images = imageUrls;
    return images.isEmpty ? null : images.first;
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
    final fallback = primaryImageUrl;
    return fallback == null ? <String>[] : <String>[fallback];
  }

  String get sellerLabel {
    final seller =
        raw['seller_name'] ?? raw['store_name'] ?? raw['seller'] ?? raw['seller_profile_name'];
    if (seller == null || seller.toString().isEmpty) {
      return 'Seller unavailable';
    }
    return seller.toString();
  }
}

class ProductRepository {
  ProductRepository(this._apiClient);

  final ApiClient _apiClient;

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
      items: result.items.map(ProductDto.new).toList(),
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
      items: result.items.map(ProductDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<ProductDto> getById(int productId) async {
    final json = await _apiClient.get('/api/v1/products/$productId');
    return ProductDto(parseObjectEnvelope(json).data);
  }
}
