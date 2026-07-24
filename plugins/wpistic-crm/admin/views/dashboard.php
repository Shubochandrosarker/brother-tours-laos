<?php
/**
 * Mount point for the React dashboard. The bundle takes over <div id="g2a-crm-root">.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$build_exists = file_exists( G2A_CRM_PLUGIN_DIR . 'admin/app/dist/.vite/manifest.json' );
?>
<div class="wrap g2a-crm-wrap" style="margin: 0; padding: 0; background: #f4f2ec; min-height: calc(100vh - 32px); margin-left: -20px;">
	<div id="g2a-crm-root">
		<?php if ( ! $build_exists ) : ?>
			<div class="g2a-crm-bootstrap">
				<h1 style="color:#B8893E; margin: 0 0 16px;">WPistic CRM</h1>
				<p>The React dashboard hasn't been built yet. Run the build in the plugin folder:</p>
				<pre style="background:#11161C; padding:16px; border-radius:6px; overflow-x:auto;">cd <?php echo esc_html( G2A_CRM_PLUGIN_DIR . 'admin/app' ); ?>
npm install
npm run build</pre>
				<p>Then reload this page. The REST API at <code><?php echo esc_html( rest_url( G2A_CRM_REST_NAMESPACE ) ); ?>/reports/dashboard</code> is already live — try it once you're logged in as an admin.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
