class ApiEnvelope<T> {
  const ApiEnvelope({
    required this.data,
    this.meta,
  });

  final T data;
  final Map<String, dynamic>? meta;

  factory ApiEnvelope.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic raw) dataFactory,
  ) {
    return ApiEnvelope<T>(
      data: dataFactory(json['data']),
      meta: json['meta'] is Map<String, dynamic>
          ? json['meta'] as Map<String, dynamic>
          : null,
    );
  }
}

class ApiErrorEnvelope {
  const ApiErrorEnvelope({
    required this.error,
    required this.message,
    required this.context,
  });

  final String error;
  final String message;
  final Map<String, dynamic> context;

  factory ApiErrorEnvelope.fromJson(Map<String, dynamic> json) {
    final context = Map<String, dynamic>.from(json);
    context.remove('error');
    context.remove('message');
    return ApiErrorEnvelope(
      error: (json['error'] ?? 'unknown_error').toString(),
      message: (json['message'] ?? 'Unknown error').toString(),
      context: context,
    );
  }
}
