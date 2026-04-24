import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
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
    required this.notifications,
    this.isLoading = false,
    this.isSaving = false,
    this.failure,
    this.successMessage,
  });

  final SellerStoreSettings storeSettings;
  final SellerShippingSettings shippingSettings;
  final List<SellerPayoutMethod> payoutMethods;
  final List<SellerNotificationItem> notifications;
  final bool isLoading;
  final bool isSaving;
  final SellerFailure? failure;
  final String? successMessage;

  String? get errorMessage => failure?.message;

  SellerBusinessState copyWith({
    SellerStoreSettings? storeSettings,
    SellerShippingSettings? shippingSettings,
    List<SellerPayoutMethod>? payoutMethods,
    List<SellerNotificationItem>? notifications,
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
      notifications: notifications ?? this.notifications,
      isLoading: isLoading ?? this.isLoading,
      isSaving: isSaving ?? this.isSaving,
      failure: clearFailure ? null : (failure ?? this.failure),
      successMessage: clearSuccess ? null : (successMessage ?? this.successMessage),
    );
  }
}

final sellerBusinessControllerProvider = NotifierProvider<SellerBusinessController, SellerBusinessState>(
  SellerBusinessController.new,
);

class SellerBusinessController extends Notifier<SellerBusinessState> {
  @override
  SellerBusinessState build() {
    Future<void>.microtask(load);
    return SellerBusinessState(
      storeSettings: const SellerStoreSettings(
        storeName: 'Tech Haven',
        storeDescription: 'We provide high quality electronic products with the best customer service.',
        contactEmail: 'support@example.com',
        contactPhone: '+880 1912-345678',
      ),
      shippingSettings: const SellerShippingSettings(
        insideDhakaLabel: 'Inside Dhaka',
        insideDhakaFee: 60,
        outsideDhakaLabel: 'Outside Dhaka',
        outsideDhakaFee: 120,
        cashOnDeliveryEnabled: true,
        processingTimeLabel: '1-2 Business Days',
      ),
      payoutMethods: const <SellerPayoutMethod>[
        SellerPayoutMethod(
          id: '1',
          type: SellerPayoutMethodType.bkash,
          accountName: 'Tech Haven',
          accountNumberMasked: '01XXXXXXXXX',
          providerName: 'bKash',
          isDefault: true,
        ),
      ],
      notifications: const <SellerNotificationItem>[
        SellerNotificationItem(
          id: 'n1',
          title: 'New Order Received',
          body: 'You have received a new order ORD-2025-000124',
          timeAgoLabel: '2m ago',
          kind: 'order',
          read: false,
        ),
      ],
    );
  }

  SellerBusinessDataSource get _ds => ref.read(sellerBusinessDataSourceProvider);
  Telemetry get _telemetry => ref.read(telemetryProvider);
  SellerFormDraftStore get _drafts => ref.read(sellerFormDraftStoreProvider);

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearFailure: true, clearSuccess: true);
    _telemetry.record('seller.business.load_attempt');
    try {
      await runWithRetry(() async {
        final results = await Future.wait(<Future<Object>>[
          _ds.getStoreSettings(),
          _ds.getShippingSettings(),
          _ds.listPayoutMethods(),
          _ds.listSellerNotifications(),
        ]);
        final store = results[0] as SellerStoreSettings;
        final shipping = results[1] as SellerShippingSettings;
        final payouts = results[2] as List<SellerPayoutMethod>;
        final notifications = results[3] as List<SellerNotificationItem>;
        state = state.copyWith(
          isLoading: false,
          storeSettings: store,
          shippingSettings: shipping,
          payoutMethods: payouts.isEmpty ? state.payoutMethods : payouts,
          notifications: notifications.isEmpty ? state.notifications : notifications,
          clearFailure: true,
          clearSuccess: true,
        );
      });
      _telemetry.record('seller.business.load_success');
    } catch (error) {
      final failure = SellerFailure.from(error);
      state = state.copyWith(isLoading: false, failure: failure, clearSuccess: true);
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
  }) async {
    final prev = state.storeSettings;
    final optimistic = SellerStoreSettings(
      storeName: storeName.trim(),
      storeDescription: storeDescription.trim(),
      storeLogoUrl: prev.storeLogoUrl,
      bannerImageUrl: prev.bannerImageUrl,
      contactEmail: prev.contactEmail,
      contactPhone: prev.contactPhone,
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
            ),
        maxAttempts: 2,
      );
      state = state.copyWith(
        isSaving: false,
        storeSettings: updated.copyWith(
          storeName: updated.storeName.isEmpty ? storeName.trim() : updated.storeName,
          storeDescription: updated.storeDescription.isEmpty ? storeDescription.trim() : updated.storeDescription,
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
      final updated = await runWithRetry(() => _ds.updateShippingSettings(next), maxAttempts: 2);
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
        asDefault: asDefault,
      );
      state = state.copyWith(
        isSaving: false,
        payoutMethods: remote.isNotEmpty ? remote : optimistic,
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
  }) async {
    state = state.copyWith(isSaving: true, clearFailure: true, clearSuccess: true);
    _telemetry.record('seller.withdraw.request_attempt');
    try {
      await runWithRetry(
        () => _ds.requestWithdrawal(
              methodType: type,
              accountNumber: accountNumber.trim(),
              amountText: amountText.trim(),
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

  void markNotificationRead(String id) {
    state = state.copyWith(
      notifications: state.notifications.map((e) => e.id == id ? e.copyWith(read: true) : e).toList(),
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
      state = state.copyWith(notifications: prev, failure: SellerFailure.from(error));
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
