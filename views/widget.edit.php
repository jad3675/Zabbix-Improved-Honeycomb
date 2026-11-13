<?php declare(strict_types = 0);


/**
 * Exception honeycomb widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\ExceptionHoneycomb\Widget;
use Modules\ExceptionHoneycomb\Includes\WidgetForm;

$form = new CWidgetFormView($data);

$groupids_field = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$hostids_field = $data['templateid'] === null
	? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
		->setFilterPreselect([
			'id' => $groupids_field->getId(),
			'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
			'submit_as' => 'groupid'
		])
	: null;

$form
	->addField($groupids_field)
	->addField($hostids_field)
	->addField(array_key_exists('evaltype_host', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['evaltype_host'])
		: null
	)
	->addField(array_key_exists('host_tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['host_tags'])
		: null
	)
	->addField(
		(new CWidgetFieldPatternSelectItemView($data['fields']['items']))
			->setFilterPreselect($hostids_field !== null
				? [
					'id' => $hostids_field->getId(),
					'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
					'submit_as' => 'hostid'
				]
				: []
			)
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['evaltype_item'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['item_tags'])
	)
	->addItem(exchoneycomb_getConditionRow($form, $data['fields']))
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['cond_compare'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_nodata'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['maintenance'])
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['show']))->setColumns(2)
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addFieldsGroup(
				exchoneycomb_getLabelFieldsGroupView($form, _('Primary label'), [
					'label' =>					$data['fields']['primary_label'],
					'label_bold' =>				$data['fields']['primary_label_bold'],
					'label_color' =>			$data['fields']['primary_label_color'],
					'label_decimal_places' =>	$data['fields']['primary_label_decimal_places'],
					'label_size' =>				$data['fields']['primary_label_size'],
					'label_size_type' =>		$data['fields']['primary_label_size_type'],
					'label_type' =>				$data['fields']['primary_label_type'],
					'label_units' =>			$data['fields']['primary_label_units'],
					'label_units_pos' =>		$data['fields']['primary_label_units_pos'],
					'label_units_show' =>		$data['fields']['primary_label_units_show']
				])->addRowClass('fields-group-primary-label')
			)
			->addFieldsGroup(
				exchoneycomb_getLabelFieldsGroupView($form, _('Secondary label'), [
					'label' =>					$data['fields']['secondary_label'],
					'label_bold' =>				$data['fields']['secondary_label_bold'],
					'label_color' =>			$data['fields']['secondary_label_color'],
					'label_decimal_places' =>	$data['fields']['secondary_label_decimal_places'],
					'label_size' =>				$data['fields']['secondary_label_size'],
					'label_size_type' =>		$data['fields']['secondary_label_size_type'],
					'label_type' =>				$data['fields']['secondary_label_type'],
					'label_units' =>			$data['fields']['secondary_label_units'],
					'label_units_pos' =>		$data['fields']['secondary_label_units_pos'],
					'label_units_show' =>		$data['fields']['secondary_label_units_show']
				])->addRowClass('fields-group-secondary-label')
			)
			->addField(
				(new CWidgetFieldTextBoxView($data['fields']['name_regex']))
					->setFieldHint(
						makeHelpIcon(_('Applied to the item name. Use {MATCH} in a label to show the first capture group, for example "Interface (.+?)\\(" turns "Interface Eth1/49(uplink): Operational status" into "Eth1/49".'))
					)
					->setPlaceholder('Interface (.+?)\\(')
			)
			->addField(
				new CWidgetFieldSelectView($data['fields']['sort'])
			)
			->addField(
				new CWidgetFieldColorView($data['fields']['bg_color'])
			)
			->addField(
				(new CWidgetFieldTextBoxView($data['fields']['empty_text']))
					->setFieldHint(
						makeHelpIcon(_('Shown in a single cell when nothing matches. {COUNT} is the number of items checked.'))
					)
			)
			->addField(
				new CWidgetFieldColorView($data['fields']['empty_color'])
			)
			->addFieldsGroup(
				exchoneycomb_getThresholdFieldsGroupView($form, $data['fields'])
			)
	)
	->includeJsFile('widget.edit.js.php')
	->initFormJs('widget_form.init('.json_encode([
		'thresholds_colors' => Widget::DEFAULT_COLOR_PALETTE
	], JSON_THROW_ON_ERROR).');')
	->show();

function exchoneycomb_getLabelFieldsGroupView(CWidgetFormView $form, string $group_label, array $fields): CWidgetFieldsGroupView {
	$label_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['label_size']));
	$label_size_type_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['label_size_type']));
	$label_units_show_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['label_units_show']));
	$label_units_field = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['label_units']))->setAdaptiveWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

	return (new CWidgetFieldsGroupView($group_label))
		->addField(
			(new CWidgetFieldRadioButtonListView($fields['label_type']))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-label-type')
		)
		->addField(
			(new CWidgetFieldTextAreaView($fields['label']))
				->addLabelClass(ZBX_STYLE_FIELD_LABEL_ASTERISK)
				->setFieldHint(
					makeHelpIcon([
						_('Supported macros:'),
						(new CList([
							'{HOST.*}',
							'{ITEM.*}',
							'{INVENTORY.*}',
							_('User macros')
						]))->addClass(ZBX_STYLE_LIST_DASHED)
					])
				)
				->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addRowClass('js-label')
		)
		->addField(
			(new CWidgetFieldIntegerBoxView($fields['label_decimal_places']))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addRowClass('js-label-decimal-places')
		)
		->addItem([
			$label_size_field->getLabel(),
			new CFormField([
				($label_size_type_field->getView())
					->addClass('js-label-size-type')
					->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				($label_size_field->getView())
					->addClass('label-size')
					->addClass('js-label-size'),
				'%'
			])
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['label_bold'])
		)
		->addField(
			new CWidgetFieldColorView($fields['label_color'])
		)
		->addItem(
			(new CTag('hr'))->addClass('js-label-units-hr')
		)
		->addItem([
			(new CDiv([$label_units_show_field->getView(), $label_units_field->getLabel()]))
				->addClass('label-units-show')
				->addClass('js-label-units-show'),
			(new CFormField($label_units_field->getView()))->addClass('js-label-units')
		])
		->addField(
			(new CWidgetFieldSelectView($fields['label_units_pos']))->addRowClass('js-label-units-pos')
		);
}

function exchoneycomb_getThresholdFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$color_interpolation_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['interpolation']));

	return (new CWidgetFieldsGroupView(_('Thresholds')))
		->setFieldHint(
			makeWarningIcon(_('This setting applies only to numeric data.'))
		)
		->addItem([
			(new CDiv([$color_interpolation_field->getView(), $color_interpolation_field->getLabel()]))
				->addClass('form-row-interpolation')
		])
		->addField(
			(new CWidgetFieldThresholdsView($fields['thresholds']))->removeLabel()
		);
}

function exchoneycomb_getConditionRow(CWidgetFormView $form, array $fields): array {
	$operator_field = $form->registerField(new CWidgetFieldSelectView($fields['cond_operator']));
	$value_field = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['cond_value']))
			->setPlaceholder(_('empty shows every item'))
			->setAdaptiveWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setFieldHint(
				makeHelpIcon([
					_('Only items whose latest value satisfies this condition are shown.'),
					BR(),
					_('Numbers are compared numerically and accept suffixes (K, M, G, s, m, h, d).'),
					BR(),
					_('List operators take comma-separated values, for example "2, 7".'),
					BR(),
					_('Leave the value empty to show every item, like the stock Honeycomb.')
				])
			)
	);

	return [
		$value_field->getLabel(),
		(new CFormField([
			$operator_field->getView(),
			$value_field->getView()
		]))->addClass('condition-row')
	];
}
