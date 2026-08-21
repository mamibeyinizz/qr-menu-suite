<?php
/**
 * [qr_calisma_saatleri] kısa kodu.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ön yüz stilini kaydeder (kısa kod basılınca kuyruğa alınır).
 *
 * @return void
 */
function qrms_cs_register_frontend_assets() {
	wp_register_style(
		'qrms-cs-frontend',
		QRMS_PLUGIN_URL . 'modules/qr-calisma-saatleri/assets/css/frontend.css',
		array(),
		QRMS_Helpers::asset_version( 'modules/qr-calisma-saatleri/assets/css/frontend.css' )
	);
}

/**
 * Haftalık çalışma saatleri listesi.
 *
 * @param array|string $atts Kısa kod öznitelikleri.
 * @return string
 */
function qrms_cs_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'today' => '0',
		),
		$atts,
		'qr_calisma_saatleri'
	);

	wp_enqueue_style( 'qrms-cs-frontend' );

	$hours  = qrms_cs_get();
	$labels = qrms_cs_day_labels();
	$today  = qrms_cs_key_from_iso( (int) wp_date( 'N' ) );
	$only   = ( '1' === (string) $atts['today'] || 'true' === (string) $atts['today'] );

	ob_start();
	?>
	<ul class="qrms-cs-list"<?php echo qrms_cs_inline_style_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr içeride. ?>>
		<?php foreach ( qrms_cs_day_keys() as $key ) : ?>
			<?php
			if ( $only && $key !== $today ) {
				continue;
			}

			$day       = $hours[ $key ];
			$classes   = array( 'qrms-cs-item' );
			$is_today  = ( $key === $today );
			$is_closed = ! empty( $day['closed'] );

			if ( $is_today ) {
				$classes[] = 'is-today';
			}
			if ( $is_closed ) {
				$classes[] = 'is-closed';
			}
			?>
			<?php // data-day: yönetimdeki canlı önizleme satırı gün kartıyla bu anahtardan eşleştirir. ?>
			<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-day="<?php echo esc_attr( $key ); ?>">
				<span class="qrms-cs-item-day"><?php echo esc_html( $labels[ $key ] ); ?></span>
				<span class="qrms-cs-item-hours"><?php echo esc_html( qrms_cs_format_day( $day ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	return (string) ob_get_clean();
}
