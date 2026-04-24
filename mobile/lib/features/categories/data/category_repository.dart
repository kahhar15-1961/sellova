import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class CategoryDto {
  const CategoryDto(this.raw);
  final Map<String, dynamic> raw;

  int? get id => (raw['id'] as num?)?.toInt();
  String get name => (raw['name'] ?? 'Unknown category').toString();
  String get slug => (raw['slug'] ?? '').toString();
  int get productsCount => (raw['products_count'] as num?)?.toInt() ?? 0;
}

class CategoryRepository {
  CategoryRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<CategoryDto>> list() async {
    final json = await _apiClient.get('/api/v1/categories');
    final envelope = parseListEnvelope(json);
    return envelope.data.map(CategoryDto.new).toList();
  }
}

