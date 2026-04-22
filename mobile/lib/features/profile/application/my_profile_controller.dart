import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/profile_repository.dart';

class MyProfileState {
  const MyProfileState({
    this.profile,
    this.isLoading = false,
    this.isSaving = false,
    this.errorMessage,
    this.successMessage,
  });

  final ActorProfileDto? profile;
  final bool isLoading;
  final bool isSaving;
  final String? errorMessage;
  final String? successMessage;

  MyProfileState copyWith({
    ActorProfileDto? profile,
    bool? isLoading,
    bool? isSaving,
    String? errorMessage,
    String? successMessage,
  }) {
    return MyProfileState(
      profile: profile ?? this.profile,
      isLoading: isLoading ?? this.isLoading,
      isSaving: isSaving ?? this.isSaving,
      errorMessage: errorMessage,
      successMessage: successMessage,
    );
  }
}

final myProfileControllerProvider = NotifierProvider<MyProfileController, MyProfileState>(
  MyProfileController.new,
);

class MyProfileController extends Notifier<MyProfileState> {
  @override
  MyProfileState build() => const MyProfileState();

  Future<void> load() async {
    state = state.copyWith(isLoading: true, errorMessage: null, successMessage: null);
    try {
      final profile = await ref.read(profileRepositoryProvider).getMe();
      state = state.copyWith(
        profile: profile,
        isLoading: false,
        errorMessage: null,
      );
    } catch (error) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<void> update({
    required String displayName,
    required String phone,
    required String password,
  }) async {
    state = state.copyWith(isSaving: true, errorMessage: null, successMessage: null);
    try {
      final request = <String, dynamic>{
        if (displayName.trim().isNotEmpty) 'display_name': displayName.trim(),
        if (phone.trim().isNotEmpty) 'phone': phone.trim(),
        if (password.trim().isNotEmpty) 'password': password,
      };
      final profile = await ref.read(profileRepositoryProvider).updateMe(request);
      state = state.copyWith(
        profile: profile,
        isSaving: false,
        successMessage: 'Profile updated',
      );
    } catch (error) {
      state = state.copyWith(
        isSaving: false,
        errorMessage: error.toString(),
      );
    }
  }
}
