import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class WalletLedgerEntryDto {
  const WalletLedgerEntryDto(this.raw);

  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  String get entryType => (raw['entry_type'] ?? '').toString();
  String get entrySide => (raw['entry_side'] ?? '').toString();
  String get amount => (raw['amount'] ?? '0').toString();
  String get currency => (raw['currency'] ?? '').toString().toUpperCase();
  String get description => (raw['description'] ?? '').toString();
  String get createdAt => (raw['created_at'] ?? '').toString();
}

class WalletTopUpRequestDto {
  const WalletTopUpRequestDto(this.raw);

  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  String get status => (raw['status'] ?? '').toString();
  String get requestedAmount => (raw['requested_amount'] ?? '0').toString();
  String get paymentMethod => (raw['payment_method'] ?? '').toString();
  String get paymentReference => (raw['payment_reference'] ?? '').toString();
  String get currency => (raw['currency'] ?? '').toString().toUpperCase();
  String? get reviewedAt => raw['reviewed_at']?.toString();
  String? get rejectionReason => raw['rejection_reason']?.toString();
  String? get createdAt => raw['created_at']?.toString();
}

class WalletDto {
  const WalletDto(this.raw);

  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  String get walletType => (raw['wallet_type'] ?? '').toString();
  String get currency => (raw['currency'] ?? '').toString().toUpperCase();
  String get status => (raw['status'] ?? '').toString();
  String get availableBalance => (raw['available_balance'] ?? '0').toString();
  String get heldBalance => (raw['held_balance'] ?? '0').toString();
  String get totalBalance => (raw['total_balance'] ?? '0').toString();
  bool get topUpAllowed => raw['top_up_allowed'] == true;

  List<WalletTopUpRequestDto> get recentTopUpRequests {
    final rows = raw['recent_top_up_requests'];
    if (rows is List) {
      return rows
          .whereType<Map>()
          .map((e) => WalletTopUpRequestDto(Map<String, dynamic>.from(e)))
          .toList();
    }
    return const <WalletTopUpRequestDto>[];
  }

  List<WalletLedgerEntryDto> get recentEntries {
    final rows = raw['recent_entries'];
    if (rows is List) {
      return rows
          .whereType<Map>()
          .map((e) => WalletLedgerEntryDto(Map<String, dynamic>.from(e)))
          .toList();
    }
    return const <WalletLedgerEntryDto>[];
  }

  String get typeLabel {
    return switch (walletType) {
      'buyer' => 'Buyer Wallet',
      'seller' => 'Seller Wallet',
      _ => 'Wallet',
    };
  }
}

class WalletRepository {
  WalletRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<WalletDto>> listWallets() async {
    final json = await _apiClient.get('/api/v1/me/wallets');
    final data = parseObjectEnvelope(json).data;
    final items = (data['wallets'] as List?) ?? const <dynamic>[];
    return items
        .whereType<Map>()
        .map((e) => WalletDto(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<Map<String, dynamic>> requestTopUp({
    required int walletId,
    required String amount,
    required String paymentMethod,
    required String paymentReference,
    String? correlationId,
  }) async {
    final body = <String, dynamic>{
      'amount': amount,
      'payment_method': paymentMethod,
      'payment_reference': paymentReference,
      if (correlationId != null && correlationId.trim().isNotEmpty)
        'correlation_id': correlationId.trim(),
    };
    final json = await _apiClient.post('/api/v1/me/wallets/$walletId/top-up',
        data: body);
    return Map<String, dynamic>.from(parseObjectEnvelope(json).data as Map);
  }
}
