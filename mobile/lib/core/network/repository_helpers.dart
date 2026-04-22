import '../models/api_envelope.dart';
import '../pagination/pagination_meta.dart';

ApiEnvelope<Map<String, dynamic>> parseObjectEnvelope(Map<String, dynamic> json) {
  return ApiEnvelope<Map<String, dynamic>>.fromJson(
    json,
    (raw) => Map<String, dynamic>.from(raw as Map),
  );
}

ApiEnvelope<List<Map<String, dynamic>>> parseListEnvelope(Map<String, dynamic> json) {
  return ApiEnvelope<List<Map<String, dynamic>>>.fromJson(
    json,
    (raw) => (raw as List<dynamic>)
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList(),
  );
}

PaginatedResult<Map<String, dynamic>> parsePaginatedObjectList(Map<String, dynamic> json) {
  final envelope = parseListEnvelope(json);
  final meta = PaginationMeta.fromJson(envelope.meta ?? const <String, dynamic>{});
  return PaginatedResult<Map<String, dynamic>>(items: envelope.data, meta: meta);
}
