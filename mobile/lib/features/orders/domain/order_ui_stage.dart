import '../data/order_repository.dart';

enum OrderUiStage {
  toPay,
  escrow,
  processing,
  shipped,
  delivered,
  completed,
  disputed,
  cancelled,
  other,
}

OrderUiStage inferOrderUiStage(OrderDto order) {
  final primary = _fromStatusText(order.status);
  if (primary != OrderUiStage.other) return primary;
  final escrow = _fromStatusText(order.escrowStatus);
  if (escrow != OrderUiStage.other) return escrow;
  return _fromStatusText(order.paymentStatus);
}

OrderUiStage _fromStatusText(String text) {
  final s = text.toLowerCase().trim();
  if (s.contains('disput')) return OrderUiStage.disputed;
  if (s.contains('cancel')) return OrderUiStage.cancelled;
  if (s.contains('complete')) return OrderUiStage.completed;
  if (s.contains('deliver')) return OrderUiStage.delivered;
  if (s.contains('ship')) return OrderUiStage.shipped;
  if (s.contains('process') || s.contains('prepar')) return OrderUiStage.processing;
  if (s.contains('escrow') || (s.contains('paid') && !s.contains('pending'))) return OrderUiStage.escrow;
  if (s.contains('pending') || s.contains('to pay') || s.contains('unpaid') || s.contains('awaiting payment')) {
    return OrderUiStage.toPay;
  }
  return OrderUiStage.other;
}
