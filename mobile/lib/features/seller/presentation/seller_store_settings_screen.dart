import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../application/seller_business_controller.dart';
import '../application/seller_failure.dart';
import '../data/seller_form_draft_store.dart';
import 'seller_ui.dart';

class SellerStoreSettingsScreen extends ConsumerStatefulWidget {
  const SellerStoreSettingsScreen({super.key});

  @override
  ConsumerState<SellerStoreSettingsScreen> createState() => _SellerStoreSettingsScreenState();
}

class _SellerStoreSettingsScreenState extends ConsumerState<SellerStoreSettingsScreen> {
  final TextEditingController _name = TextEditingController();
  final TextEditingController _desc = TextEditingController();
  bool _seededFromRemote = false;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  final Debouncer _draftDebouncer = Debouncer(duration: const Duration(milliseconds: 450));

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _name.dispose();
    _desc.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_listenersAttached) {
      return;
    }
    _listenersAttached = true;
    void persistDraft() {
      _draftDebouncer.run(() {
        ref.read(sellerFormDraftStoreProvider).saveStoreSettingsDraft(_name.text, _desc.text);
      });
    }

    _name.addListener(persistDraft);
    _desc.addListener(persistDraft);
  }

  Future<void> _save() async {
    await ref.read(sellerBusinessControllerProvider.notifier).saveStoreSettings(
          storeName: _name.text,
          storeDescription: _desc.text,
        );
    if (!mounted) return;
    final next = ref.read(sellerBusinessControllerProvider);
    final message = next.failure?.message ?? next.successMessage ?? 'Changes processed.';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _pickAndUploadLogo() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.image, allowMultiple: false);
    final path = result?.files.single.path;
    if (path == null) {
      return;
    }
    try {
      final url = await ref.read(sellerRepositoryProvider).uploadStoreMedia(path);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Logo uploaded. URL saved for next API sync: $url')));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(SellerFailure.from(e).message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final drafts = ref.read(sellerFormDraftStoreProvider);
    if (!_draftChecked) {
      _draftChecked = true;
      final draft = drafts.loadStoreSettingsDraft();
      if (draft != null) {
        final n = draft['store_name']?.toString() ?? '';
        final d = draft['store_description']?.toString() ?? '';
        if (n.trim().isNotEmpty || d.trim().isNotEmpty) {
          if (n.trim().isNotEmpty) {
            _name.text = n;
          }
          if (d.trim().isNotEmpty) {
            _desc.text = d;
          }
          _seededFromRemote = true;
        }
      }
    }

    final state = ref.watch(sellerBusinessControllerProvider);
    if (!_seededFromRemote) {
      _name.text = state.storeSettings.storeName;
      _desc.text = state.storeSettings.storeDescription;
      _seededFromRemote = true;
    }
    return Scaffold(
      backgroundColor: const Color(0xFFF5F5FA),
      appBar: AppBar(
        title: const Text('Store Settings'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFE5E7EB))),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Store Name', style: Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                TextField(controller: _name, decoration: const InputDecoration(border: OutlineInputBorder())),
                const SizedBox(height: 18),
                Text('Store Logo', style: Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Center(
                  child: Column(
                    children: <Widget>[
                      Container(
                        width: 88,
                        height: 88,
                        decoration: BoxDecoration(color: const Color(0xFFEDE9FE), borderRadius: BorderRadius.circular(14)),
                        child: const Icon(Icons.headphones_rounded, size: 40),
                      ),
                      TextButton(onPressed: state.isSaving ? null : _pickAndUploadLogo, child: const Text('Change')),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Text('Banner Image', style: Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Stack(
                  alignment: Alignment.centerRight,
                  children: <Widget>[
                    Container(
                      height: 120,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(14),
                        gradient: const LinearGradient(colors: <Color>[Color(0xFFE8E4FF), Color(0xFFD4CCFF)]),
                      ),
                      child: const Center(child: Icon(Icons.photo_size_select_actual_outlined, size: 40)),
                    ),
                    Positioned(
                      right: 10,
                      child: Row(
                        children: <Widget>[
                          CircleAvatar(backgroundColor: Colors.white, child: IconButton(icon: const Icon(Icons.photo_camera_outlined, color: kSellerAccent), onPressed: state.isSaving ? null : _pickAndUploadLogo)),
                          const SizedBox(width: 8),
                          FilledButton.tonal(onPressed: state.isSaving ? null : _pickAndUploadLogo, child: const Text('Change')),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Text('Store Description', style: Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                TextField(
                  controller: _desc,
                  maxLines: 4,
                  decoration: const InputDecoration(border: OutlineInputBorder(), alignLabelWithHint: true),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: state.isSaving ? null : _save,
            style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(52)),
            child: state.isSaving ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Save Changes'),
          ),
        ],
      ),
    );
  }
}
