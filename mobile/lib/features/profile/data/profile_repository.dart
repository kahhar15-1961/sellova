import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class ActorProfileDto {
  const ActorProfileDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten this DTO once backend field set is frozen.

  String get displayName =>
      (raw['display_name'] ?? raw['name'] ?? raw['full_name'] ?? 'Unnamed user')
          .toString();

  int? get id {
    final v = raw['id'];
    if (v is int) {
      return v;
    }
    if (v is num) {
      return v.toInt();
    }
    return int.tryParse(v?.toString() ?? '');
  }

  String get email => (raw['email'] ?? '').toString();

  String get phone => (raw['phone'] ?? '').toString();

  String get country =>
      (raw['country_code'] ?? raw['country'] ?? '').toString().toUpperCase();

  String get currency => (raw['default_currency'] ?? raw['currency'] ?? '')
      .toString()
      .toUpperCase();

  List<String> get roleCodes {
    final r = raw['role_codes'];
    if (r is List) {
      return r.map((e) => e.toString()).toList();
    }
    return const <String>[];
  }

  bool get isPlatformStaff =>
      roleCodes.contains('admin') || roleCodes.contains('adjudicator');
}

class SellerProfileDto {
  const SellerProfileDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten this DTO once backend field set is frozen.

  int? get id {
    final v = raw['id'] ?? raw['seller_profile_id'];
    if (v is int) {
      return v;
    }
    if (v is num) {
      return v.toInt();
    }
    return int.tryParse(v?.toString() ?? '');
  }

  String get displayName => (raw['display_name'] ??
          raw['store_name'] ??
          raw['name'] ??
          'Unnamed seller')
      .toString();

  String get legalName => (raw['legal_name'] ?? '').toString();

  String get country =>
      (raw['country_code'] ?? raw['country'] ?? '').toString().toUpperCase();

  String get currency => (raw['default_currency'] ?? raw['currency'] ?? '')
      .toString()
      .toUpperCase();

  String get verificationStatus =>
      (raw['verification_status'] ?? 'unknown').toString();

  String get storeStatus => (raw['store_status'] ?? 'unknown').toString();

  String get latestKycStatus =>
      (raw['latest_kyc_status'] ?? raw['kyc_status'] ?? 'none').toString();

  DateTime? get latestKycSubmittedAt {
    final rawValue = raw['latest_kyc_submitted_at'] ?? raw['kyc_submitted_at'];
    if (rawValue is String && rawValue.isNotEmpty) {
      return DateTime.tryParse(rawValue);
    }
    return null;
  }

  List<Map<String, dynamic>> get latestKycDocuments {
    final rawDocs = raw['latest_kyc'];
    if (rawDocs is Map) {
      final docs = rawDocs['documents'];
      if (docs is List) {
        return docs
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      }
    }
    return const <Map<String, dynamic>>[];
  }
}

class ProfileRepository {
  ProfileRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<ActorProfileDto> getMe() async {
    final json = await _apiClient.get('/api/v1/me');
    return ActorProfileDto(parseObjectEnvelope(json).data);
  }

  Future<ActorProfileDto> updateMe(Map<String, dynamic> request) async {
    final json = await _apiClient.patch('/api/v1/me', data: request);
    return ActorProfileDto(parseObjectEnvelope(json).data);
  }

  Future<SellerProfileDto> getMeSeller() async {
    final json = await _apiClient.get('/api/v1/me/seller');
    return SellerProfileDto(parseObjectEnvelope(json).data);
  }

  Future<SellerProfileDto> updateMeSeller(Map<String, dynamic> request) async {
    final json = await _apiClient.patch('/api/v1/me/seller', data: request);
    return SellerProfileDto(parseObjectEnvelope(json).data);
  }

  Future<SellerProfileDto> createMeSeller(Map<String, dynamic> request) async {
    final json = await _apiClient.post('/api/v1/me/seller', data: request);
    return SellerProfileDto(parseObjectEnvelope(json).data);
  }

  Future<SellerProfileDto> submitSellerKyc(Map<String, dynamic> request) async {
    final json = await _apiClient.post('/api/v1/me/seller/kyc', data: request);
    return SellerProfileDto(parseObjectEnvelope(json).data);
  }
}
