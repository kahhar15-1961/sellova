import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../data/profile_repository.dart';

class SellerProfileState {
  const SellerProfileState({
    this.profile,
    this.isLoading = false,
    this.isSaving = false,
    this.errorMessage,
    this.successMessage,
    this.hasSellerProfile = true,
  });

  final SellerProfileDto? profile;
  final bool isLoading;
  final bool isSaving;
  final String? errorMessage;
  final String? successMessage;
  final bool hasSellerProfile;

  SellerProfileState copyWith({
    SellerProfileDto? profile,
    bool? isLoading,
    bool? isSaving,
    String? errorMessage,
    String? successMessage,
    bool? hasSellerProfile,
  }) {
    return SellerProfileState(
      profile: profile ?? this.profile,
      isLoading: isLoading ?? this.isLoading,
      isSaving: isSaving ?? this.isSaving,
      errorMessage: errorMessage,
      successMessage: successMessage,
      hasSellerProfile: hasSellerProfile ?? this.hasSellerProfile,
    );
  }
}

final sellerProfileControllerProvider =
    NotifierProvider<SellerProfileController, SellerProfileState>(
  SellerProfileController.new,
);

class SellerProfileController extends Notifier<SellerProfileState> {
  @override
  SellerProfileState build() => const SellerProfileState();

  Future<void> load() async {
    state = state.copyWith(isLoading: true, errorMessage: null, successMessage: null);
    try {
      final profile = await ref.read(profileRepositoryProvider).getMeSeller();
      state = state.copyWith(
        profile: profile,
        isLoading: false,
        hasSellerProfile: true,
      );
    } catch (error) {
      if (error is ApiException && error.type == ApiExceptionType.notFound) {
        state = state.copyWith(
          profile: null,
          isLoading: false,
          hasSellerProfile: false,
          errorMessage: null,
        );
        return;
      }
      state = state.copyWith(
        isLoading: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<void> update({
    required String displayName,
    required String legalName,
  }) async {
    state = state.copyWith(isSaving: true, errorMessage: null, successMessage: null);
    try {
      final request = <String, dynamic>{
        if (displayName.trim().isNotEmpty) 'display_name': displayName.trim(),
        if (legalName.trim().isNotEmpty) 'legal_name': legalName.trim(),
      };
      final profile = await ref.read(profileRepositoryProvider).updateMeSeller(request);
      state = state.copyWith(
        profile: profile,
        hasSellerProfile: true,
        isSaving: false,
        successMessage: 'Seller profile updated',
      );
    } catch (error) {
      state = state.copyWith(
        isSaving: false,
        errorMessage: error.toString(),
      );
    }
  }
}
