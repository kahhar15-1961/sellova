import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../application/auth_session_controller.dart';
import '../application/auth_social_sign_in.dart';
import 'auth_form_widgets.dart';
import 'auth_ui_constants.dart';

class SignInGateScreen extends ConsumerStatefulWidget {
  const SignInGateScreen({super.key});

  @override
  ConsumerState<SignInGateScreen> createState() => _SignInGateScreenState();
}

class _SignInGateScreenState extends ConsumerState<SignInGateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _submitting = false;
  bool _obscurePassword = true;
  String? _errorMessage;

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  static final RegExp _emailRegex = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  bool _looksLikeEmail(String s) => _emailRegex.hasMatch(s.trim());

  Map<String, dynamic> _loginPayload(String password) {
    final id = _identifierController.text.trim();
    final body = <String, dynamic>{
      'password': password,
      'device_name': 'sellova-mobile',
    };
    if (_looksLikeEmail(id)) {
      body['email'] = id;
    } else {
      body['phone'] = id.replaceAll(RegExp(r'[\s-]'), '');
    }
    return body;
  }

  String? _validateIdentifier(String? value) {
    final input = (value ?? '').trim();
    if (input.isEmpty) {
      return 'Enter your email or phone number';
    }
    if (_looksLikeEmail(input)) {
      return null;
    }
    final digits = input.replaceAll(RegExp(r'[\s-]'), '');
    if (digits.length < 6 || digits.length > 32) {
      return 'Enter a valid phone number (6–32 digits)';
    }
    if (!RegExp(r'^[0-9+]+$').hasMatch(digits)) {
      return 'Phone may only include digits and +';
    }
    return null;
  }

  Future<void> _submit() async {
    if (_submitting) {
      return;
    }
    final valid = _formKey.currentState?.validate() ?? false;
    if (!valid) {
      return;
    }

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      await ref.read(authSessionControllerProvider.notifier).login(
            _loginPayload(_passwordController.text),
          );
    } catch (error) {
      final message = switch (error) {
        ApiException() => error.message.isNotEmpty ? error.message : 'Unable to sign in.',
        _ => 'Unable to sign in. Please try again.',
      };
      setState(() {
        _errorMessage = message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  Future<void> _google() async {
    setState(() {
      _submitting = true;
      _errorMessage = null;
    });
    try {
      final String? token = await AuthSocialSignIn.googleIdToken();
      if (!mounted) {
        return;
      }
      if (token == null || token.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Google sign-in was cancelled or is not configured on this device.')),
        );
        return;
      }
      await ref.read(authSessionControllerProvider.notifier).loginWithGoogleIdToken(idToken: token);
    } catch (error) {
      if (!mounted) {
        return;
      }
      final message = switch (error) {
        ApiException() => error.message.isNotEmpty ? error.message : 'Google sign-in failed.',
        _ => 'Google sign-in failed. Check server Google OAuth client IDs.',
      };
      setState(() {
        _errorMessage = message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  Future<void> _apple() async {
    final appleOk = !kIsWeb &&
        (defaultTargetPlatform == TargetPlatform.iOS || defaultTargetPlatform == TargetPlatform.macOS);
    if (!appleOk) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sign in with Apple is available on iOS and macOS.')),
      );
      return;
    }
    setState(() {
      _submitting = true;
      _errorMessage = null;
    });
    try {
      final (String? idToken, String? email) = await AuthSocialSignIn.appleCredentials();
      if (!mounted) {
        return;
      }
      if (idToken == null || idToken.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Apple sign-in was cancelled.')),
        );
        return;
      }
      await ref.read(authSessionControllerProvider.notifier).loginWithApple(
            identityToken: idToken,
            email: email,
          );
    } catch (error) {
      if (!mounted) {
        return;
      }
      final message = switch (error) {
        ApiException() => error.message.isNotEmpty ? error.message : 'Apple sign-in failed.',
        _ => 'Apple sign-in failed. Set APPLE_CLIENT_ID on the server to your Services ID.',
      };
      setState(() {
        _errorMessage = message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    const SizedBox(height: 12),
                    Text(
                      'Welcome back!',
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.4,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Login to continue',
                      style: theme.textTheme.bodyLarge?.copyWith(
                        color: const Color(0xFF64748B),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 32),
                    Text(
                      'Email or Phone',
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: const Color(0xFF334155),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _identifierController,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const <String>[AutofillHints.username, AutofillHints.email],
                      decoration: authInputDecoration(hint: 'example@gmail.com'),
                      validator: _validateIdentifier,
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: <Widget>[
                        Text(
                          'Password',
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: const Color(0xFF334155),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const Spacer(),
                        TextButton(
                          style: TextButton.styleFrom(
                            foregroundColor: kAuthAccentPurple,
                            padding: EdgeInsets.zero,
                            minimumSize: Size.zero,
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          ),
                          onPressed: () => context.push('/forgot-password'),
                          child: const Text('Forgot?'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      textInputAction: TextInputAction.done,
                      autofillHints: const <String>[AutofillHints.password],
                      onFieldSubmitted: (_) => _submit(),
                      decoration: authInputDecoration(
                        hint: '••••••••',
                        suffix: IconButton(
                          tooltip: _obscurePassword ? 'Show password' : 'Hide password',
                          onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                          icon: Icon(
                            _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                            color: const Color(0xFF64748B),
                          ),
                        ),
                      ),
                      validator: (value) {
                        if ((value ?? '').isEmpty) {
                          return 'Password is required';
                        }
                        return null;
                      },
                    ),
                    if (_errorMessage != null) ...<Widget>[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.red.shade50,
                          borderRadius: BorderRadius.circular(kAuthFieldRadius),
                          border: Border.all(color: Colors.red.shade100),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Icon(Icons.error_outline_rounded, color: Colors.red.shade700, size: 22),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: TextStyle(color: Colors.red.shade900, height: 1.35),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 28),
                    SizedBox(
                      height: 54,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: kAuthAccentPurple,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(kAuthFieldRadius)),
                          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                        ),
                        onPressed: _submitting ? null : _submit,
                        child: _submitting
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                              )
                            : const Text('Login'),
                      ),
                    ),
                    const SizedBox(height: 28),
                    Row(
                      children: <Widget>[
                        const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          child: Text(
                            'or continue with',
                            style: theme.textTheme.bodySmall?.copyWith(color: const Color(0xFF94A3B8)),
                          ),
                        ),
                        const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
                      ],
                    ),
                    const SizedBox(height: 20),
                    OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF0F172A),
                        side: const BorderSide(color: Color(0xFFE2E8F0)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(kAuthFieldRadius)),
                      ),
                      onPressed: _submitting ? null : _google,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: <Widget>[
                          Container(
                            width: 22,
                            height: 22,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: const Text(
                              'G',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 13,
                                color: Color(0xFF4285F4),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          const Text('Continue with Google', style: TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF0F172A),
                        side: const BorderSide(color: Color(0xFFE2E8F0)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(kAuthFieldRadius)),
                      ),
                      onPressed: _submitting ? null : _apple,
                      child: const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: <Widget>[
                          Icon(Icons.apple, size: 24, color: Color(0xFF0F172A)),
                          SizedBox(width: 10),
                          Text('Continue with Apple', style: TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 32),
                    Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 0,
                      children: <Widget>[
                        Text(
                          "Don't have an account? ",
                          style: theme.textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
                        ),
                        TextButton(
                          style: TextButton.styleFrom(
                            foregroundColor: kAuthAccentPurple,
                            padding: EdgeInsets.zero,
                            minimumSize: Size.zero,
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          ),
                          onPressed: () => context.push('/sign-up'),
                          child: const Text('Sign up', style: TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
