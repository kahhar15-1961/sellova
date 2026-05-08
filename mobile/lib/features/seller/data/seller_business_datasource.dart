import '../domain/seller_models.dart';
import 'seller_repository.dart';

/// Narrow surface used by seller business flows (testability + enterprise seams).
abstract class SellerBusinessDataSource {
  Future<SellerStoreSettings> getStoreSettings();

  Future<SellerStoreSettings> updateStoreSettings({
    required String storeName,
    required String storeDescription,
    String? storeLogoUrl,
    String? bannerImageUrl,
    String? contactEmail,
    String? contactPhone,
    String? addressLine,
    String? city,
    String? region,
    String? postalCode,
    String? country,
  });

  Future<SellerShippingSettings> getShippingSettings();

  Future<SellerShippingSettings> updateShippingSettings(
      SellerShippingSettings value);

  Future<List<SellerPayoutMethod>> listPayoutMethods();

  Future<SellerWithdrawalSettings> getWithdrawalSettings();

  Future<List<SellerPayoutMethod>> upsertPayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    String? branchName,
    String? routingNumber,
    String? accountType,
    bool asDefault = false,
  });

  Future<List<SellerPayoutMethod>> deletePayoutMethod(String id);

  Future<void> requestWithdrawal({
    required SellerPayoutMethodType methodType,
    required String accountNumber,
    required String amountText,
    int? walletId,
    String? currency,
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
    String? storeLogoUrl,
    String? bannerImageUrl,
    String? contactEmail,
    String? contactPhone,
    String? addressLine,
    String? city,
    String? region,
    String? postalCode,
    String? country,
  }) {
    return _inner.updateStoreSettings(
      storeName: storeName,
      storeDescription: storeDescription,
      storeLogoUrl: storeLogoUrl,
      bannerImageUrl: bannerImageUrl,
      contactEmail: contactEmail,
      contactPhone: contactPhone,
      addressLine: addressLine,
      city: city,
      region: region,
      postalCode: postalCode,
      country: country,
    );
  }

  @override
  Future<SellerShippingSettings> getShippingSettings() =>
      _inner.getShippingSettings();

  @override
  Future<SellerShippingSettings> updateShippingSettings(
      SellerShippingSettings value) {
    return _inner.updateShippingSettings(value);
  }

  @override
  Future<List<SellerPayoutMethod>> listPayoutMethods() =>
      _inner.listPayoutMethods();

  @override
  Future<SellerWithdrawalSettings> getWithdrawalSettings() =>
      _inner.getWithdrawalSettings();

  @override
  Future<List<SellerPayoutMethod>> upsertPayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    String? branchName,
    String? routingNumber,
    String? accountType,
    bool asDefault = false,
  }) {
    return _inner.upsertPayoutMethod(
      type: type,
      accountName: accountName,
      accountNumber: accountNumber,
      bankName: bankName,
      branchName: branchName,
      routingNumber: routingNumber,
      accountType: accountType,
      asDefault: asDefault,
    );
  }

  @override
  Future<List<SellerPayoutMethod>> deletePayoutMethod(String id) {
    return _inner.deletePayoutMethod(id);
  }

  @override
  Future<void> requestWithdrawal({
    required SellerPayoutMethodType methodType,
    required String accountNumber,
    required String amountText,
    int? walletId,
    String? currency,
  }) {
    return _inner.requestWithdrawal(
        methodType: methodType,
        accountNumber: accountNumber,
        amountText: amountText,
        walletId: walletId,
        currency: currency);
  }

  @override
  Future<List<SellerNotificationItem>> listSellerNotifications() =>
      _inner.listSellerNotifications();

  @override
  Future<void> markAllNotificationsRead() => _inner.markAllNotificationsRead();
}
