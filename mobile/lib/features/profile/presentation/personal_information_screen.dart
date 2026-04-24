import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/my_profile_controller.dart';

/// Editable buyer account fields (moved from the profile hub for a cleaner IA).
class PersonalInformationScreen extends ConsumerStatefulWidget {
  const PersonalInformationScreen({super.key});

  @override
  ConsumerState<PersonalInformationScreen> createState() => _PersonalInformationScreenState();
}

class _PersonalInformationScreenState extends ConsumerState<PersonalInformationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _displayNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _seeded = false;
  bool _hidePassword = true;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() => ref.read(myProfileControllerProvider.notifier).load());
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

    if (profile != null && !_seeded) {
      _displayNameController.text = profile.displayName;
      _phoneController.text = profile.phone;
      _seeded = true;
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Personal Information'),
      ),
      body: state.isLoading && profile == null
          ? const Center(child: CircularProgressIndicator())
          : state.errorMessage != null && profile == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(state.errorMessage!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: () => ref.read(myProfileControllerProvider.notifier).load(),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () => ref.read(myProfileControllerProvider.notifier).load(),
                  child: ListView(
                    padding: const EdgeInsets.all(20),
                    children: <Widget>[
                      if (state.successMessage != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              color: Colors.green.shade50,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Row(
                                children: <Widget>[
                                  Icon(Icons.check_circle_outline, color: Colors.green.shade700),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(state.successMessage!)),
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
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Row(
                                children: <Widget>[
                                  Icon(Icons.error_outline, color: Colors.red.shade700),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(state.errorMessage!)),
                                ],
                              ),
                            ),
                          ),
                        ),
                      Text(
                        'Account',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      Text('Email: ${profile?.email ?? '—'}', style: Theme.of(context).textTheme.bodyMedium),
                      const SizedBox(height: 4),
                      Text(
                        'Country: ${profile == null || profile.country.isEmpty ? 'N/A' : profile.country}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
                      ),
                      Text(
                        'Currency: ${profile == null || profile.currency.isEmpty ? 'N/A' : profile.currency}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
                      ),
                      const SizedBox(height: 24),
                      Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: <Widget>[
                            Text(
                              'Edit details',
                              style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _displayNameController,
                              decoration: InputDecoration(
                                labelText: 'Display name',
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
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
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                              ),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _hidePassword,
                              decoration: InputDecoration(
                                labelText: 'Password (optional)',
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                                suffixIcon: IconButton(
                                  onPressed: () => setState(() => _hidePassword = !_hidePassword),
                                  icon: Icon(_hidePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
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
                            const SizedBox(height: 20),
                            FilledButton(
                              onPressed: state.isSaving
                                  ? null
                                  : () async {
                                      if (!(_formKey.currentState?.validate() ?? false)) {
                                        return;
                                      }
                                      await ref.read(myProfileControllerProvider.notifier).update(
                                            displayName: _displayNameController.text,
                                            phone: _phoneController.text,
                                            password: _passwordController.text,
                                          );
                                      _passwordController.clear();
                                    },
                              child: state.isSaving
                                  ? const SizedBox(
                                      height: 22,
                                      width: 22,
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    )
                                  : const Text('Save changes'),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}
