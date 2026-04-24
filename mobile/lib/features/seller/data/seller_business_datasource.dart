import '../domain/seller_models.dart';
import 'seller_repository.dart';

/// Narrow surface used by seller business flows (testability + enterprise seams).
abstract class SellerBusinessDataSource {
  Future<SellerStoreSettings> getStoreSettings();

  Future<SellerStoreSettings> updateStoreSettings({
    required String storeName,
    required String storeDescription,
  });

  Future<SellerShippingSettings> getShippingSettings();

  Future<SellerShippingSettings> updateShippingSettings(SellerShippingSettings value);

  Future<List<SellerPayoutMethod>> listPayoutMethods();

  Future<List<SellerPayoutMethod>> upsertPayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    bool asDefault = false,
  });

  Future<void> requestWithdrawal({
    required SellerPayoutMethodType methodType,
    required String accountNumber,
    required String amountText,
  });

  Future<List<SellerNotificationItem>> listSellerNotifications();

  Future<void> markAllNotificationsRead();
}

class SellerRepositoryBusinessAdapter implements SellerBusinessDataSource {
  SellerRepositoryBusinessAdapter(this._inner);

  final SellerRepository _inner;

  @override
  Future<SellerStoreSettings> getStoreSettings() => _inner.getStoreSettings();

  @override
  Future<SellerStoreSettings> updateStoreSettings({
    required String storeName,
    required String storeDescription,
  }) {
    return _inner.updateStoreSettings(storeName: storeName, storeDescription: storeDescription);
  }

  @override
  Future<SellerShippingSettings> getShippingSettings() => _inner.getShippingSettings();

  @override
  Future<SellerShippingSettings> updateShippingSettings(SellerShippingSettings value) {
    return _inner.updateShippingSettings(value);
  }

  @override
  Future<List<SellerPayoutMethod>> listPayoutMethods() => _inner.listPayoutMethods();

  @override
  Future<List<SellerPayoutMethod>> upsertPayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    bool asDefault = false,
  }) {
    return _inner.upsertPayoutMethod(
      type: type,
      accountName: accountName,
      accountNumber: accountNumber,
      bankName: bankName,
      asDefault: asDefault,
    );
  }

  @override
  Future<void> requestWithdrawal({
    required SellerPayoutMethodType methodType,
    required String accountNumber,
    required String amountText,
  }) {
    return _inner.requestWithdrawal(methodType: methodType, accountNumber: accountNumber, amountText: amountText);
  }

  @override
  Future<List<SellerNotificationItem>> listSellerNotifications() => _inner.listSellerNotifications();

  @override
  Future<void> markAllNotificationsRead() => _inner.markAllNotificationsRead();
}
