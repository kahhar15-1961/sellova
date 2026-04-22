class PaginationMeta {
  const PaginationMeta({
    required this.page,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.raw,
  });

  final int page;
  final int perPage;
  final int total;
  final int lastPage;
  final Map<String, dynamic> raw;

  bool get hasMore => page < lastPage;

  factory PaginationMeta.fromJson(Map<String, dynamic> json) {
    return PaginationMeta(
      page: (json['page'] as num?)?.toInt() ?? 1,
      perPage: (json['per_page'] as num?)?.toInt() ?? 10,
      total: (json['total'] as num?)?.toInt() ?? 0,
      lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
      raw: Map<String, dynamic>.from(json),
    );
  }
}

class PaginatedResult<T> {
  const PaginatedResult({
    required this.items,
    required this.meta,
  });

  final List<T> items;
  final PaginationMeta meta;
}
