import '../../../core/auth/token_store.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class AuthSessionDto {
  const AuthSessionDto(this.raw);

  final Map<String, dynamic> raw;

  String get accessToken => (raw['access_token'] ?? '').toString();
  String get refreshToken => (raw['refresh_token'] ?? '').toString();

  int? get userId {
    final v = raw['user_id'];
    if (v is int) {
      return v;
    }
    if (v is num) {
      return v.toInt();
    }
    return int.tryParse(v?.toString() ?? '');
  }

  List<String> get roleCodes {
    final r = raw['role_codes'];
    if (r is List) {
      return r.map((e) => e.toString()).toList();
    }
    return const <String>[];
  }

  bool get isPlatformStaff => roleCodes.contains('admin') || roleCodes.contains('adjudicator');
}

class AuthRepository {
  AuthRepository({
    required ApiClient apiClient,
    required TokenStore tokenStore,
  })  : _apiClient = apiClient,
        _tokenStore = tokenStore;

  final ApiClient _apiClient;
  final TokenStore _tokenStore;

  Future<AuthSessionDto> register(Map<String, dynamic> request) async {
    final json = await _apiClient.post('/api/v1/auth/register', data: request);
    final envelope = parseObjectEnvelope(json);
    final dto = AuthSessionDto(envelope.data);
    await _persistTokens(dto);
    return dto;
  }

  Future<AuthSessionDto> login(Map<String, dynamic> request) async {
    final json = await _apiClient.post('/api/v1/auth/login', data: request);
    final envelope = parseObjectEnvelope(json);
    final dto = AuthSessionDto(envelope.data);
    await _persistTokens(dto);
    return dto;
  }

  Future<AuthSessionDto> loginWithGoogle({required String idToken}) async {
    final json = await _apiClient.post(
      '/api/v1/auth/google',
      data: <String, dynamic>{'id_token': idToken},
    );
    final envelope = parseObjectEnvelope(json);
    final dto = AuthSessionDto(envelope.data);
    await _persistTokens(dto);
    return dto;
  }

  Future<AuthSessionDto> loginWithApple({
    required String identityToken,
    String? email,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/auth/apple',
      data: <String, dynamic>{
        'identity_token': identityToken,
        if (email != null && email.trim().isNotEmpty) 'email': email.trim(),
      },
    );
    final envelope = parseObjectEnvelope(json);
    final dto = AuthSessionDto(envelope.data);
    await _persistTokens(dto);
    return dto;
  }

  Future<AuthSessionDto> refresh(String refreshToken) async {
    final json = await _apiClient.post(
      '/api/v1/auth/refresh',
      data: <String, dynamic>{'refresh_token': refreshToken},
    );
    final envelope = parseObjectEnvelope(json);
    final dto = AuthSessionDto(envelope.data);
    await _persistTokens(dto);
    return dto;
  }

  Future<Map<String, dynamic>> logout() async {
    final json = await _apiClient.post('/api/v1/auth/logout');
    await _tokenStore.clear();
    return parseObjectEnvelope(json).data;
  }

  Future<void> _persistTokens(AuthSessionDto dto) async {
    if (dto.accessToken.isEmpty || dto.refreshToken.isEmpty) {
      return;
    }
    await _tokenStore.writeTokens(
      accessToken: dto.accessToken,
      refreshToken: dto.refreshToken,
    );
  }
}
