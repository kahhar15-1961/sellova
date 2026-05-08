import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/debouncer.dart';
import '../application/seller_business_controller.dart';
import '../data/seller_form_draft_store.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerShippingSettingsScreen extends ConsumerStatefulWidget {
  const SellerShippingSettingsScreen({super.key});

  @override
  ConsumerState<SellerShippingSettingsScreen> createState() =>
      _SellerShippingSettingsScreenState();
}

class _SellerShippingSettingsScreenState
    extends ConsumerState<SellerShippingSettingsScreen> {
  final List<_ShippingMethodDraft> _rows = <_ShippingMethodDraft>[];
  final Debouncer _draftDebouncer =
      Debouncer(duration: const Duration(milliseconds: 450));
  bool _cod = true;
  bool _seeded = false;
  bool _draftChecked = false;

  @override
  void dispose() {
    _draftDebouncer.dispose();
    for (final row in _rows) {
      row.dispose();
    }
    super.dispose();
  }

  void _replaceRows(List<_ShippingMethodDraft> rows) {
    for (final row in _rows) {
      row.dispose();
    }
    _rows
      ..clear()
      ..addAll(rows);
    for (final row in _rows) {
      row.price.addListener(_persistDraft);
    }
  }

  void _persistDraft() {
    _draftDebouncer.run(() {
      ref.read(sellerFormDraftStoreProvider).saveShippingDraft(
        <String, dynamic>{
          'cashOnDeliveryEnabled': _cod,
          'shippingMethods': _rows
              .map((row) => <String, dynamic>{
                    'shippingMethodId': row.methodId,
                    'price': row.price.text,
                    'processingTimeLabel': row.processingTime,
                  })
              .toList(),
        },
      );
    });
  }

  List<_ShippingMethodDraft> _rowsFromSettings(
      SellerShippingSettings settings) {
    if (settings.shippingMethods.isNotEmpty) {
      return settings.shippingMethods.map((method) {
        return _ShippingMethodDraft(
          methodId: method.shippingMethodId,
          price: method.price == 0 ? '' : method.price.toStringAsFixed(0),
          processingTime: method.processingTimeLabel.isEmpty
              ? _defaultProcessing(settings, method.shippingMethodId)
              : method.processingTimeLabel,
        );
      }).toList();
    }
    return <_ShippingMethodDraft>[];
  }

  void _seedFromDraftOrSettings(SellerBusinessState state) {
    if (!_draftChecked && state.sellerAccessChecked) {
      _draftChecked = true;
      final draft = ref.read(sellerFormDraftStoreProvider).loadShippingDraft();
      if (!state.shippingSettings.isConfigured) {
        ref.read(sellerFormDraftStoreProvider).clearShippingDraft();
      } else if (draft != null) {
        final methods = draft['shippingMethods'];
        if (draft['cashOnDeliveryEnabled'] is bool) {
          _cod = draft['cashOnDeliveryEnabled']! as bool;
        }
        if (methods is List) {
          final rows = methods
              .whereType<Map>()
              .map((raw) => Map<String, dynamic>.from(raw))
              .map((raw) {
                final methodId = (raw['shippingMethodId'] as num?)?.toInt() ??
                    int.tryParse('${raw['shippingMethodId']}') ??
                    0;
                return _ShippingMethodDraft(
                  methodId: methodId,
                  price: (raw['price'] ?? '').toString(),
                  processingTime: (raw['processingTimeLabel'] ??
                          _defaultProcessing(state.shippingSettings, methodId))
                      .toString(),
                );
              })
              .where((row) => row.methodId > 0)
              .toList();
          _replaceRows(rows);
          _seeded = true;
        }
      }
    }

    if (!_seeded && state.sellerAccessChecked) {
      final settings = state.shippingSettings;
      _cod = settings.cashOnDeliveryEnabled;
      _replaceRows(_rowsFromSettings(settings));
      _seeded = true;
    }
  }

  String _defaultProcessing(SellerShippingSettings settings, int methodId) {
    final method = _optionFor(settings, methodId);
    if ((method?.processingTimeLabel ?? '').trim().isNotEmpty) {
      return method!.processingTimeLabel;
    }
    if (settings.processingTimeOptions.isNotEmpty) {
      return settings.processingTimeOptions.first;
    }
    return '1-2 Business Days';
  }

  SellerShippingMethodOption? _optionFor(
      SellerShippingSettings settings, int methodId) {
    for (final option in settings.availableMethods) {
      if (option.id == methodId) {
        return option;
      }
    }
    return null;
  }

  List<SellerShippingMethodOption> _optionsForRow(
    SellerShippingSettings settings,
    _ShippingMethodDraft row,
  ) {
    final selected = _rows
        .where((candidate) => !identical(candidate, row))
        .map((candidate) => candidate.methodId)
        .toSet();
    return settings.availableMethods
        .where((option) =>
            option.id == row.methodId || !selected.contains(option.id))
        .toList();
  }

  void _addMethod(SellerShippingSettings settings) {
    final used = _rows.map((row) => row.methodId).toSet();
    SellerShippingMethodOption? option;
    for (final item in settings.availableMethods) {
      if (!used.contains(item.id)) {
        option = item;
        break;
      }
    }
    if (option == null) {
      return;
    }
    final selectedOption = option;
    setState(() {
      final row = _ShippingMethodDraft(
        methodId: selectedOption.id,
        price: selectedOption.suggestedFee == 0
            ? ''
            : selectedOption.suggestedFee.toStringAsFixed(0),
        processingTime: selectedOption.processingTimeLabel.isEmpty
            ? _defaultProcessing(settings, selectedOption.id)
            : selectedOption.processingTimeLabel,
      );
      row.price.addListener(_persistDraft);
      _rows.add(row);
    });
    _persistDraft();
  }

  Future<void> _save(SellerShippingSettings settings) async {
    if (_rows.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Please add at least one shipping method.')));
      return;
    }

    final selections = <SellerShippingMethodSelection>[];
    for (var index = 0; index < _rows.length; index += 1) {
      final row = _rows[index];
      final option = _optionFor(settings, row.methodId);
      final price = double.tryParse(row.price.text.trim());
      if (option == null || price == null || price < 0) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Please choose a method and enter valid prices.')));
        return;
      }
      selections.add(SellerShippingMethodSelection(
        shippingMethodId: option.id,
        methodCode: option.code,
        methodName: option.name,
        suggestedFee: option.suggestedFee,
        price: price,
        processingTimeLabel: row.processingTime,
        isEnabled: true,
        sortOrder: (index + 1) * 10,
      ));
    }

    final first = selections.isNotEmpty ? selections.first : null;
    final second = selections.length > 1 ? selections[1] : null;
    await ref
        .read(sellerBusinessControllerProvider.notifier)
        .saveShippingSettings(
          SellerShippingSettings(
            insideDhakaLabel: first?.methodName ?? '',
            insideDhakaFee: first?.price ?? 0,
            outsideDhakaLabel: second?.methodName ?? '',
            outsideDhakaFee: second?.price ?? 0,
            cashOnDeliveryEnabled: _cod,
            processingTimeLabel:
                first?.processingTimeLabel ?? '1-2 Business Days',
            shippingMethods: selections,
            availableMethods: settings.availableMethods,
            processingTimeOptions: settings.processingTimeOptions,
            isConfigured: true,
          ),
        );
    if (!mounted) return;
    final next = ref.read(sellerBusinessControllerProvider);
    final message =
        next.failure?.message ?? next.successMessage ?? 'Changes processed.';
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerBusinessControllerProvider);
    final settings = state.shippingSettings;
    _seedFromDraftOrSettings(state);

    final canAdd = _rows.length < settings.availableMethods.length;
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: const Text('Shipping Settings'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: <Widget>[
          _sectionLabel('Shipping methods'),
          const SizedBox(height: 8),
          if (settings.availableMethods.isEmpty)
            _emptyCatalog()
          else ...<Widget>[
            for (final row in _rows) ...<Widget>[
              _methodCard(settings, row),
              const SizedBox(height: 12),
            ],
            OutlinedButton.icon(
              onPressed: canAdd ? () => _addMethod(settings) : null,
              icon: const Icon(Icons.add_rounded),
              label: Text(canAdd ? 'Add shipping method' : 'All methods added'),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                foregroundColor: kSellerAccent,
              ),
            ),
          ],
          const SizedBox(height: 22),
          _sectionLabel('Cash on Delivery'),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border.all(color: const Color(0xFFE5EAF3)),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: <Widget>[
                const Expanded(
                  child: Text(
                    'Enable COD',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                Switch(
                  value: _cod,
                  activeThumbColor: const Color(0xFF22C55E),
                  onChanged: (value) {
                    setState(() => _cod = value);
                    _persistDraft();
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 28),
          FilledButton(
            onPressed: state.isSaving || settings.availableMethods.isEmpty
                ? null
                : () => _save(settings),
            style: FilledButton.styleFrom(
              backgroundColor: kSellerAccent,
              minimumSize: const Size.fromHeight(54),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16)),
            ),
            child: state.isSaving
                ? const SizedBox(
                    height: 22,
                    width: 22,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                : const Text('Save Changes'),
          ),
        ],
      ),
    );
  }

  Widget _methodCard(
      SellerShippingSettings settings, _ShippingMethodDraft row) {
    final option = _optionFor(settings, row.methodId);
    final processingOptions = settings.processingTimeOptions.isEmpty
        ? <String>[
            'Instant',
            'Same day',
            '1-2 Business Days',
            '3-5 Business Days'
          ]
        : settings.processingTimeOptions;
    final processValue = processingOptions.contains(row.processingTime)
        ? row.processingTime
        : processingOptions.first;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE1E8F2)),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 20,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final stackFields = constraints.maxWidth < 330;
          final priceField = TextField(
            controller: row.price,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: <TextInputFormatter>[
              FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
            ],
            decoration: const InputDecoration(
              labelText: 'Seller price',
              prefixText: '৳ ',
              border: OutlineInputBorder(),
            ),
          );
          final processingField = DropdownButtonFormField<String>(
            initialValue: processValue,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Processing time',
              border: OutlineInputBorder(),
            ),
            items: processingOptions
                .map(
                  (item) => DropdownMenuItem<String>(
                    value: item,
                    child: Text(item, overflow: TextOverflow.ellipsis),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value == null) {
                return;
              }
              setState(() => row.processingTime = value);
              _persistDraft();
            },
          );

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: DropdownButtonFormField<int>(
                      initialValue: option?.id,
                      isExpanded: true,
                      decoration: const InputDecoration(
                        labelText: 'Method name',
                        border: OutlineInputBorder(),
                      ),
                      items: _optionsForRow(settings, row)
                          .map(
                            (item) => DropdownMenuItem<int>(
                              value: item.id,
                              child: Text(item.name,
                                  overflow: TextOverflow.ellipsis),
                            ),
                          )
                          .toList(),
                      onChanged: (value) {
                        if (value == null) {
                          return;
                        }
                        final next = _optionFor(settings, value);
                        setState(() {
                          row.methodId = value;
                          if (next != null) {
                            row.price.text = next.suggestedFee == 0
                                ? ''
                                : next.suggestedFee.toStringAsFixed(0);
                            row.processingTime =
                                next.processingTimeLabel.isEmpty
                                    ? _defaultProcessing(settings, value)
                                    : next.processingTimeLabel;
                          }
                        });
                        _persistDraft();
                      },
                    ),
                  ),
                  const SizedBox(width: 4),
                  SizedBox(
                    height: 48,
                    width: 40,
                    child: IconButton(
                      tooltip: 'Remove method',
                      padding: EdgeInsets.zero,
                      onPressed: () {
                        setState(() {
                          _rows.remove(row);
                          row.dispose();
                        });
                        _persistDraft();
                      },
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              if (stackFields) ...<Widget>[
                priceField,
                const SizedBox(height: 10),
                processingField,
              ] else
                Row(
                  children: <Widget>[
                    Expanded(child: priceField),
                    const SizedBox(width: 10),
                    Expanded(child: processingField),
                  ],
                ),
              if (option != null) ...<Widget>[
                const SizedBox(height: 10),
                Text(
                  'Admin suggested ৳${option.suggestedFee.toStringAsFixed(0)} · ${option.processingTimeLabel}',
                  style: const TextStyle(
                    color: kSellerMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }

  Widget _emptyCatalog() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5EAF3)),
        borderRadius: BorderRadius.circular(16),
      ),
      child: const Text(
        'No active shipping methods are available. Ask an admin to add delivery zones first.',
        style: TextStyle(color: kSellerMuted, fontWeight: FontWeight.w600),
      ),
    );
  }

  Widget _sectionLabel(String text) {
    return Text(
      text,
      style: const TextStyle(
        color: kSellerMuted,
        fontWeight: FontWeight.w700,
        fontSize: 13,
      ),
    );
  }
}

class _ShippingMethodDraft {
  _ShippingMethodDraft({
    required this.methodId,
    required String price,
    required this.processingTime,
  }) : price = TextEditingController(text: price);

  int methodId;
  final TextEditingController price;
  String processingTime;

  void dispose() {
    price.dispose();
  }
}
