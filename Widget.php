<?php declare(strict_types = 0);

namespace Modules\ExceptionHoneycomb;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	// Same palette as the stock Honeycomb so thresholds pick familiar colors.
	public const DEFAULT_COLOR_PALETTE = [
		'E65660', 'FCCB1D', '3BC97D', '2ED3B7', '19D0D7', '29C2FA', '58B0FE', '5D98FE', '859AFA', 'E580FA',
		'F773C7', 'FC5F7E', 'FC738E', 'FF6D2E', 'F48D48', 'F89C3A', 'FBB318', 'FECF62', '87CE40', 'A3E86D'
	];

	public function getDefaultName(): string {
		return _('Exception honeycomb');
	}

	public function getTranslationStrings(): array {
		return [
			'class.svgexceptionhoneycomb.js' => [
				'No data' => _('No data')
			],
			'class.widget.js' => [
				'Actions' => _('Actions'),
				'Download image' => _('Download image')
			]
		];
	}
}
