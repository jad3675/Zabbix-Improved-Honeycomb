<?php declare(strict_types = 0);
namespace Modules\ExceptionHoneycomb\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm,
	Fields\CWidgetFieldCheckBox,
	Fields\CWidgetFieldCheckBoxList,
	Fields\CWidgetFieldColor,
	Fields\CWidgetFieldIntegerBox,
	Fields\CWidgetFieldMultiSelectGroup,
	Fields\CWidgetFieldMultiSelectHost,
	Fields\CWidgetFieldPatternSelectItem,
	Fields\CWidgetFieldRadioButtonList,
	Fields\CWidgetFieldSelect,
	Fields\CWidgetFieldTags,
	Fields\CWidgetFieldTextArea,
	Fields\CWidgetFieldTextBox,
	Fields\CWidgetFieldThresholds
};

use CWidgetsData;

/**
 * Exception honeycomb widget form.
 */
class WidgetForm extends CWidgetForm {

	public const SHOW_PRIMARY_LABEL = 1;
	public const SHOW_SECONDARY_LABEL = 2;

	public const LABEL_TYPE_TEXT = 0;
	public const LABEL_TYPE_VALUE = 1;

	public const LABEL_SIZE_AUTO = 0;
	public const LABEL_SIZE_CUSTOM = 1;

	public const UNITS_POSITION_BEFORE = 0;
	public const UNITS_POSITION_AFTER = 1;

	// Condition operators.
	public const OP_EQUAL = 0;
	public const OP_NOT_EQUAL = 1;
	public const OP_LESS = 2;
	public const OP_LESS_EQUAL = 3;
	public const OP_GREATER = 4;
	public const OP_GREATER_EQUAL = 5;
	public const OP_IN = 6;
	public const OP_NOT_IN = 7;
	public const OP_REGEX = 8;
	public const OP_NOT_REGEX = 9;

	// What the condition is compared against.
	public const COMPARE_RAW = 0;
	public const COMPARE_MAPPED = 1;

	// Cell order.
	public const SORT_NAME = 0;
	public const SORT_VALUE_ASC = 1;
	public const SORT_VALUE_DESC = 2;

