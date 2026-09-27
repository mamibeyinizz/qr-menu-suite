<?php
/**
 * Öneri Raporu alt sayfası.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ürün fiyatını rapor cirosu için sayıya çevirir.
 *
 * @param int $urun_id Ürün kimliği.
 * @return float
 */
function qmo_chatbot_oneri_urun_fiyat_sayi( $urun_id ) {
	$urun_id = absint( $urun_id );
	if ( $urun_id < 1 ) {
		return 0.0;
	}

	$ham = function_exists( 'rma_get_effective_price' )
		? rma_get_effective_price( $urun_id )
		: get_post_meta( $urun_id, 'rma_price', true );

	return is_numeric( $ham ) ? (float) $ham : 0.0;
}

/**
 * Öneri raporu ekranı.
 *
 * @return void
 */
function qmo_chatbot_sayfa_oneri_rapor() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
	}

	QMO_Chatbot_DB::sema_kontrol();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$bitis = isset( $_GET['bitis'] ) ? sanitize_text_field( wp_unslash( $_GET['bitis'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$baslangic = isset( $_GET['baslangic'] ) ? sanitize_text_field( wp_unslash( $_GET['baslangic'] ) ) : '';

	if ( '' === $bitis ) {
		$bitis = gmdate( 'Y-m-d' );
	}
	if ( '' === $baslangic ) {
		$baslangic = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
	}

	$ham      = QMO_Chatbot_DB::oneri_rapor( $baslangic, $bitis );
	$satirlar = array();
	$ozet     = array(
		'gosterildi'               => 0,
		'sepete'                   => 0,
		'atfedilen_siparis'        => 0,
		'dogrudan_chatbot_siparis' => 0,
		'ciro'                     => 0.0,
	);

	$atfedilen_aciklama = __(
		'Aynı oturum ve ürün için recommendation sepete ekleme sonrası gerçekleşen order_sent ile gözlemsel ilişki; kesin sipariş bağı değildir.',
		'qrms'
	);

	foreach ( $ham as $satir ) {
		$urun_id    = (int) $satir['urun_id'];
		$gosterildi = (int) $satir['gosterildi'];
		$sepete     = (int) $satir['sepete'];
		$atfedilen  = (int) $satir['atfedilen_siparis'];
		$bot        = (int) $satir['dogrudan_chatbot_siparis'];
		$fiyat      = qmo_chatbot_oneri_urun_fiyat_sayi( $urun_id );
		$ciro       = $atfedilen * $fiyat;
		$ad         = get_the_title( $urun_id );
		if ( '' === $ad ) {
			$ad = '#' . $urun_id;
		}

		$satirlar[] = array(
			'urun_id'                  => $urun_id,
			'ad'                       => $ad,
			'gosterildi'               => $gosterildi,
			'sepete'                   => $sepete,
			'atfedilen_siparis'        => $atfedilen,
			'dogrudan_chatbot_siparis' => $bot,
			'donusum_orani'            => $gosterildi > 0 ? round( ( $atfedilen / $gosterildi ) * 100, 1 ) : 0.0,
			'ciro'                     => $ciro,
			'fiyat_metin'              => function_exists( 'rma_ceviri_fiyat' ) ? rma_ceviri_fiyat( $fiyat ) : (string) $fiyat,
		);

		$ozet['gosterildi']               += $gosterildi;
		$ozet['sepete']                   += $sepete;
		$ozet['atfedilen_siparis']        += $atfedilen;
		$ozet['dogrudan_chatbot_siparis'] += $bot;
		$ozet['ciro']                     += $ciro;
	}

	usort(
		$satirlar,
		function ( $a, $b ) {
			if ( $a['ciro'] === $b['ciro'] ) {
				return $b['atfedilen_siparis'] - $a['atfedilen_siparis'];
			}
			return ( $a['ciro'] > $b['ciro'] ) ? -1 : 1;
		}
	);

	$ozet['donusum'] = $ozet['gosterildi'] > 0
		? round( ( $ozet['atfedilen_siparis'] / $ozet['gosterildi'] ) * 100, 1 )
		: 0.0;

	qmo_chatbot_sayfa_basligi(
		__( 'Öneri Raporu', 'qrms' ),
		__( 'Ürün önerilerinin sepete ve atfedilen siparişe dönüşüm performansı.', 'qrms' )
	);
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="qmo-cb-filtre">
		<input type="hidden" name="page" value="qrms-chatbot-oneri-rapor">
		<label><?php esc_html_e( 'Başlangıç', 'qrms' ); ?>
			<input type="date" name="baslangic" value="<?php echo esc_attr( $baslangic ); ?>">
		</label>
		<label><?php esc_html_e( 'Bitiş', 'qrms' ); ?>
			<input type="date" name="bitis" value="<?php echo esc_attr( $bitis ); ?>">
		</label>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrele', 'qrms' ); ?></button>
	</form>

	<div class="qmo-cb-rapor-ozet">
		<div class="qmo-cb-rapor-kart">
			<span class="qmo-cb-rapor-etiket"><?php esc_html_e( 'Toplam öneri', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger"><?php echo esc_html( number_format_i18n( $ozet['gosterildi'] ) ); ?></strong>
		</div>
		<div class="qmo-cb-rapor-kart">
			<span class="qmo-cb-rapor-etiket"><?php esc_html_e( 'Sepete eklenen', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger"><?php echo esc_html( number_format_i18n( $ozet['sepete'] ) ); ?></strong>
		</div>
		<div class="qmo-cb-rapor-kart">
			<span class="qmo-cb-rapor-etiket" title="<?php echo esc_attr( $atfedilen_aciklama ); ?>"><?php esc_html_e( 'Atfedilen sipariş', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger"><?php echo esc_html( number_format_i18n( $ozet['atfedilen_siparis'] ) ); ?></strong>
		</div>
		<div class="qmo-cb-rapor-kart">
			<span class="qmo-cb-rapor-etiket"><?php esc_html_e( 'Doğrudan chatbot siparişi', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger"><?php echo esc_html( number_format_i18n( $ozet['dogrudan_chatbot_siparis'] ) ); ?></strong>
		</div>
		<div class="qmo-cb-rapor-kart">
			<span class="qmo-cb-rapor-etiket"><?php esc_html_e( 'Dönüşüm oranı', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger"><?php echo esc_html( $ozet['donusum'] ); ?>%</strong>
		</div>
		<div class="qmo-cb-rapor-kart">
			<span class="qmo-cb-rapor-etiket"><?php esc_html_e( 'Tahmini ciro', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger">
				<?php
				echo esc_html(
					function_exists( 'rma_ceviri_fiyat' )
						? rma_ceviri_fiyat( $ozet['ciro'] )
						: number_format_i18n( $ozet['ciro'], 2 )
				);
				?>
			</strong>
		</div>
	</div>

	<table class="widefat striped" id="qmo-cb-oneri-rapor-tablo">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Ürün', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Gösterildi', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Sepete', 'qrms' ); ?></th>
				<th title="<?php echo esc_attr( $atfedilen_aciklama ); ?>"><?php esc_html_e( 'Atfedilen sipariş', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Doğrudan chatbot siparişi', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Dönüşüm %', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Tahmini ciro', 'qrms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $satirlar ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'Seçilen aralıkta kayıt yok.', 'qrms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $satirlar as $satir ) : ?>
					<tr>
						<td><?php echo esc_html( $satir['ad'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['gosterildi'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['sepete'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['atfedilen_siparis'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['dogrudan_chatbot_siparis'] ) ); ?></td>
						<td><?php echo esc_html( $satir['donusum_orani'] ); ?>%</td>
						<td>
							<?php
							echo esc_html(
								function_exists( 'rma_ceviri_fiyat' )
									? rma_ceviri_fiyat( $satir['ciro'] )
									: number_format_i18n( $satir['ciro'], 2 )
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
	qmo_chatbot_sayfa_bitir();
}
