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
 * Seçilmiş yazı tipini Google Fonts'tan kuyruğa alır.
 *
 * Yalnızca ADLANDIRILMIŞ bir font seçilmişse istek yapılır: hiçbir şey
 * seçilmemişken ya da sistem fontu (Georgia/serif/sans-serif) seçilmişken
 * ön yüze tek bir dış istek bile eklenmez. Sürüm null'dır — Google adresi
 * kendi sürümünü taşır, `?ver=` eklemek önbelleği bozardı.
 *
 * @return void
 */
function qrms_cs_enqueue_font() {
	$url = qrms_cs_google_font_url( qrms_cs_get_font() );

	if ( '' === $url ) {
		return;
	}

	wp_enqueue_style( 'qrms-cs-font', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
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
	qrms_cs_enqueue_font();

	$hours  = qrms_cs_get();
	$labels = qrms_cs_day_labels();
	$today  = qrms_cs_key_from_iso( (int) wp_date( 'N' ) );
	$only   = ( '1' === (string) $atts['today'] || 'true' === (string) $atts['today'] );

	$is_open = qrms_cs_is_open_at();

	ob_start();
	?>
	<?php // Kart, başlık ve alt not liste DIŞINDA durur: renk değişkenleri de burada, kapsayıcıda toplanır. ?>
	<div class="qrms-cs-card"<?php echo qrms_cs_inline_style_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr içeride. ?>>
		<div class="qrms-cs-head">
			<span class="qrms-cs-badge" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<circle cx="12" cy="12" r="9"></circle>
					<path d="M12 7v5l3 2"></path>
				</svg>
			</span>
			<span class="qrms-cs-title"><?php esc_html_e( 'Çalışma Saatleri', 'qrms' ); ?></span>
			<?php // Durum, açık/kapalı hesabının (qrms_cs_is_open_at) tek kaynağından gelir. ?>
			<span class="qrms-cs-status <?php echo esc_attr( $is_open ? 'is-open' : 'is-shut' ); ?>">
				<span class="qrms-cs-status-dot" aria-hidden="true"></span>
				<?php echo esc_html( $is_open ? __( 'Şu an açığız', 'qrms' ) : __( 'Şu an kapalıyız', 'qrms' ) ); ?>
			</span>
		</div>

	<ul class="qrms-cs-list">
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
				<span class="qrms-cs-item-day">
					<?php echo esc_html( $labels[ $key ] ); ?>
					<?php if ( $is_today ) : ?>
						<span class="qrms-cs-today-tag"><?php esc_html_e( 'Bugün', 'qrms' ); ?></span>
					<?php endif; ?>
				</span>
				<?php // Noktalı dolgu: gün adı ile saat arasındaki boşluğu flex ile kapatır. ?>
				<span class="qrms-cs-fill" aria-hidden="true"></span>
				<span class="qrms-cs-item-hours"><?php echo esc_html( qrms_cs_format_day( $day ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>

		<p class="qrms-cs-note">
			<span class="qrms-cs-note-dot" aria-hidden="true"></span>
			<?php esc_html_e( 'Sipariş ve rezervasyon için bizi arayın', 'qrms' ); ?>
		</p>
	</div>
	<?php
	return (string) ob_get_clean();
}
