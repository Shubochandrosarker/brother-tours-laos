<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Install;

final class Deactivator {

	public static function deactivate(): void {
		// Never destroy data on deactivate — only clear rewrite rules.
		flush_rewrite_rules();
	}
}
