import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class ProductDto {
  const ProductDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten product fields once payload schema is fully frozen.
}

class ProductRepository {
  ProductRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResult<ProductDto>> list({
    int page = 1,
    int perPage = 10,
  }) async {
    final json = await _apiClient.get(
      '/api/v1/products',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
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
  }) async {
    final json = await _apiClient.get(
      '/api/v1/products/search',
      queryParameters: <String, dynamic>{
        'search': query,
        'page': page,
        'per_page': perPage,
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
