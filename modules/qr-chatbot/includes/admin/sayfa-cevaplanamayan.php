<?php
/**
 * Cevaplanamayan Sorular alt sayfası.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bilemediği sorular.
 *
 * @return void
 */
function qmo_chatbot_sayfa_cevaplanamayan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
	}

	QMO_Chatbot_DB::sema_kontrol();

	$liste = QMO_Chatbot_DB::bilinmeyen_liste( 'open' );

	qmo_chatbot_sayfa_basligi(
		__( 'Cevaplanamayan Sorular', 'qrms' ),
		__( 'Asistanın bilemediği sorular, tekrar sayısına göre çoktan aza sıralıdır.', 'qrms' )
	);
	?>
	<table class="widefat striped" id="qmo-cb-bilinmeyen-tablo">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Soru', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Tekrar', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Son görülme', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'İşlem', 'qrms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $liste ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Bekleyen soru yok.', 'qrms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $liste as $satir ) : ?>
					<tr data-id="<?php echo (int) $satir->id; ?>">
						<td><?php echo esc_html( $satir->soru ); ?></td>
						<td><?php echo (int) $satir->tekrar; ?></td>
						<td><?php echo esc_html( $satir->last_seen ); ?></td>
						<td>
							<button type="button" class="button qmo-cb-coz" data-id="<?php echo (int) $satir->id; ?>"><?php esc_html_e( 'Çözüldü olarak işaretle', 'qrms' ); ?></button>
							<button type="button" class="button qmo-cb-soruya-ekle" data-id="<?php echo (int) $satir->id; ?>"><?php esc_html_e( 'Hazır Sorulara ekle', 'qrms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
	qmo_chatbot_sayfa_bitir();
}
