import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class PromoOfferDto {
  const PromoOfferDto(this.raw);

  final Map<String, dynamic> raw;

  String get code => (raw['code'] ?? '').toString();
  String get title => (raw['title'] ?? code).toString();
  String get description => (raw['description'] ?? '').toString();
  String get badge => (raw['badge'] ?? '').toString();
  String get minSpendLabel => (raw['min_spend_label'] ?? raw['min_spend'] ?? '').toString();
  bool get eligible => raw['eligible'] == true;
  String? get estimatedDiscountAmount => raw['estimated_discount_amount']?.toString();
  String? get estimatedTotalAmount => raw['estimated_total_amount']?.toString();
}

class PromoValidationDto {
  const PromoValidationDto(this.raw);

  final Map<String, dynamic> raw;

  String get code => ((raw['promo'] as Map?)?['code'] ?? raw['code'] ?? '').toString();
  String get title => ((raw['promo'] as Map?)?['title'] ?? raw['title'] ?? code).toString();
  String get description => ((raw['promo'] as Map?)?['description'] ?? raw['description'] ?? '').toString();
  String get badge => ((raw['promo'] as Map?)?['badge'] ?? raw['badge'] ?? '').toString();
  String get minSpendLabel => ((raw['promo'] as Map?)?['min_spend_label'] ?? raw['min_spend_label'] ?? '').toString();
  bool get eligible => ((raw['promo'] as Map?)?['eligible'] ?? raw['eligible']) == true;
  double get discountAmount {
    final value = (raw['promo'] as Map?)?['estimated_discount_amount'] ?? raw['discount_amount'];
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }
}

class PromoRepository {
  PromoRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<PromoOfferDto>> listOffers({
    double? subtotal,
    String currency = 'USD',
    String shippingMethod = 'standard',
  }) async {
    final queryParameters = <String, dynamic>{
      if (subtotal != null) 'subtotal': subtotal.toStringAsFixed(4),
      'currency': currency,
      'shipping_method': shippingMethod,
    };
    final json = await _apiClient.get(
      '/api/v1/promo-codes',
      queryParameters: queryParameters,
    );
    final data = parseObjectEnvelope(json).data;
    final items = (data['items'] as List?) ?? const <dynamic>[];
    return items.whereType<Map>().map((e) => PromoOfferDto(Map<String, dynamic>.from(e))).toList();
  }

  Future<PromoValidationDto> validate({
    required String code,
    required double subtotal,
    required double shippingFee,
    required String currency,
    required String shippingMethod,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/promo-codes/validate',
      data: <String, dynamic>{
        'code': code,
        'subtotal': subtotal.toStringAsFixed(4),
        'shipping_fee': shippingFee.toStringAsFixed(4),
        'currency': currency,
        'shipping_method': shippingMethod,
      },
    );
    final data = parseObjectEnvelope(json).data;
    return PromoValidationDto(Map<String, dynamic>.from(data));
  }
}
