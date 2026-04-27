import '../../../core/network/api_client.dart';

class ReturnRequestDto {
  ReturnRequestDto({
    required this.id,
    required this.orderId,
    required this.reasonCode,
    required this.status,
    required this.rmaCode,
    required this.slaStatus,
    required this.slaDueAt,
    required this.reverseLogisticsStatus,
    required this.returnTrackingUrl,
    required this.returnCarrier,
    required this.refundStatus,
    required this.refundAmount,
    required this.notes,
    required this.decisionNote,
    required this.timeline,
    required this.requestedAt,
    required this.decidedAt,
  });

  factory ReturnRequestDto.fromJson(Map<String, dynamic> json) {
    final timelineRaw =
        (json['timeline'] as List?)?.cast<dynamic>() ?? const <dynamic>[];
    return ReturnRequestDto(
      id: (json['id'] as num?)?.toInt() ?? 0,
      orderId: (json['order_id'] as num?)?.toInt() ?? 0,
      reasonCode: (json['reason_code'] as String?) ?? '',
      status: (json['status'] as String?) ?? 'requested',
      rmaCode: json['rma_code'] as String?,
      slaStatus: (json['sla_status'] as String?) ?? 'on_track',
      slaDueAt: json['sla_due_at'] as String?,
      reverseLogisticsStatus: (json['reverse_logistics_status'] as String?) ??
          'pending_buyer_shipment',
      returnTrackingUrl: json['return_tracking_url'] as String?,
      returnCarrier: json['return_carrier'] as String?,
      refundStatus: (json['refund_status'] as String?) ?? 'not_started',
      refundAmount: json['refund_amount'] as String?,
      notes: json['notes'] as String?,
      decisionNote: json['decision_note'] as String?,
      timeline: timelineRaw
          .map((e) => ReturnTimelineEventDto.fromJson(
              (e as Map).cast<String, dynamic>()))
          .toList(),
      requestedAt: json['requested_at'] as String?,
      decidedAt: json['decided_at'] as String?,
    );
  }

  final int id;
  final int orderId;
  final String reasonCode;
  final String status;
  final String? rmaCode;
  final String slaStatus;
  final String? slaDueAt;
  final String reverseLogisticsStatus;
  final String? returnTrackingUrl;
  final String? returnCarrier;
  final String refundStatus;
  final String? refundAmount;
  final String? notes;
  final String? decisionNote;
  final List<ReturnTimelineEventDto> timeline;
  final String? requestedAt;
  final String? decidedAt;
}

class ReturnEligibilityDto {
  ReturnEligibilityDto({
    required this.eligible,
    required this.reason,
    required this.windowDays,
    required this.requestDeadlineAt,
  });

  factory ReturnEligibilityDto.fromJson(Map<String, dynamic> json) {
    return ReturnEligibilityDto(
      eligible: json['eligible'] == true,
      reason: (json['reason'] as String?) ?? 'unknown',
      windowDays: (json['window_days'] as num?)?.toInt() ?? 14,
      requestDeadlineAt: json['request_deadline_at'] as String?,
    );
  }

  final bool eligible;
  final String reason;
  final int windowDays;
  final String? requestDeadlineAt;
}

class ReturnTimelineEventDto {
  ReturnTimelineEventDto({
    required this.eventCode,
    required this.createdAt,
  });

  factory ReturnTimelineEventDto.fromJson(Map<String, dynamic> json) {
    return ReturnTimelineEventDto(
      eventCode: (json['event_code'] as String?) ?? '',
      createdAt: json['created_at'] as String?,
    );
  }

  final String eventCode;
  final String? createdAt;
}

class ReturnsRepository {
  ReturnsRepository(this._api);

  final ApiClient _api;

