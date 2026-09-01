<?php
/**
 * Sohbet Geçmişi alt sayfası.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geçmiş listesi.
 *
 * @return void
 */
function qmo_chatbot_sayfa_gecmis() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
	}

	QMO_Chatbot_DB::sema_kontrol();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$baslangic = isset( $_GET['baslangic'] ) ? sanitize_text_field( wp_unslash( $_GET['baslangic'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$bitis = isset( $_GET['bitis'] ) ? sanitize_text_field( wp_unslash( $_GET['bitis'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$masa = isset( $_GET['masa'] ) ? sanitize_text_field( wp_unslash( $_GET['masa'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$arama = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sayfa = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

	$sonuc = QMO_Chatbot_DB::mesaj_liste(
		array(
			'baslangic' => $baslangic,
			'bitis'     => $bitis,
			'masa'      => $masa,
			'arama'     => $arama,
			'sayfa'     => $sayfa,
			'adet'      => 20,
		)
	);

	$masalar = QMO_Chatbot_DB::masa_listesi();
	$toplam  = (int) $sonuc['toplam'];
	$sayfa_s = (int) ceil( $toplam / 20 );

	qmo_chatbot_sayfa_basligi(
		__( 'Sohbet Geçmişi', 'qrms' ),
		__( 'Ziyaretçilerin soruları ve asistanın cevapları. Satıra tıklayınca o oturumun tam yazışması açılır.', 'qrms' )
	);

	qmo_chatbot_form_ac();
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="qmo_chatbot_retention_days"><?php esc_html_e( 'Eski kayıtları otomatik sil', 'qrms' ); ?></label></th>
			<td>
				<input type="number" id="qmo_chatbot_retention_days" name="qmo_chatbot_retention_days" class="small-text" min="0" max="365"
					value="<?php echo esc_attr( (int) qmo_chatbot_ayar( 'qmo_chatbot_retention_days' ) ); ?>">
				<span><?php esc_html_e( 'günden eski kayıtlar silinsin. 0 = kapat.', 'qrms' ); ?></span>
			</td>
		</tr>
	</table>
	<?php
	qmo_chatbot_form_kapat();
	?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="qmo-cb-filtre">
		<input type="hidden" name="page" value="qrms-chatbot-history">
		<label><?php esc_html_e( 'Başlangıç', 'qrms' ); ?>
			<input type="date" name="baslangic" value="<?php echo esc_attr( $baslangic ); ?>">
		</label>
		<label><?php esc_html_e( 'Bitiş', 'qrms' ); ?>
			<input type="date" name="bitis" value="<?php echo esc_attr( $bitis ); ?>">
		</label>
		<label><?php esc_html_e( 'Masa', 'qrms' ); ?>
			<select name="masa">
				<option value=""><?php esc_html_e( 'Tümü', 'qrms' ); ?></option>
				<?php foreach ( $masalar as $m ) : ?>
					<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $masa, $m ); ?>><?php echo esc_html( $m ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e( 'Ara', 'qrms' ); ?>
			<input type="search" name="s" value="<?php echo esc_attr( $arama ); ?>">
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'qrms' ); ?></button>
	</form>

	<p>
		<button type="button" class="button" id="qmo-cb-toplu-sil" data-nonce="<?php echo esc_attr( wp_create_nonce( 'qmo_chatbot_gecmis' ) ); ?>">
			<?php esc_html_e( 'Seçilenleri sil', 'qrms' ); ?>
		</button>
	</p>

	<table class="widefat striped" id="qmo-cb-gecmis-tablo">
		<thead>
			<tr>
				<td class="check-column"><input type="checkbox" id="qmo-cb-gecmis-hepsi"></td>
				<th><?php esc_html_e( 'Tarih / saat', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Masa', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Ziyaretçinin sorusu', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Botun cevabı', 'qrms' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $sonuc['satirlar'] ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Kayıt yok.', 'qrms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $sonuc['satirlar'] as $satir ) : ?>
					<tr class="qmo-cb-gecmis-satir" data-oturum="<?php echo esc_attr( $satir->oturum_id ); ?>">
						<th class="check-column"><input type="checkbox" class="qmo-cb-gecmis-sec" value="<?php echo (int) $satir->id; ?>"></th>
						<td><?php echo esc_html( $satir->created_at ); ?></td>
						<td><?php echo esc_html( $satir->masa_no ? $satir->masa_no : '—' ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $satir->soru, 16, '…' ) ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $satir->cevap, 16, '…' ) ); ?></td>
						<td>
							<button type="button" class="button-link qmo-cb-tek-sil" data-id="<?php echo (int) $satir->id; ?>"><?php esc_html_e( 'Sil', 'qrms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $sayfa_s > 1 ) : ?>
		<p class="qmo-cb-sayfalama">
			<?php
			for ( $i = 1; $i <= $sayfa_s; $i++ ) {
				$url = add_query_arg(
					array(
						'page'      => 'qrms-chatbot-history',
						'baslangic' => $baslangic,
						'bitis'     => $bitis,
						'masa'      => $masa,
						's'         => $arama,
						'paged'     => $i,
					),
					admin_url( 'admin.php' )
				);
				if ( $i === $sayfa ) {
					echo '<strong>' . (int) $i . '</strong> ';
				} else {
					echo '<a href="' . esc_url( $url ) . '">' . (int) $i . '</a> ';
				}
			}
			?>
		</p>
	<?php endif; ?>

	<div id="qmo-cb-oturum-modal" class="qmo-cb-modal" hidden>
		<div class="qmo-cb-modal-box">
			<button type="button" class="button-link qmo-cb-modal-kapat">&times;</button>
			<h2><?php esc_html_e( 'Yazışma', 'qrms' ); ?></h2>
			<div id="qmo-cb-oturum-govde"></div>
		</div>
	</div>
	<?php
	qmo_chatbot_sayfa_bitir();
}
