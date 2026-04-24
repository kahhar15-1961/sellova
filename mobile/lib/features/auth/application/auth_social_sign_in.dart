import 'package:flutter/foundation.dart' show kIsWeb, defaultTargetPlatform;
import 'package:flutter/material.dart' show TargetPlatform;
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

/// Optional compile-time IDs (see mobile README / CI env).
/// - [GOOGLE_SERVER_CLIENT_ID]: Web OAuth client ID (often required on Android for `idToken`).
/// - [GOOGLE_IOS_CLIENT_ID]: iOS OAuth client ID string (`*.apps.googleusercontent.com`).
const String _kGoogleServerClientId = String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID', defaultValue: '');
const String _kGoogleIosClientId = String.fromEnvironment('GOOGLE_IOS_CLIENT_ID', defaultValue: '');

/// Native Google / Apple sign-in helpers. Backend verifies tokens at `/api/v1/auth/google` and `/api/v1/auth/apple`.
final class AuthSocialSignIn {
  AuthSocialSignIn._();

  static bool get _isAppleDesktopOrMobile =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.iOS || defaultTargetPlatform == TargetPlatform.macOS);

  static Future<String?> googleIdToken() async {
    if (kIsWeb) {
      return null;
    }
    final GoogleSignIn signIn = GoogleSignIn(
      scopes: const <String>['email', 'openid'],
      serverClientId: _kGoogleServerClientId.isEmpty ? null : _kGoogleServerClientId,
      clientId: (_isAppleDesktopOrMobile && _kGoogleIosClientId.isNotEmpty) ? _kGoogleIosClientId : null,
    );
    await signIn.signOut();
    final GoogleSignInAccount? account = await signIn.signIn();
    if (account == null) {
      return null;
    }
    final GoogleSignInAuthentication auth = await account.authentication;
    return auth.idToken;
  }

  /// Returns `(identityToken, email)` where [email] may be non-null only on the user's first Apple authorization.
  static Future<(String?, String?)> appleCredentials() async {
    if (!_isAppleDesktopOrMobile) {
      return (null, null);
    }
    final AuthorizationCredentialAppleID credential = await SignInWithApple.getAppleIDCredential(
      scopes: <AppleIDAuthorizationScopes>[
        AppleIDAuthorizationScopes.email,
        AppleIDAuthorizationScopes.fullName,
      ],
    );
    return (credential.identityToken, credential.email);
  }
}