  Future<List<ReturnRequestDto>> listBuyerReturns() async {
    final response = await _api.get('/api/v1/returns');
    final items =
        ((response['data'] as Map?)?['items'] as List?)?.cast<dynamic>() ??
            const <dynamic>[];
    return items
        .map((e) =>
            ReturnRequestDto.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<ReturnRequestDto> createReturn({
    required int orderId,
    required String reasonCode,
    String? notes,
  }) async {
    final response = await _api.post(
      '/api/v1/returns',
      data: <String, dynamic>{
        'order_id': orderId,
        'reason_code': reasonCode,
        'notes': notes,
      },
    );
    return ReturnRequestDto.fromJson(
        ((response['data'] as Map?) ?? <String, dynamic>{})
            .cast<String, dynamic>());
  }

  Future<ReturnRequestDto> getReturnDetail(int returnId) async {
    final response = await _api.get('/api/v1/returns/$returnId');
    return ReturnRequestDto.fromJson(
        ((response['data'] as Map?) ?? <String, dynamic>{})
            .cast<String, dynamic>());
  }

  Future<ReturnEligibilityDto> checkEligibility(int orderId) async {
    final response =
        await _api.get('/api/v1/orders/$orderId/returns/eligibility');
    return ReturnEligibilityDto.fromJson(
      ((response['data'] as Map?) ?? <String, dynamic>{})
          .cast<String, dynamic>(),
    );
  }

  Future<List<ReturnRequestDto>> listSellerReturns() async {
    final response = await _api.get('/api/v1/seller/returns');
    final items =
        ((response['data'] as Map?)?['items'] as List?)?.cast<dynamic>() ??
            const <dynamic>[];
    return items
        .map((e) =>
            ReturnRequestDto.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<List<ReturnRequestDto>> listAdminReturns() async {
    final response = await _api.get('/api/v1/admin/returns');
    final items =
        ((response['data'] as Map?)?['items'] as List?)?.cast<dynamic>() ??
            const <dynamic>[];
    return items
        .map((e) =>
            ReturnRequestDto.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> escalateReturn({required int returnId, String? note}) async {
    await _api.post(
      '/api/v1/admin/returns/$returnId/escalate',
      data: <String, dynamic>{'note': note},
    );
  }

  Future<ReturnRequestDto> markShippedBack({
    required int returnId,
    String? trackingUrl,
    String? carrier,
  }) async {
    final response = await _api.post(
      '/api/v1/returns/$returnId/shipped-back',
      data: <String, dynamic>{
        'tracking_url': trackingUrl,
        'carrier': carrier,
      },
    );
    return ReturnRequestDto.fromJson(
      ((response['data'] as Map?) ?? <String, dynamic>{})
          .cast<String, dynamic>(),
    );
  }

  Future<ReturnRequestDto> markSellerReceived(int returnId) async {
    final response =
        await _api.post('/api/v1/seller/returns/$returnId/received');
    return ReturnRequestDto.fromJson(
      ((response['data'] as Map?) ?? <String, dynamic>{})
          .cast<String, dynamic>(),
    );
  }

  Future<ReturnRequestDto> submitRefund(int returnId, {String? amount}) async {
    final response = await _api.post(
      '/api/v1/admin/returns/$returnId/refund/submit',
      data: <String, dynamic>{'amount': amount},
    );
    return ReturnRequestDto.fromJson(
      ((response['data'] as Map?) ?? <String, dynamic>{})
          .cast<String, dynamic>(),
    );
  }

  Future<ReturnRequestDto> confirmRefund(int returnId) async {
    final response =
        await _api.post('/api/v1/admin/returns/$returnId/refund/confirm');
    return ReturnRequestDto.fromJson(
      ((response['data'] as Map?) ?? <String, dynamic>{})
          .cast<String, dynamic>(),
    );
  }

  Future<ReturnRequestDto> decide({
    required int returnId,
    required String decision,
    String? decisionNote,
  }) async {
    final response = await _api.patch(
      '/api/v1/seller/returns/$returnId/decision',
      data: <String, dynamic>{
        'decision': decision,
        'decision_note': decisionNote,
      },
    );
    return ReturnRequestDto.fromJson(
        ((response['data'] as Map?) ?? <String, dynamic>{})
            .cast<String, dynamic>());
  }
}
