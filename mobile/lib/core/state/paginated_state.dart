import '../pagination/pagination_meta.dart';

class PaginatedState<T> {
  const PaginatedState({
    this.items = const [],
    this.meta,
    this.isInitialLoading = false,
    this.isAppending = false,
    this.errorMessage,
  });

  final List<T> items;
  final PaginationMeta? meta;
  final bool isInitialLoading;
  final bool isAppending;
  final String? errorMessage;

  bool get hasMore => meta?.hasMore ?? false;

  PaginatedState<T> copyWith({
    List<T>? items,
    PaginationMeta? meta,
    bool? isInitialLoading,
    bool? isAppending,
    String? errorMessage,
  }) {
    return PaginatedState<T>(
      items: items ?? this.items,
      meta: meta ?? this.meta,
      isInitialLoading: isInitialLoading ?? this.isInitialLoading,
      isAppending: isAppending ?? this.isAppending,
      errorMessage: errorMessage,
    );
  }
}
