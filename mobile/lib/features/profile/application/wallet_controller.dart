import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/wallet_repository.dart';

class WalletState {
  const WalletState({
    this.wallets = const <WalletDto>[],
    this.isLoading = false,
    this.isSubmitting = false,
    this.errorMessage,
    this.successMessage,
  });

  final List<WalletDto> wallets;
  final bool isLoading;
  final bool isSubmitting;
  final String? errorMessage;
  final String? successMessage;

  WalletState copyWith({
    List<WalletDto>? wallets,
    bool? isLoading,
    bool? isSubmitting,
    String? errorMessage,
    String? successMessage,
  }) {
    return WalletState(
      wallets: wallets ?? this.wallets,
      isLoading: isLoading ?? this.isLoading,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      errorMessage: errorMessage,
      successMessage: successMessage,
    );
  }
}

final walletControllerProvider =
    NotifierProvider<WalletController, WalletState>(WalletController.new);

class WalletController extends Notifier<WalletState> {
  @override
  WalletState build() => const WalletState();

  Future<void> load() async {
    state = state.copyWith(
        isLoading: true, errorMessage: null, successMessage: null);
    try {
      final wallets = await ref.read(walletRepositoryProvider).listWallets();
      state = state.copyWith(
          wallets: wallets, isLoading: false, errorMessage: null);
    } catch (error) {
      state = state.copyWith(isLoading: false, errorMessage: error.toString());
    }
  }

  Future<void> requestTopUp(
      {required int walletId,
      required String amount,
      required String paymentMethod,
      required String paymentReference}) async {
    state = state.copyWith(
        isSubmitting: true, errorMessage: null, successMessage: null);
    try {
      await ref.read(walletRepositoryProvider).requestTopUp(
            walletId: walletId,
            amount: amount,
            paymentMethod: paymentMethod,
            paymentReference: paymentReference,
          );
      await load();
      state = state.copyWith(
        isSubmitting: false,
        successMessage: 'Request submitted for review.',
      );
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: error.toString(),
      );
    }
  }
}
