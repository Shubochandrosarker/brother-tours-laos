<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Security {
	public function register(): void {
		add_filter( 'rest_endpoints', array( $this, 'restrict_user_routes' ), 99 );
	}

	/** @param array<string,mixed> $endpoints @return array<string,mixed> */
	public function restrict_user_routes( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users(?:/|$)#', (string) $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}
}
