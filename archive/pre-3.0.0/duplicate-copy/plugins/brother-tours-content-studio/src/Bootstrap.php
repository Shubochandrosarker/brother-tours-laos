<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Bootstrap {
	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {}

	public function boot(): void {
		load_plugin_textdomain( 'brother-tours-content-studio', false, dirname( plugin_basename( BT_CS_FILE ) ) . '/languages' );

		( new Capabilities() )->register();
		( new Settings() )->register();
		( new Fields() )->register();
		( new Blocks() )->register();
		( new Templates() )->register();
		( new Migration() )->register();
		( new Seo() )->register();
		( new Sitemap() )->register();
		( new Security() )->register();
	}
}