	public static function getOperators(): array {
		return [
			self::OP_EQUAL => '=',
			self::OP_NOT_EQUAL => '<>',
			self::OP_LESS => '<',
			self::OP_LESS_EQUAL => '<=',
			self::OP_GREATER => '>',
			self::OP_GREATER_EQUAL => '>=',
			self::OP_IN => _('in list'),
			self::OP_NOT_IN => _('not in list'),
			self::OP_REGEX => _('matches regex'),
			self::OP_NOT_REGEX => _('does not match regex')
		];
	}

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				(new CWidgetFieldMultiSelectHost('hostids', _('Hosts')))
					->setDefault($this->isTemplateDashboard()
						? [
							CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
								CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_HOST_IDS
							)
						]
						: []
					)
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype_host', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('host_tags')
			)
			->addField(
				(new CWidgetFieldPatternSelectItem('items', _('Item patterns')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype_item', _('Item tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField(
				new CWidgetFieldTags('item_tags')
			)
			->addField(
				(new CWidgetFieldSelect('cond_operator', _('Operator'), self::getOperators()))
					->setDefault(self::OP_NOT_EQUAL)
			)
			->addField(
				new CWidgetFieldTextBox('cond_value', _('Show when value'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('cond_compare', _('Compare'), [
					self::COMPARE_RAW => _('Raw value'),
					self::COMPARE_MAPPED => _('Mapped value')
				]))->setDefault(self::COMPARE_RAW)
			)
			->addField(
				new CWidgetFieldCheckBox('show_nodata', _('Show items without data'))
			)
			->addField(
				new CWidgetFieldCheckBox('maintenance',
					$this->isTemplateDashboard() ? _('Show data in maintenance') : _('Show hosts in maintenance')
				)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					self::SHOW_PRIMARY_LABEL => _('Primary label'),
					self::SHOW_SECONDARY_LABEL => _('Secondary label')
				]))
					->setDefault([self::SHOW_PRIMARY_LABEL, self::SHOW_SECONDARY_LABEL])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('primary_label_type', _('Type'), [
					self::LABEL_TYPE_TEXT => _('Text'),
					self::LABEL_TYPE_VALUE => _('Value')
				]))->setDefault(self::LABEL_TYPE_TEXT)
			)
			->addField(
				(new CWidgetFieldIntegerBox('primary_label_decimal_places', _('Decimal places'), 0, 6))
					->setDefault(2)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
					->prefixLabel(_('Primary label'))
			)
			->addField(
				(new CWidgetFieldTextArea('primary_label', _('Text')))
					->setDefault('{HOST.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->prefixLabel(_('Primary label'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('primary_label_size_type', null, [
					self::LABEL_SIZE_AUTO => _('Auto'),
					self::LABEL_SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::LABEL_SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('primary_label_size', _('Size'), 1, 100))
					->setDefault(20)
					->prefixLabel(_('Primary label'))
			)
			->addField(
				new CWidgetFieldCheckBox('primary_label_bold', _('Bold'))
			)
			->addField(
				(new CWidgetFieldColor('primary_label_color', _('Color')))
					->prefixLabel(_('Primary label'))
					->allowInherited()
			)
			->addField(
				(new CWidgetFieldCheckBox('primary_label_units_show'))->setDefault(1)
			)
			->addField(
				new CWidgetFieldTextBox('primary_label_units', _('Units'))
			)
			->addField(
				(new CWidgetFieldSelect('primary_label_units_pos', _('Position'), [
					self::UNITS_POSITION_BEFORE => _('Before value'),
					self::UNITS_POSITION_AFTER => _('After value')
				]))->setDefault(self::UNITS_POSITION_AFTER)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('secondary_label_type', _('Type'), [
					self::LABEL_TYPE_TEXT => _('Text'),
					self::LABEL_TYPE_VALUE => _('Value')
				]))->setDefault(self::LABEL_TYPE_VALUE)
			)
			->addField(
				(new CWidgetFieldIntegerBox('secondary_label_decimal_places', _('Decimal places'), 0, 6))
					->setDefault(2)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
					->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldTextArea('secondary_label', _('Text')))
					->setDefault('{{ITEM.LASTVALUE}.fmtnum(2)}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('secondary_label_size_type', null, [
					self::LABEL_SIZE_AUTO => _('Auto'),
					self::LABEL_SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::LABEL_SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('secondary_label_size', _('Size'), 1, 100))
					->setDefault(30)
					->prefixLabel(_('Secondary label'))
			)
			->addField(
				(new CWidgetFieldCheckBox('secondary_label_bold', _('Bold')))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldColor('secondary_label_color', _('Color')))
					->prefixLabel(_('Secondary label'))
					->allowInherited()
			)
			->addField(
				(new CWidgetFieldCheckBox('secondary_label_units_show'))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldTextBox('secondary_label_units', _('Units')))
			)
			->addField(
				(new CWidgetFieldSelect('secondary_label_units_pos', _('Position'), [
					self::UNITS_POSITION_BEFORE => _('Before value'),
					self::UNITS_POSITION_AFTER => _('After value')
				]))->setDefault(self::UNITS_POSITION_AFTER)
			)
			->addField(
				(new CWidgetFieldColor('bg_color', _('Background color')))
					->setDefault('E65660')
					->allowInherited()
			)
			->addField(
				new CWidgetFieldTextBox('name_regex', _('Name regex'))
			)
			->addField(
				(new CWidgetFieldSelect('sort', _('Sort cells by'), [
					self::SORT_NAME => _('Host and item name'),
					self::SORT_VALUE_ASC => _('Value, ascending'),
					self::SORT_VALUE_DESC => _('Value, descending')
				]))->setDefault(self::SORT_NAME)
			)
			->addField(
				(new CWidgetFieldTextBox('empty_text', _('All clear text')))
					->setDefault('All {COUNT} OK')
			)
			->addField(
				(new CWidgetFieldColor('empty_color', _('All clear color')))
					->setDefault('3BC97D')
			)
			->addField(
				new CWidgetFieldCheckBox('interpolation', _('Color interpolation'))
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'))
			);
	}

	public function validate(bool $strict = false): array {
		if ($strict && $this->isTemplateDashboard()) {
			$this->getField('hostids')->setValue([
				CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
					CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_HOST_IDS
				)
			]);
		}

		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		if (!$this->getFieldValue('show')) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show'), _('at least one option must be selected'));
		}

		$operator = (int) $this->getFieldValue('cond_operator');
		$cond_value = trim($this->getFieldValue('cond_value'));

		if (in_array($operator, [self::OP_REGEX, self::OP_NOT_REGEX]) && $cond_value !== ''
				&& !self::isValidRegex($cond_value)) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show when value'), _('invalid regular expression'));
		}

		if (in_array($operator, [self::OP_LESS, self::OP_LESS_EQUAL, self::OP_GREATER, self::OP_GREATER_EQUAL])
				&& $cond_value !== '' && self::parseNumber($cond_value) === null) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Show when value'),
				_('a numeric value is expected for this operator')
			);
		}

		$name_regex = trim($this->getFieldValue('name_regex'));

		if ($name_regex !== '' && !self::isValidRegex($name_regex)) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Name regex'), _('invalid regular expression'));
		}

		return $errors;
	}

	/**
	 * Wrap a user-supplied pattern in delimiters. Users type bare patterns, as elsewhere in Zabbix.
	 */
	public static function makeRegex(string $pattern): string {
		return "\x01".$pattern."\x01u";
	}

	public static function isValidRegex(string $pattern): bool {
		return @preg_match(self::makeRegex($pattern), '') !== false;
	}

	/**
	 * Parse a number with optional Zabbix suffixes (K, M, G, s, m, h, d, w).
	 *
	 * @return float|null  Null if the string is not a number.
	 */
	public static function parseNumber(string $value): ?float {
		$parser = new \CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => false
		]);

		if ($parser->parse(trim($value)) != \CParser::PARSE_SUCCESS) {
			return null;
		}

		return (float) $parser->calcValue();
	}
}
