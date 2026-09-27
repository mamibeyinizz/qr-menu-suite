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

	$rapor_ham     = QMO_Chatbot_DB::oneri_rapor( $baslangic, $bitis );
	$attr_ozet     = QMO_Chatbot_DB::oneri_rapor_ozet_attribution( $rapor_ham );
	$urun_satirlari = QMO_Chatbot_DB::oneri_rapor_urunler( $rapor_ham );

	$satirlar = array();
	$ozet     = array(
		'gosterildi'               => 0,
		'sepete'                   => 0,
		'dogrudan_chatbot_siparis' => 0,
	);

	$attribution_aciklama = __(
		'Öneri etkileşimi sonrası ilişkilendirilen sipariş ve ürün hareketlerini gösterir. Bu değerler gözlemsel attribution verisidir; önerinin tek başına satışa neden olduğunu göstermez.',
		'qrms'
	);

	$atfedilen_siparis_aciklama = __(
		'Ürün satırında: bu ürün için atfedilen tekil sipariş (DISTINCT order_id). Özet kartında: tüm ürünler genelinde tekil sipariş sayısı; aynı sipariş birden fazla ürün satırında tekrar sayılmaz.',
		'qrms'
	);

	$atfedilen_tutar_aciklama = __(
		'Sipariş anındaki liste fiyatı ve attribution edilen birimler üzerinden hesaplanır. Gerçek tahsil edilmiş ciro değildir.',
		'qrms'
	);

	foreach ( $urun_satirlari as $satir ) {
		$urun_id    = (int) $satir['urun_id'];
		$gosterildi = (int) $satir['gosterildi'];
		$sepete     = (int) $satir['sepete'];
		$atfedilen  = (int) $satir['atfedilen_siparis'];
		$birim      = (int) ( $satir['atfedilen_birim'] ?? 0 );
		$tutar      = (float) ( $satir['atfedilen_tutar'] ?? 0.0 );
		$bot        = (int) $satir['dogrudan_chatbot_siparis'];
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
			'atfedilen_birim'          => $birim,
			'atfedilen_tutar'          => $tutar,
			'dogrudan_chatbot_siparis' => $bot,
			'donusum_orani'            => $gosterildi > 0 ? round( ( $atfedilen / $gosterildi ) * 100, 1 ) : 0.0,
		);

		$ozet['gosterildi']               += $gosterildi;
		$ozet['sepete']                   += $sepete;
		$ozet['dogrudan_chatbot_siparis'] += $bot;
	}

	usort(
		$satirlar,
		function ( $a, $b ) {
			$ta = (float) $a['atfedilen_tutar'];
			$tb = (float) $b['atfedilen_tutar'];
			if ( $ta !== $tb ) {
				return ( $ta > $tb ) ? -1 : 1;
			}
			if ( $a['atfedilen_birim'] !== $b['atfedilen_birim'] ) {
				return $b['atfedilen_birim'] - $a['atfedilen_birim'];
			}
			return $a['urun_id'] - $b['urun_id'];
		}
	);

	$global_tekil = (int) $attr_ozet['atfedilen_siparis_tekil'];
	$ozet['donusum'] = $ozet['gosterildi'] > 0
		? round( ( $global_tekil / $ozet['gosterildi'] ) * 100, 1 )
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

	<p class="description" title="<?php echo esc_attr( $attribution_aciklama ); ?>">
		<?php echo esc_html( $attribution_aciklama ); ?>
	</p>

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
			<span class="qmo-cb-rapor-etiket" title="<?php echo esc_attr( $atfedilen_siparis_aciklama ); ?>"><?php esc_html_e( 'Atfedilen sipariş (tekil)', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger"><?php echo esc_html( number_format_i18n( $global_tekil ) ); ?></strong>
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
			<span class="qmo-cb-rapor-etiket" title="<?php echo esc_attr( $atfedilen_tutar_aciklama ); ?>"><?php esc_html_e( 'Atfedilen Tutar (Liste Fiyatı)', 'qrms' ); ?></span>
			<strong class="qmo-cb-rapor-deger">
				<?php
				echo esc_html(
					function_exists( 'rma_ceviri_fiyat' )
						? rma_ceviri_fiyat( (float) $attr_ozet['atfedilen_tutar'] )
						: number_format_i18n( (float) $attr_ozet['atfedilen_tutar'], 2 )
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
				<th title="<?php echo esc_attr( $atfedilen_siparis_aciklama ); ?>"><?php esc_html_e( 'Atfedilen sipariş', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Atfedilen birim', 'qrms' ); ?></th>
				<th title="<?php echo esc_attr( $atfedilen_tutar_aciklama ); ?>"><?php esc_html_e( 'Atfedilen Tutar (Liste Fiyatı)', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Doğrudan chatbot siparişi', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Dönüşüm %', 'qrms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $satirlar ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'Seçilen aralıkta kayıt yok.', 'qrms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $satirlar as $satir ) : ?>
					<tr>
						<td><?php echo esc_html( $satir['ad'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['gosterildi'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['sepete'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['atfedilen_siparis'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $satir['atfedilen_birim'] ) ); ?></td>
						<td>
							<?php
							echo esc_html(
								function_exists( 'rma_ceviri_fiyat' )
									? rma_ceviri_fiyat( (float) $satir['atfedilen_tutar'] )
									: number_format_i18n( (float) $satir['atfedilen_tutar'], 2 )
							);
							?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $satir['dogrudan_chatbot_siparis'] ) ); ?></td>
						<td><?php echo esc_html( $satir['donusum_orani'] ); ?>%</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
	qmo_chatbot_sayfa_bitir();
}
