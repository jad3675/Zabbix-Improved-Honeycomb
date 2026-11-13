<?php declare(strict_types = 0);

namespace Modules\ExceptionHoneycomb\Actions;

use API,
	CArrayHelper,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMacrosResolverHelper,
	CNumberParser,
	CSettingsHelper,
	CValueMapHelper,
	Manager;

use Modules\ExceptionHoneycomb\Includes\WidgetForm;

/**
 * Same data path as the stock Honeycomb, with one difference: every matching item is evaluated against the
 * configured condition, and only items whose latest value satisfies it become cells.
 */
class WidgetView extends CControllerDashboardWidgetView {

	private const LABEL_MAX_LINES = 10;
	private const LABEL_MAX_LINE_LENGTH = 250;
	private const MAX_ITEMS = 1000;

	// Upper bound on items evaluated per refresh. Every item has to be checked to know what is "bad", so this
	// is separate from the number of cells that fit on screen.
	private const MAX_EVALUATED_ITEMS = 10000;
	private const HISTORY_BATCH = 1000;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'with_config' => 'in 1',
			'max_items' => 'int32'
		]);
	}

	protected function doAction(): void {
		[$cells, $total] = $this->getCells($this->getInput('max_items', self::MAX_ITEMS) + 1);

		$empty_label = null;

		if (!$cells && $total > 0) {
			$empty_label = str_replace('{COUNT}', (string) $total, $this->fields_values['empty_text']);

			if (trim($empty_label) === '') {
				$empty_label = (string) $total;
			}
		}

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'vars' => [
				'cells' => $cells,
				'empty_label' => $empty_label
			]
		];

		if ($this->hasInput('with_config')) {
			$data['vars']['config'] = $this->getConfig();
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * @return array  [cells to display (trimmed to $limit), number of items evaluated]
	 */
	private function getCells(int $limit): array {
		if ($this->isTemplateDashboard() && !$this->fields_values['hostids']) {
			return [[], 0];
		}

		$groupids = null;
		$evaltype = null;
		$tags = null;

		if (!$this->isTemplateDashboard()) {
			if ($this->fields_values['groupids']) {
				$groupids = getSubGroups($this->fields_values['groupids']);
			}

			if ($this->fields_values['host_tags']) {
				$evaltype = $this->fields_values['evaltype_host'];
				$tags = $this->fields_values['host_tags'];
			}
		}

		$hostids = $this->fields_values['hostids'] ?: null;
		$filter = $this->fields_values['maintenance'] != 1
			? ['maintenance_status' => HOST_MAINTENANCE_STATUS_OFF]
			: null;

		if ($groupids !== null || $hostids !== null || $tags !== null || $filter !== null) {
			$db_hosts = API::Host()->get([
				'output' => [],
				'groupids' => $groupids,
				'hostids' => $hostids,
				'filter' => $filter,
				'evaltype' => $evaltype,
				'tags' => $tags,
				'monitored_hosts' => true,
				'preservekeys' => true
			]);

			if (!$db_hosts) {
				return [[], 0];
			}

			$hostids = array_keys($db_hosts);
		}

		$search_field = $this->isTemplateDashboard() ? 'name' : 'name_resolved';

		$db_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'units', 'value_type', 'name_resolved', 'key_'],
			'selectHosts' => ['name'],
			'webitems' => true,
			'hostids' => $hostids,
			'evaltype' => $this->fields_values['evaltype_item'],
			'tags' => $this->fields_values['item_tags'] ?: null,
			'selectValueMap' => ['mappings'],
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'search' => [
				$search_field => in_array('*', $this->fields_values['items'], true)
					? null
					: $this->fields_values['items']
			],
			'filter' => ['status' => ITEM_STATUS_ACTIVE],
			'limit' => self::MAX_EVALUATED_ITEMS
		]);

		if (!$db_items) {
			return [[], 0];
		}

		$items = CArrayHelper::renameObjectsKeys($db_items, ['name_resolved' => 'name']);

		foreach ($items as &$item) {
			$item['hostname'] = $item['hosts'][0]['name'];
		}
		unset($item);

		$condition = $this->getCondition();
		$show_nodata = $this->fields_values['show_nodata'] == 1;
		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		$total = 0;
		$matched = [];

		foreach (array_chunk($items, self::HISTORY_BATCH) as $batch_items) {
			$db_history = Manager::History()->getLastValues($batch_items, 1, $history_period);

			foreach ($batch_items as $item) {
				if (!array_key_exists($item['itemid'], $db_history)) {
					if ($show_nodata) {
						$total++;
						$matched[] = $item + ['last_value' => null];
					}

					continue;
				}

				$total++;
				$last_value = $db_history[$item['itemid']][0]['value'];

				if ($this->matchesCondition($item, (string) $last_value, $condition)) {
					$matched[] = $item + ['last_value' => $last_value];
				}
			}
		}

		$this->sortItems($matched);

		$show = array_flip($this->fields_values['show']);
		$name_regex = trim($this->fields_values['name_regex']);
		$name_regex = $name_regex !== '' ? WidgetForm::makeRegex($name_regex) : null;
		$cells = [];

		foreach (array_slice($matched, 0, $limit) as $item) {
			$last_value = $item['last_value'];
			$name_match = $this->getNameMatch($item['name'], $name_regex);

			$primary_label = array_key_exists(WidgetForm::SHOW_PRIMARY_LABEL, $show)
				? $this->getCellLabel($item, $last_value, $name_match, [
					'label' => $this->fields_values['primary_label'],
					'label_decimal_places' => $this->fields_values['primary_label_decimal_places'],
					'label_type' => $this->fields_values['primary_label_type'],
					'label_units' => $this->fields_values['primary_label_units'],
					'label_units_pos' => $this->fields_values['primary_label_units_pos'],
					'label_units_show' => $this->fields_values['primary_label_units_show']
				])
				: '';

			$secondary_label = array_key_exists(WidgetForm::SHOW_SECONDARY_LABEL, $show)
				? $this->getCellLabel($item, $last_value, $name_match, [
					'label' => $this->fields_values['secondary_label'],
					'label_decimal_places' => $this->fields_values['secondary_label_decimal_places'],
					'label_type' => $this->fields_values['secondary_label_type'],
					'label_units' => $this->fields_values['secondary_label_units'],
					'label_units_pos' => $this->fields_values['secondary_label_units_pos'],
					'label_units_show' => $this->fields_values['secondary_label_units_show']
				])
				: '';

			$is_numeric_item = $last_value !== null
				&& in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);

			$cells[] = [
				'hostid' => $item['hostid'],
				'itemid' => $item['itemid'],
				'primary_label' => $primary_label,
				'secondary_label' => $secondary_label,
				'value' => $is_numeric_item ? $last_value : null,
				'is_numeric' => $is_numeric_item,
				'is_binary_units' => isBinaryUnits($item['units']),
				'key_' => $item['key_']
			];
		}

		return [$cells, $total];
	}

	/**
	 * Pre-compute everything about the condition once, instead of per item.
	 */
	private function getCondition(): array {
		$operator = (int) $this->fields_values['cond_operator'];
		$value = trim($this->fields_values['cond_value']);

		$condition = [
			'operator' => $operator,
			'compare' => (int) $this->fields_values['cond_compare'],
			'enabled' => $value !== '',
			'value' => $value,
			'number' => null,
			'list' => [],
			'regex' => null
		];

		switch ($operator) {
			case WidgetForm::OP_IN:
			case WidgetForm::OP_NOT_IN:
				foreach (explode(',', $value) as $entry) {
					$entry = trim($entry);

					if ($entry !== '') {
						$condition['list'][] = ['value' => $entry, 'number' => WidgetForm::parseNumber($entry)];
					}
				}

				$condition['enabled'] = (bool) $condition['list'];
				break;

			case WidgetForm::OP_REGEX:
			case WidgetForm::OP_NOT_REGEX:
				$condition['regex'] = WidgetForm::makeRegex($value);
				break;

			default:
				$condition['number'] = WidgetForm::parseNumber($value);
		}

		return $condition;
	}

	private function matchesCondition(array $item, string $raw_value, array $condition): bool {
		// No condition configured: behave like the stock Honeycomb and show everything.
		if (!$condition['enabled']) {
			return true;
		}

		$value = $raw_value;

		if ($condition['compare'] == WidgetForm::COMPARE_MAPPED && $item['valuemap']) {
			$mapped = CValueMapHelper::getMappedValue($item['value_type'], $raw_value, $item['valuemap']);

			if ($mapped !== false) {
				$value = (string) $mapped;
			}
		}

		$value_number = is_numeric($value) ? (float) $value : null;

		switch ($condition['operator']) {
			case WidgetForm::OP_EQUAL:
				return self::isEqual($value, $value_number, $condition['value'], $condition['number']);

			case WidgetForm::OP_NOT_EQUAL:
				return !self::isEqual($value, $value_number, $condition['value'], $condition['number']);

			case WidgetForm::OP_LESS:
				return $value_number !== null && $condition['number'] !== null
					&& $value_number < $condition['number'];

			case WidgetForm::OP_LESS_EQUAL:
				return $value_number !== null && $condition['number'] !== null
					&& $value_number <= $condition['number'];

			case WidgetForm::OP_GREATER:
				return $value_number !== null && $condition['number'] !== null
					&& $value_number > $condition['number'];

			case WidgetForm::OP_GREATER_EQUAL:
				return $value_number !== null && $condition['number'] !== null
					&& $value_number >= $condition['number'];

			case WidgetForm::OP_IN:
			case WidgetForm::OP_NOT_IN:
				$in_list = false;

				foreach ($condition['list'] as $entry) {
					if (self::isEqual($value, $value_number, $entry['value'], $entry['number'])) {
						$in_list = true;
						break;
					}
				}

				return $condition['operator'] == WidgetForm::OP_IN ? $in_list : !$in_list;

			case WidgetForm::OP_REGEX:
				return @preg_match($condition['regex'], $value) === 1;

			case WidgetForm::OP_NOT_REGEX:
				// A value that cannot be tested (invalid UTF-8) is not treated as a match either way.
				$result = @preg_match($condition['regex'], $value);

				return $result === 0;
		}

		return false;
	}

	/**
	 * Numeric comparison when both sides are numbers ("1" equals "1.0000"), string comparison otherwise.
	 */
	private static function isEqual(string $value, ?float $value_number, string $target, ?float $target_number): bool {
		if ($value_number !== null && $target_number !== null) {
			return abs($value_number - $target_number) < 1e-9 * max(1, abs($target_number));
		}

		return $value === $target;
	}

	private function sortItems(array &$items): void {
		$sort = (int) $this->fields_values['sort'];

		if ($sort == WidgetForm::SORT_NAME) {
			CArrayHelper::sort($items, ['hostname', 'name']);

			return;
		}

		$direction = $sort == WidgetForm::SORT_VALUE_DESC ? -1 : 1;

		usort($items, static function (array $a, array $b) use ($direction): int {
			$a_num = $a['last_value'] !== null && is_numeric($a['last_value']);
			$b_num = $b['last_value'] !== null && is_numeric($b['last_value']);

			// Items without data and non-numeric values always go last, in name order.
			if ($a_num !== $b_num) {
				return $a_num ? -1 : 1;
			}

			if ($a_num) {
				$cmp = ((float) $a['last_value'] <=> (float) $b['last_value']) * $direction;

				if ($cmp != 0) {
					return $cmp;
				}
			}

			return strnatcasecmp($a['hostname'], $b['hostname']) ?: strnatcasecmp($a['name'], $b['name']);
		});
	}

	/**
	 * First capture group of the name regex, or the whole match if the pattern has no groups.
	 */
	private function getNameMatch(string $name, ?string $regex): string {
		if ($regex === null || @preg_match($regex, $name, $matches) !== 1) {
			return $name;
		}

		return $matches[1] ?? $matches[0];
	}

	private function getCellLabel(array $item, $last_value, string $name_match, array $context_fields_values): string {
		if ($context_fields_values['label_type'] == WidgetForm::LABEL_TYPE_TEXT) {
			$label = $context_fields_values['label'];

			if (!$this->isTemplateDashboard() || $this->fields_values['hostids']) {
				$resolved_label = CMacrosResolverHelper::resolveItemBasedWidgetMacros(
					[$item['itemid'] => $item + ['label' => $label]],
					['label' => 'label']
				);
				$label = $resolved_label[$item['itemid']]['label'];
			}

			// {MATCH} is resolved after Zabbix macros so a captured value can never be re-expanded as a macro.
			$label = str_replace('{MATCH}', $name_match, $label);

			return $this->trimCellLabel($label);
		}

		if ($last_value === null) {
			return _('No data');
		}

		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				if ($context_fields_values['label_units_show'] == 1) {
					if ($context_fields_values['label_units'] !== '') {
						$item['units'] = $context_fields_values['label_units'];
					}
				}
				else {
					$item['units'] = '';
				}

				$formatted_value = formatHistoryValueRaw($last_value, $item, false, [
					'decimals' => $context_fields_values['label_decimal_places'],
					'decimals_exact' => true,
					'small_scientific' => false,
					'zero_as_zero' => false
				]);

				if ($context_fields_values['label_units_show'] == 1) {
					return $context_fields_values['label_units_pos'] == WidgetForm::UNITS_POSITION_BEFORE
						? $formatted_value['units'].' '.$formatted_value['value']
						: $formatted_value['value'].' '.$formatted_value['units'];
				}

				return $formatted_value['value'];

			default:
				return $this->trimCellLabel(formatHistoryValue($last_value, $item, false));
		}
	}

	private function trimCellLabel(string $label): string {
		$result = '';

		foreach (array_slice(explode("\n", $label, self::LABEL_MAX_LINES + 1), 0, self::LABEL_MAX_LINES) as $line) {
			$result .= ($result !== '' ? "\n" : '').mb_substr(trim($line), 0, self::LABEL_MAX_LINE_LENGTH);
		}

		return $result;
	}

	private function getConfig(): array {
		$config = [
			'bg_color' => $this->fields_values['bg_color'],
			'empty_color' => $this->fields_values['empty_color']
		];

		$show = array_flip($this->fields_values['show']);

		foreach (['primary' => WidgetForm::SHOW_PRIMARY_LABEL, 'secondary' => WidgetForm::SHOW_SECONDARY_LABEL]
				as $prefix => $flag) {
			if (array_key_exists($flag, $show)) {
				$is_custom_size = $this->fields_values[$prefix.'_label_size_type'] == WidgetForm::LABEL_SIZE_CUSTOM;

				$config[$prefix.'_label'] = [
					'show' => true,
					'is_custom_size' => $is_custom_size,
					'is_bold' => $this->fields_values[$prefix.'_label_bold'] == 1,
					'color' => $this->fields_values[$prefix.'_label_color']
				];

				if ($is_custom_size) {
					$config[$prefix.'_label']['size'] = $this->fields_values[$prefix.'_label_size'];
				}
			}
			else {
				$config[$prefix.'_label']['show'] = false;
			}
		}

		$config['apply_interpolation'] = $this->fields_values['interpolation'] == 1;
		$config['thresholds'] = $this->fields_values['thresholds'];

		$number_parser = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => false
		]);

		$number_parser_binary = new CNumberParser([
			'with_size_suffix' => true,
			'with_time_suffix' => true,
			'is_binary_size' => true
		]);

		foreach ($config['thresholds'] as &$threshold) {
			$number_parser_binary->parse($threshold['threshold']);
			$threshold['threshold_binary'] = $number_parser_binary->calcValue();

			$number_parser->parse($threshold['threshold']);
			$threshold['threshold'] = $number_parser->calcValue();
		}
		unset($threshold);

		return $config;
	}
}
