import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/providers/repository_providers.dart';
import '../../categories/application/category_list_provider.dart';
import '../../categories/data/category_repository.dart';
import '../application/seller_demo_controller.dart';
import '../application/seller_failure.dart';
import '../application/seller_product_controller.dart';
import 'seller_feedback_widgets.dart';
import 'seller_product_media_picker.dart';

class SellerAddProductScreen extends ConsumerStatefulWidget {
  const SellerAddProductScreen({super.key, required this.productType});
  final String productType;

  @override
  ConsumerState<SellerAddProductScreen> createState() =>
      _SellerAddProductScreenState();
}

class _SellerAddProductScreenState
    extends ConsumerState<SellerAddProductScreen> {
  final _name = TextEditingController();
  final _price = TextEditingController();
  final _category = TextEditingController();
  final _stock = TextEditingController();
  final _description = TextEditingController();
  final _brand = TextEditingController();
  final _warranty = TextEditingController();
  final _location = TextEditingController();
  final _tags = TextEditingController();
  final _digitalKind = TextEditingController();
  final _subscriptionDuration = TextEditingController();
  final _platform = TextEditingController();
  final _accountRegion = TextEditingController();
  final _deliveryNote = TextEditingController();
  String _condition = 'New';
  String _warrantyStatus = 'No warranty';
  String _accessType = 'Account credentials';
  int? _selectedRootCategoryId;
  int? _selectedCategoryId;
  final List<ProductImageSelection> _images = <ProductImageSelection>[];
  bool _uploadingImage = false;
  bool _instantDelivery = false;

  @override
  void dispose() {
    _name.dispose();
    _price.dispose();
    _category.dispose();
    _stock.dispose();
    _description.dispose();
    _brand.dispose();
    _warranty.dispose();
    _location.dispose();
    _tags.dispose();
    _digitalKind.dispose();
    _subscriptionDuration.dispose();
    _platform.dispose();
    _accountRegion.dispose();
    _deliveryNote.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    final categoriesAsync = ref.watch(categoryListProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Add Product')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          ProductImageGalleryPicker(
            images: _images,
            uploading: _uploadingImage,
            onAdd: _showImagePickerSheet,
            onRemove: (index) => setState(() => _images.removeAt(index)),
            onMakeCover: (index) => setState(() {
              final image = _images.removeAt(index);
              _images.insert(0, image);
            }),
          ),
          const SizedBox(height: 12),
          if (error != null) ...<Widget>[
            SellerInlineFeedback(message: error),
            const SizedBox(height: 10),
          ],
          _f('Product Name', _name),
          _f('Price (৳)', _price, keyboard: TextInputType.number),
          _categoryField(categoriesAsync),
          if (_isPhysical)
            _f('Stock Quantity', _stock, keyboard: TextInputType.number),
          _attributeFields(),
          _f('Description', _description, lines: 4),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
                    final name = _name.text.trim();
                    final price = double.tryParse(_price.text.trim());
                    final stock =
                        _isPhysical ? int.tryParse(_stock.text.trim()) : 1;
                    if (name.isEmpty ||
                        price == null ||
                        stock == null ||
                        _category.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                          content:
                              Text('Please fill required fields correctly.')));
                      return;
                    }
                    await ref
                        .read(sellerProductsProvider.notifier)
                        .createProduct(
                          name: name,
                          price: price,
                          stock: stock,
                          category: _category.text.trim(),
                          description: _description.text.trim(),
                          productType: widget.productType,
                          isInstantDelivery: _isDigital && _instantDelivery,
                          imageUrl: _images.isEmpty
                              ? null
                              : _images.first.storagePath,
                          imageUrls: _images
                              .map((image) => image.storagePath)
                              .toList(),
                          attributes: _productAttributes(),
                        );
                    if (context.mounted) {
                      final error = ref.read(sellerErrorProvider);
                      if (error != null && error.trim().isNotEmpty) {
                        ScaffoldMessenger.of(context)
                            .showSnackBar(SnackBar(content: Text(error)));
                        return;
                      }
                      showSellerSuccessToast(
                          context, 'Product saved successfully.');
                      context.go('/seller/products');
                    }
                  },
            child: const Text('Save Product'),
          ),
          if (busy)
            const Padding(
                padding: EdgeInsets.only(top: 10),
                child: LinearProgressIndicator()),
        ],
      ),
    );
  }

  bool get _isPhysical => widget.productType == 'physical';
  bool get _isDigital => widget.productType == 'digital';

  Map<String, dynamic> _productAttributes() {
    final tags = _tags.text
        .split(',')
        .map((tag) => tag.trim())
        .where((tag) => tag.isNotEmpty)
        .toList();
    final data = <String, dynamic>{
      if (_brand.text.trim().isNotEmpty) 'brand': _brand.text.trim(),
      'warranty_status': _warrantyStatus,
      if (_warranty.text.trim().isNotEmpty)
        'warranty_period': _warranty.text.trim(),
      if (tags.isNotEmpty) 'tags': tags,
    };
    if (_isPhysical) {
      data.addAll(<String, dynamic>{
        'condition': _condition,
        if (_location.text.trim().isNotEmpty)
          'product_location': _location.text.trim(),
      });
    } else if (_isDigital) {
      data.addAll(<String, dynamic>{
        'condition': 'Digital',
        'delivery_mode': _instantDelivery ? 'instant' : 'digital_delivery',
        'is_instant_delivery': _instantDelivery,
        'access_type': _accessType,
        if (_digitalKind.text.trim().isNotEmpty)
          'digital_product_kind': _digitalKind.text.trim(),
        if (_subscriptionDuration.text.trim().isNotEmpty)
          'subscription_duration': _subscriptionDuration.text.trim(),
        if (_platform.text.trim().isNotEmpty) 'platform': _platform.text.trim(),
        if (_accountRegion.text.trim().isNotEmpty)
          'account_region': _accountRegion.text.trim(),
        if (_deliveryNote.text.trim().isNotEmpty)
          'delivery_note': _deliveryNote.text.trim(),
      });
    } else {
      data.addAll(<String, dynamic>{
        'condition': 'Service',
        'delivery_mode': 'manual',
        if (_deliveryNote.text.trim().isNotEmpty)
          'delivery_note': _deliveryNote.text.trim(),
      });
    }
    return data;
  }

  Widget _attributeFields() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        const Padding(
          padding: EdgeInsets.only(bottom: 8),
          child: Text('Product details',
              style: TextStyle(fontWeight: FontWeight.w800)),
        ),
        _f('Brand / Publisher', _brand),
        if (_isPhysical) ...<Widget>[
          _selectField(
            label: 'Condition',
            value: _condition,
            options: const <String>['New', 'Like new', 'Good', 'Fair', 'Used'],
            onChanged: (value) => setState(() => _condition = value),
          ),
          _f('Product Location', _location),
        ] else if (_isDigital) ...<Widget>[
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: const Text('Instant delivery'),
            subtitle: const Text(
              'Enable automatic digital handoff for this product.',
            ),
            value: _instantDelivery,
            onChanged: (value) => setState(() => _instantDelivery = value),
          ),
          _f('Digital product type', _digitalKind,
              hint: 'Game account, ChatGPT subscription, license key'),
          _selectField(
            label: 'Access type',
            value: _accessType,
            options: const <String>[
              'Account credentials',
              'Subscription package',
              'License key',
              'Download link',
              'Manual handover'
            ],
            onChanged: (value) => setState(() => _accessType = value),
          ),
          _f('Subscription / validity', _subscriptionDuration,
              hint: '30 days, lifetime, 1 year'),
          _f('Platform', _platform, hint: 'Steam, OpenAI, Netflix, Microsoft'),
          _f('Account region', _accountRegion, hint: 'BD, US, Global'),
          _f(
            _instantDelivery ? 'Instant delivery note' : 'Delivery note',
            _deliveryNote,
            lines: 2,
          ),
        ] else ...<Widget>[
          _f('Service scope', _digitalKind,
              hint: 'Consultation, setup, support, custom work'),
          _f('Delivery note', _deliveryNote, lines: 2),
        ],
        _selectField(
          label: 'Warranty status',
          value: _warrantyStatus,
          options: const <String>[
            'No warranty',
            'Seller warranty',
            'Brand warranty',
            'Service warranty'
          ],
          onChanged: (value) => setState(() => _warrantyStatus = value),
        ),
        _f('Warranty period', _warranty, hint: '7 days, 6 months, 1 year'),
        _f('Tags', _tags, hint: 'gaming, premium, official'),
      ],
    );
  }

  Widget _selectField({
    required String label,
    required String value,
    required List<String> options,
    required ValueChanged<String> onChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<String>(
        initialValue: value,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
        ),
        items: options
            .map((option) =>
                DropdownMenuItem<String>(value: option, child: Text(option)))
            .toList(),
        onChanged: (value) {
          if (value != null) {
            onChanged(value);
          }
        },
      ),
    );
  }

  Widget _categoryField(AsyncValue<List<CategoryDto>> categoriesAsync) {
    final categories = categoriesAsync.valueOrNull ?? const <CategoryDto>[];
    final rootCategories =
        categories.where((category) => category.parentId == null).toList();
    final subcategories = categories
        .where((category) => category.parentId == _selectedRootCategoryId)
        .toList();
    final selectedSubcategoryId =
        subcategories.any((category) => category.id == _selectedCategoryId)
            ? _selectedCategoryId
            : null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text('Category'),
          const SizedBox(height: 6),
          DropdownButtonFormField<int>(
            initialValue: _selectedRootCategoryId,
            isExpanded: true,
            decoration: const InputDecoration(border: OutlineInputBorder()),
            hint: Text(categoriesAsync.isLoading
                ? 'Loading categories...'
                : 'Select category'),
            items: rootCategories
                .where((category) => category.id != null)
                .map(
                  (category) => DropdownMenuItem<int>(
                    value: category.id,
                    child: Text(category.name),
                  ),
                )
                .toList(),
            onChanged: rootCategories.isEmpty
                ? null
                : (value) {
                    final selected = rootCategories.firstWhere(
                      (category) => category.id == value,
                    );
                    final childOptions = categories
                        .where((category) => category.parentId == selected.id)
                        .toList();
                    setState(() {
                      _selectedRootCategoryId = value;
                      if (childOptions.isEmpty) {
                        _selectedCategoryId = value;
                        _category.text = selected.name;
                      } else {
                        _selectedCategoryId = null;
                        _category.clear();
                      }
                    });
                  },
          ),
          if (_selectedRootCategoryId != null && subcategories.isNotEmpty) ...[
            const SizedBox(height: 10),
            DropdownButtonFormField<int>(
              initialValue: selectedSubcategoryId,
              isExpanded: true,
              decoration: const InputDecoration(border: OutlineInputBorder()),
              hint: const Text('Select subcategory'),
              items: subcategories
                  .where((category) => category.id != null)
                  .map(
                    (category) => DropdownMenuItem<int>(
                      value: category.id,
                      child: Text(category.name),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                final selected = subcategories.firstWhere(
                  (category) => category.id == value,
                );
                setState(() {
                  _selectedCategoryId = value;
                  _category.text = selected.name;
                });
              },
            ),
          ],
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => _showCategoryRequestDialog(categories),
              icon: const Icon(Icons.add_circle_outline_rounded),
              label: const Text('Request new category'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _showCategoryRequestDialog(List<CategoryDto> categories) async {
    final name = TextEditingController();
    final reason = TextEditingController();
    final example = TextEditingController(text: _name.text.trim());
    int? parentId = _selectedCategoryId ?? _selectedRootCategoryId;
    try {
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: const Text('Request category'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  TextField(
                    controller: name,
                    decoration: const InputDecoration(
                      labelText: 'Category name',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    initialValue: parentId,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Parent category',
                      border: OutlineInputBorder(),
                    ),
                    items: <DropdownMenuItem<int>>[
                      const DropdownMenuItem<int>(
                        value: null,
                        child: Text('Root category'),
                      ),
                      ...categories
                          .where((category) => category.id != null)
                          .map((category) => DropdownMenuItem<int>(
                                value: category.id,
                                child: Text(category.name),
                              )),
                    ],
                    onChanged: (value) =>
                        setDialogState(() => parentId = value),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: example,
                    decoration: const InputDecoration(
                      labelText: 'Example product',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: reason,
                    minLines: 3,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Reason',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () async {
                  final requestedName = name.text.trim();
                  if (requestedName.isEmpty) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                        content: Text('Category name is required.')));
                    return;
                  }
                  await ref
                      .read(sellerRepositoryProvider)
                      .submitCategoryRequest(
                        name: requestedName,
                        parentId: parentId,
                        reason: reason.text,
                        exampleProductName: example.text,
                      );
                  if (!context.mounted) return;
                  Navigator.of(dialogContext).pop();
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                      content: Text(
                          'Category request submitted for admin review.')));
                },
                child: const Text('Submit request'),
              ),
            ],
          ),
        ),
      );
    } finally {
      name.dispose();
      reason.dispose();
      example.dispose();
    }
  }

  Widget _f(String label, TextEditingController c,
      {TextInputType keyboard = TextInputType.text,
      int lines = 1,
      String? hint}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(label),
            const SizedBox(height: 6),
            TextField(
                controller: c,
                keyboardType: keyboard,
                maxLines: lines,
                decoration: InputDecoration(
                  hintText: hint,
                  border: const OutlineInputBorder(),
                )),
          ]),
    );
  }

  Future<void> _showImagePickerSheet() async {
    if (_uploadingImage) {
      return;
    }
    if (_images.length >= 8) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('You can add up to 8 product images.')),
      );
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              ListTile(
                leading: const Icon(Icons.photo_camera_outlined),
                title: const Text('Take photo'),
                onTap: () {
                  Navigator.of(context).pop();
                  _pickImage(_ProductImageSource.camera);
                },
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Choose from gallery'),
                onTap: () {
                  Navigator.of(context).pop();
                  _pickImage(_ProductImageSource.gallery);
                },
              ),
              ListTile(
                leading: const Icon(Icons.upload_file_outlined),
                title: const Text('Choose file'),
                onTap: () {
                  Navigator.of(context).pop();
                  _pickImage(_ProductImageSource.file);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickImage(_ProductImageSource source) async {
    final availableSlots = 8 - _images.length;
    if (availableSlots <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('You can add up to 8 product images.')),
      );
      return;
    }
    final selections = <({String? path, String fileName, Uint8List? bytes})>[];

    try {
      if (source == _ProductImageSource.camera) {
        final picked = await ImagePicker().pickImage(
          source: ImageSource.camera,
          imageQuality: 88,
          preferredCameraDevice: CameraDevice.rear,
        );
        if (picked == null) {
          return;
        }
        selections.add((
          path: picked.path,
          fileName: picked.name,
          bytes: await picked.readAsBytes(),
        ));
      } else if (source == _ProductImageSource.gallery) {
        final picked = await ImagePicker().pickMultiImage(imageQuality: 88);
        if (picked.isEmpty) {
          return;
        }
        for (final image in picked) {
          selections.add((
            path: image.path,
            fileName: image.name,
            bytes: await image.readAsBytes(),
          ));
        }
      } else {
        final result = await FilePicker.platform.pickFiles(
          type: FileType.image,
          allowMultiple: true,
          withData: true,
        );
        if (result == null || result.files.isEmpty) {
          return;
        }
        for (final file in result.files) {
          selections
              .add((path: file.path, fileName: file.name, bytes: file.bytes));
        }
      }

      if (selections.isEmpty) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not read the selected images.')),
        );
        return;
      }

      setState(() => _uploadingImage = true);
      final uploadedImages = <ProductImageSelection>[];
      for (final selection in selections.take(availableSlots)) {
        final uploaded =
            await ref.read(sellerRepositoryProvider).uploadSellerMedia(
                  selection.path ?? selection.fileName,
                  purpose: 'product_image',
                  bytes: selection.bytes,
                  fileName: selection.fileName,
                );
        uploadedImages.add(ProductImageSelection(
          storagePath: uploaded.storagePath,
          fileName: uploaded.originalName.isNotEmpty
              ? uploaded.originalName
              : selection.fileName,
          localPath: selection.path ?? '',
          bytes: selection.bytes,
        ));
      }
      if (!mounted) return;
      setState(() {
        _images.addAll(uploadedImages);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            uploadedImages.length == 1
                ? 'Product image uploaded.'
                : '${uploadedImages.length} product images uploaded.',
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(SellerFailure.from(e).message)));
    } finally {
      if (mounted) {
        setState(() => _uploadingImage = false);
      }
    }
  }
}

enum _ProductImageSource { camera, gallery, file }
