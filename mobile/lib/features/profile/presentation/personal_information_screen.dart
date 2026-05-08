import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/my_profile_controller.dart';

/// Editable buyer account fields with a lighter, more premium presentation.
class PersonalInformationScreen extends ConsumerStatefulWidget {
  const PersonalInformationScreen({super.key});

  @override
  ConsumerState<PersonalInformationScreen> createState() =>
      _PersonalInformationScreenState();
}

class _PersonalInformationScreenState
    extends ConsumerState<PersonalInformationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _displayNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _seeded = false;
  bool _hidePassword = true;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
        () => ref.read(myProfileControllerProvider.notifier).load());
  }

  @override
  void dispose() {
    _displayNameController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(myProfileControllerProvider);
    final profile = state.profile;
    final cs = Theme.of(context).colorScheme;

    if (profile != null && !_seeded) {
      _displayNameController.text = profile.displayName;
      _phoneController.text = profile.phone;
      _seeded = true;
    }

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Personal Info'),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: state.isLoading && profile == null
            ? const Center(child: CircularProgressIndicator())
            : state.errorMessage != null && profile == null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          Text(state.errorMessage!,
                              textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: () => ref
                                .read(myProfileControllerProvider.notifier)
                                .load(),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: () =>
                        ref.read(myProfileControllerProvider.notifier).load(),
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                      children: <Widget>[
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: cs.surface,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(
                                color:
                                    cs.outlineVariant.withValues(alpha: 0.35)),
                            boxShadow: <BoxShadow>[
                              BoxShadow(
                                color: cs.shadow.withValues(alpha: 0.05),
                                blurRadius: 16,
                                offset: const Offset(0, 6),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                'Account',
                                style: Theme.of(context)
                                    .textTheme
                                    .titleSmall
                                    ?.copyWith(fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 8),
                              Text('Email: ${profile?.email ?? '—'}',
                                  style:
                                      Theme.of(context).textTheme.bodyMedium),
                              const SizedBox(height: 4),
                              Text(
                                'Country: ${profile == null || profile.country.isEmpty ? 'N/A' : profile.country}',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(color: cs.onSurfaceVariant),
                              ),
                              Text(
                                'Currency: ${profile == null || profile.currency.isEmpty ? 'N/A' : profile.currency}',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(color: cs.onSurfaceVariant),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                        if (state.successMessage != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: Colors.green.shade50,
                                borderRadius: BorderRadius.circular(14),
                                border:
                                    Border.all(color: Colors.green.shade100),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: <Widget>[
                                    Icon(Icons.check_circle_outline,
                                        color: Colors.green.shade700),
                                    const SizedBox(width: 8),
                                    Expanded(
                                        child: Text(state.successMessage!)),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        if (state.errorMessage != null && profile != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: Colors.red.shade50,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: Colors.red.shade100),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: <Widget>[
                                    Icon(Icons.error_outline,
                                        color: Colors.red.shade700),
                                    const SizedBox(width: 8),
                                    Expanded(child: Text(state.errorMessage!)),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: cs.surface,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(
                                color:
                                    cs.outlineVariant.withValues(alpha: 0.35)),
                          ),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: <Widget>[
                                Text(
                                  'Edit',
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleSmall
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _displayNameController,
                                  decoration: InputDecoration(
                                    labelText: 'Display name',
                                    border: OutlineInputBorder(
                                        borderRadius:
                                            BorderRadius.circular(14)),
                                  ),
                                  validator: (value) {
                                    if ((value ?? '').trim().isEmpty) {
                                      return 'Display name is required';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _phoneController,
                                  keyboardType: TextInputType.phone,
                                  decoration: InputDecoration(
                                    labelText: 'Phone',
                                    border: OutlineInputBorder(
                                        borderRadius:
                                            BorderRadius.circular(14)),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _hidePassword,
                                  decoration: InputDecoration(
                                    labelText: 'Password',
                                    border: OutlineInputBorder(
                                        borderRadius:
                                            BorderRadius.circular(14)),
                                    suffixIcon: IconButton(
                                      onPressed: () => setState(
                                          () => _hidePassword = !_hidePassword),
                                      icon: Icon(_hidePassword
                                          ? Icons.visibility_outlined
                                          : Icons.visibility_off_outlined),
                                    ),
                                  ),
                                  validator: (value) {
                                    final input = (value ?? '').trim();
                                    if (input.isNotEmpty && input.length < 8) {
                                      return 'Password must be at least 8 characters';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 18),
                                FilledButton(
                                  onPressed: state.isSaving
                                      ? null
                                      : () async {
                                          if (!(_formKey.currentState
                                                  ?.validate() ??
                                              false)) {
                                            return;
                                          }
                                          await ref
                                              .read(myProfileControllerProvider
                                                  .notifier)
                                              .update(
                                                displayName:
                                                    _displayNameController.text,
                                                phone: _phoneController.text,
                                                password:
                                                    _passwordController.text,
                                              );
                                          _passwordController.clear();
                                        },
                                  child: state.isSaving
                                      ? const SizedBox(
                                          height: 22,
                                          width: 22,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2),
                                        )
                                      : const Text('Save'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
      ),
    );
  }
}
