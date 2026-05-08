import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../../../core/network/network_retry.dart';
import '../../../core/telemetry/telemetry.dart';
import 'seller_failure.dart';
import '../data/seller_business_datasource.dart';
import '../data/seller_form_draft_store.dart';
import '../domain/seller_models.dart';

class SellerBusinessState {
  const SellerBusinessState({
    required this.storeSettings,
    required this.shippingSettings,
    required this.payoutMethods,
    required this.withdrawalSettings,
    required this.notifications,
    this.sellerAccessChecked = false,
    this.hasSellerProfile = false,
    this.isLoading = false,
    this.isSaving = false,
    this.failure,
    this.successMessage,
  });

  final SellerStoreSettings storeSettings;
  final SellerShippingSettings shippingSettings;
  final List<SellerPayoutMethod> payoutMethods;
  final SellerWithdrawalSettings withdrawalSettings;
  final List<SellerNotificationItem> notifications;
  final bool sellerAccessChecked;
  final bool hasSellerProfile;
  final bool isLoading;
  final bool isSaving;
  final SellerFailure? failure;
  final String? successMessage;

  String? get errorMessage => failure?.message;

  SellerBusinessState copyWith({
    SellerStoreSettings? storeSettings,
    SellerShippingSettings? shippingSettings,
    List<SellerPayoutMethod>? payoutMethods,
    SellerWithdrawalSettings? withdrawalSettings,
    List<SellerNotificationItem>? notifications,
    bool? sellerAccessChecked,
    bool? hasSellerProfile,
    bool? isLoading,
    bool? isSaving,
    SellerFailure? failure,
    String? successMessage,
    bool clearFailure = false,
    bool clearSuccess = false,
  }) {
    return SellerBusinessState(
      storeSettings: storeSettings ?? this.storeSettings,
      shippingSettings: shippingSettings ?? this.shippingSettings,
      payoutMethods: payoutMethods ?? this.payoutMethods,
      withdrawalSettings: withdrawalSettings ?? this.withdrawalSettings,
      notifications: notifications ?? this.notifications,
      sellerAccessChecked: sellerAccessChecked ?? this.sellerAccessChecked,
      hasSellerProfile: hasSellerProfile ?? this.hasSellerProfile,
      isLoading: isLoading ?? this.isLoading,
      isSaving: isSaving ?? this.isSaving,
      failure: clearFailure ? null : (failure ?? this.failure),
      successMessage:
          clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

final sellerBusinessControllerProvider =
    NotifierProvider<SellerBusinessController, SellerBusinessState>(
  SellerBusinessController.new,
);

class SellerBusinessController extends Notifier<SellerBusinessState> {
  @override
  SellerBusinessState build() {
    Future<void>.microtask(load);
    return const SellerBusinessState(
      storeSettings: SellerStoreSettings(
        storeName: '',
        storeDescription:
            'Store details will appear here after access is verified.',
        contactEmail: '',
        contactPhone: '',
      ),
      shippingSettings: SellerShippingSettings(
        insideDhakaLabel: '',
        insideDhakaFee: 0,
        outsideDhakaLabel: '',
        outsideDhakaFee: 0,
        cashOnDeliveryEnabled: true,
        processingTimeLabel: '',
      ),
      payoutMethods: <SellerPayoutMethod>[],
      withdrawalSettings: SellerWithdrawalSettings.fallback,
      notifications: <SellerNotificationItem>[],
    );
  }

  SellerBusinessDataSource get _ds =>
      ref.read(sellerBusinessDataSourceProvider);
  Telemetry get _telemetry => ref.read(telemetryProvider);
  SellerFormDraftStore get _drafts => ref.read(sellerFormDraftStoreProvider);

  Future<void> load() async {
    state =
        state.copyWith(isLoading: true, clearFailure: true, clearSuccess: true);
    _telemetry.record('seller.business.load_attempt');
    try {
      await runWithRetry(() async {
        final results = await Future.wait(<Future<Object>>[
          _ds.getStoreSettings(),
          _ds.getShippingSettings(),
          _ds.listPayoutMethods(),
          _ds.getWithdrawalSettings(),
          _ds.listSellerNotifications(),
        ]);
        final store = results[0] as SellerStoreSettings;
        final shipping = results[1] as SellerShippingSettings;
        final payouts = results[2] as List<SellerPayoutMethod>;
        final withdrawalSettings = results[3] as SellerWithdrawalSettings;
        final notifications = results[4] as List<SellerNotificationItem>;
        state = state.copyWith(
          isLoading: false,
          sellerAccessChecked: true,
          hasSellerProfile: true,
          storeSettings: store,
          shippingSettings: shipping,
          payoutMethods: payouts,
          withdrawalSettings: withdrawalSettings,
          notifications: notifications,
          clearFailure: true,
          clearSuccess: true,
        );
      });
      _telemetry.record('seller.business.load_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      final clearedState = state.copyWith(
        isLoading: false,
        sellerAccessChecked: true,
        hasSellerProfile: false,
        storeSettings: const SellerStoreSettings(
          storeName: '',
          storeDescription: '',
          contactEmail: '',
          contactPhone: '',
          addressLine: '',
          city: '',
          region: '',
          postalCode: '',
          country: '',
          storeAddress: '',
        ),
        shippingSettings: const SellerShippingSettings(
          insideDhakaLabel: '',
          insideDhakaFee: 0,
          outsideDhakaLabel: '',
          outsideDhakaFee: 0,
          cashOnDeliveryEnabled: false,
          processingTimeLabel: '',
        ),
        payoutMethods: <SellerPayoutMethod>[],
        withdrawalSettings: SellerWithdrawalSettings.fallback,
        notifications: <SellerNotificationItem>[],
        clearSuccess: true,
      );
      state = failure.type == ApiExceptionType.notFound
          ? clearedState.copyWith(clearFailure: true)
          : clearedState.copyWith(failure: failure);
      _telemetry.record('seller.business.load_failed', <String, Object?>{
        'type': failure.type?.name,
        'code': failure.code,
        'retryable': failure.retryable,
      });
    }
  }

  Future<void> saveStoreSettings({
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
  }) async {
    final prev = state.storeSettings;
    final optimistic = SellerStoreSettings(
      storeName: storeName.trim(),
      storeDescription: storeDescription.trim(),
      storeLogoUrl: storeLogoUrl ?? prev.storeLogoUrl,
      bannerImageUrl: bannerImageUrl ?? prev.bannerImageUrl,
      contactEmail: contactEmail?.trim() ?? prev.contactEmail,
      contactPhone: contactPhone?.trim() ?? prev.contactPhone,
      addressLine: addressLine?.trim() ?? prev.addressLine,
      city: city?.trim() ?? prev.city,
      region: region?.trim() ?? prev.region,
      postalCode: postalCode?.trim() ?? prev.postalCode,
      country: country?.trim() ?? prev.country,
      storeAddress: _formatStoreAddress(
        addressLine: addressLine?.trim() ?? prev.addressLine,
        city: city?.trim() ?? prev.city,
        region: region?.trim() ?? prev.region,
        postalCode: postalCode?.trim() ?? prev.postalCode,
        country: country?.trim() ?? prev.country,
      ),
    );
    state = state.copyWith(
      storeSettings: optimistic,
      isSaving: true,
      clearFailure: true,
      clearSuccess: true,
    );
    _telemetry.record('seller.store.save_attempt');
    try {
      final updated = await runWithRetry(
        () => _ds.updateStoreSettings(
          storeName: storeName.trim(),
          storeDescription: storeDescription.trim(),
          storeLogoUrl: storeLogoUrl ?? prev.storeLogoUrl,
          bannerImageUrl: bannerImageUrl ?? prev.bannerImageUrl,
          contactEmail: contactEmail?.trim() ?? prev.contactEmail,
          contactPhone: contactPhone?.trim() ?? prev.contactPhone,
          addressLine: addressLine?.trim() ?? prev.addressLine,
          city: city?.trim() ?? prev.city,
          region: region?.trim() ?? prev.region,
          postalCode: postalCode?.trim() ?? prev.postalCode,
          country: country?.trim() ?? prev.country,
        ),
        maxAttempts: 2,
      );
      state = state.copyWith(
        isSaving: false,
        storeSettings: updated.copyWith(
          storeName:
              updated.storeName.isEmpty ? storeName.trim() : updated.storeName,
          storeDescription: updated.storeDescription.isEmpty
              ? storeDescription.trim()
              : updated.storeDescription,
        ),
        successMessage: 'Store settings saved.',
        clearFailure: true,
      );
      await _drafts.clearStoreSettingsDraft();
      _telemetry.record('seller.store.save_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      state = state.copyWith(
        storeSettings: prev,
        isSaving: false,
        failure: failure,
      );
      _telemetry.record('seller.store.save_failed', <String, Object?>{
        'type': failure.type?.name,
        'code': failure.code,
        'retryable': failure.retryable,
      });
      _telemetry.record('seller.store.save_rollback');
    }
  }

  String _formatStoreAddress({
    String? addressLine,
    String? city,
    String? region,
    String? postalCode,
    String? country,
  }) {
    return <String?>[addressLine, city, region, postalCode, country]
        .map((value) => (value ?? '').trim())
        .where((value) => value.isNotEmpty)
        .join(', ');
  }

  Future<void> saveShippingSettings(SellerShippingSettings next) async {
    final prev = state.shippingSettings;
    state = state.copyWith(
      shippingSettings: next,
      isSaving: true,
      clearFailure: true,
      clearSuccess: true,
    );
    _telemetry.record('seller.shipping.save_attempt');
    try {
      final updated = await runWithRetry(() => _ds.updateShippingSettings(next),
          maxAttempts: 2);
      state = state.copyWith(
        isSaving: false,
        shippingSettings: updated,
        successMessage: 'Shipping settings saved.',
        clearFailure: true,
      );
      await _drafts.clearShippingDraft();
      _telemetry.record('seller.shipping.save_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      state = state.copyWith(
        shippingSettings: prev,
        isSaving: false,
        failure: failure,
      );
      _telemetry.record('seller.shipping.save_failed', <String, Object?>{
        'type': failure.type?.name,
        'code': failure.code,
      });
      _telemetry.record('seller.shipping.save_rollback');
    }
  }

  Future<void> addOrUpdatePayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    String? branchName,
    String? routingNumber,
    String? accountType,
    bool asDefault = false,
  }) async {
    final prev = state.payoutMethods;
    final provisional = SellerPayoutMethod(
      id: 'local-${DateTime.now().millisecondsSinceEpoch}',
      type: type,
      accountName: accountName.trim(),
      accountNumberMasked: _maskAccount(accountNumber.trim()),
      bankName: bankName?.trim(),
      isDefault: asDefault,
    );
    final optimistic = <SellerPayoutMethod>[
      if (asDefault) ...prev.map((e) => e.copyWith(isDefault: false)),
      if (!asDefault) ...prev,
      provisional,
    ];
    state = state.copyWith(
      payoutMethods: optimistic,
      isSaving: true,
      clearFailure: true,
      clearSuccess: true,
    );
    _telemetry.record('seller.payout.save_attempt');
    try {
      final remote = await _ds.upsertPayoutMethod(
        type: type,
        accountName: accountName.trim(),
        accountNumber: accountNumber.trim(),
        bankName: bankName,
        branchName: branchName,
        routingNumber: routingNumber,
        accountType: accountType,
        asDefault: asDefault,
      );
      state = state.copyWith(
        isSaving: false,
        payoutMethods: remote,
        successMessage: 'Payout method saved.',
        clearFailure: true,
      );
      await _drafts.clearBankPaymentDraft();
      _telemetry.record('seller.payout.save_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      state = state.copyWith(
        payoutMethods: prev,
        isSaving: false,
        failure: failure,
      );
      _telemetry.record('seller.payout.save_failed', <String, Object?>{
        'type': failure.type?.name,
        'code': failure.code,
      });
      _telemetry.record('seller.payout.save_rollback');
    }
  }

  Future<void> requestWithdrawal({
    required SellerPayoutMethodType type,
    required String accountNumber,
    required String amountText,
    int? walletId,
    String? currency,
  }) async {
    state =
        state.copyWith(isSaving: true, clearFailure: true, clearSuccess: true);
    _telemetry.record('seller.withdraw.request_attempt');
    try {
      await runWithRetry(
        () => _ds.requestWithdrawal(
          methodType: type,
          accountNumber: accountNumber.trim(),
          amountText: amountText.trim(),
          walletId: walletId,
          currency: currency,
        ),
        maxAttempts: 2,
      );
      state = state.copyWith(
        isSaving: false,
        successMessage: 'Withdrawal request submitted.',
        clearFailure: true,
      );
      await _drafts.clearWithdrawDraft();
      _telemetry.record('seller.withdraw.request_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      state = state.copyWith(
        isSaving: false,
        failure: failure,
      );
      _telemetry.record('seller.withdraw.request_failed', <String, Object?>{
        'type': failure.type?.name,
        'code': failure.code,
      });
      rethrow;
    }
  }

  Future<void> deletePayoutMethod(String id) async {
    final prev = state.payoutMethods;
    state = state.copyWith(
      payoutMethods: prev.where((method) => method.id != id).toList(),
      isSaving: true,
      clearFailure: true,
      clearSuccess: true,
    );
    _telemetry.record('seller.payout.delete_attempt');
    try {
      final remote = await runWithRetry(
        () => _ds.deletePayoutMethod(id),
        maxAttempts: 2,
      );
      state = state.copyWith(
        isSaving: false,
        payoutMethods: remote,
        successMessage: 'Payout method removed.',
        clearFailure: true,
      );
      _telemetry.record('seller.payout.delete_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      state = state.copyWith(
        payoutMethods: prev,
        isSaving: false,
        failure: failure,
      );
      _telemetry.record('seller.payout.delete_failed', <String, Object?>{
        'type': failure.type?.name,
        'code': failure.code,
      });
    }
  }

  void markNotificationRead(String id) {
    state = state.copyWith(
      notifications: state.notifications
          .map((e) => e.id == id ? e.copyWith(read: true) : e)
          .toList(),
    );
  }

  Future<void> markAllNotificationsRead() async {
    final prev = state.notifications;
    state = state.copyWith(
      notifications: prev.map((e) => e.copyWith(read: true)).toList(),
    );
    _telemetry.record('seller.notifications.mark_all_attempt');
    try {
      await runWithRetry(() => _ds.markAllNotificationsRead());
      _telemetry.record('seller.notifications.mark_all_success');
    } catch (error) {
      state = state.copyWith(
          notifications: prev, failure: SellerFailure.from(error));
      _telemetry.record('seller.notifications.mark_all_failed');
      _telemetry.record('seller.notifications.mark_all_rollback');
    }
  }

  static String _maskAccount(String value) {
    if (value.length <= 4) {
      return '****';
    }
    return '${value.substring(0, 2)}****${value.substring(value.length - 2)}';
  }
}
