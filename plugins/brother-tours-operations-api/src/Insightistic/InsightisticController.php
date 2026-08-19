<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Insightistic;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;

use function BrotherTours\OperationsApi\response;

final class InsightisticController
{
    public function register(): void
    {
        add_action('rest_api_init', array($this, 'routes'));
    }

    public function routes(): void
    {
        register_rest_route(
            BTOA_NAMESPACE,
            '/insightistic',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get'),
                'permission_callback' => static fn(WP_REST_Request $request) => Csrf::authorize($request, 'bt_view_health', false),
            )
        );
    }

    public function get(WP_REST_Request $request)
    {
        return response(array('insightistic' => $this->insightistic_data()));
    }

    private function insightistic_data(): array
    {
        $active = false;
        $version = null;

        if (defined('WPISTIC_INSIGHTISTIC_VERSION')) {
            $active = true;
            $version = (string) WPISTIC_INSIGHTISTIC_VERSION;
        }

        if (! $active && (class_exists('\\Wpistic\\Insightistic\\Plugin') || class_exists('\\Wpistic_Insightistic') || function_exists('wpistic_insightistic'))) {
            $active = true;
        }

        return array(
            'active'  => $active,
            'version' => $version,
        );
    }
}
