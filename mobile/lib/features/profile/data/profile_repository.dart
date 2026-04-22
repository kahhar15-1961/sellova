import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class ActorProfileDto {
  const ActorProfileDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten this DTO once backend field set is frozen.
}

class SellerProfileDto {
  const SellerProfileDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten this DTO once backend field set is frozen.
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
}
