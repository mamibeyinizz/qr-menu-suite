<?php
/**
 * Görünürlük alt sayfası.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kimlere / hangi cihazda / mesai dışı davranış.
 *
 * @return void
 */
function qmo_chatbot_sayfa_gorunurluk() {
	qmo_chatbot_sayfa_basligi(
		__( 'Görünürlük', 'qrms' ),
		__( 'Asistanın kimlere ve ne zaman görüneceğini buradan seçin.', 'qrms' )
	);

	$saatler_var = function_exists( 'qrms_cs_is_open_at' );

	qmo_chatbot_form_ac();
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Kimlere gösterilsin', 'qrms' ); ?></th>
			<td>
				<?php
				qmo_chatbot_secenek_grup(
					'qmo_chatbot_audience',
					qmo_chatbot_ayar( 'qmo_chatbot_audience' ),
					array(
						'session' => __( 'Sadece masa oturumu olanlara', 'qrms' ),
						'all'     => __( 'Tüm ziyaretçilere', 'qrms' ),
					)
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Hangi cihazlarda', 'qrms' ); ?></th>
			<td>
				<?php
				qmo_chatbot_secenek_grup(
					'qmo_chatbot_devices',
					qmo_chatbot_ayar( 'qmo_chatbot_devices' ),
					array(
						'phone'   => __( 'Telefon', 'qrms' ),
						'desktop' => __( 'Masaüstü', 'qrms' ),
						'both'    => __( 'Her ikisi', 'qrms' ),
					)
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Çalışma saatleri', 'qrms' ); ?></th>
			<td>
				<?php if ( $saatler_var ) : ?>
					<?php qmo_chatbot_ac_kapa( 'qmo_chatbot_hide_after_hours', qmo_chatbot_ayar( 'qmo_chatbot_hide_after_hours' ), __( 'Çalışma saatleri dışında gizle', 'qrms' ) ); ?>
					<p class="description"><?php esc_html_e( 'Saatler Çalışma Saatleri modülünden okunur.', 'qrms' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Bu seçenek için Çalışma Saatleri modülünün açık olması gerekir.', 'qrms' ); ?></p>
					<input type="hidden" name="qmo_chatbot_hide_after_hours" value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_hide_after_hours' ) ); ?>">
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Kapalıyken davranış', 'qrms' ); ?></th>
			<td>
				<?php
				qmo_chatbot_secenek_grup(
					'qmo_chatbot_closed_behavior',
					qmo_chatbot_ayar( 'qmo_chatbot_closed_behavior' ),
					array(
						'hide'    => __( 'Tamamen gizle', 'qrms' ),
						'message' => __( 'İkon görünsün ama tıklanınca mesaj versin', 'qrms' ),
					)
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="qmo_chatbot_closed_message"><?php esc_html_e( 'Kapalıyken gösterilecek mesaj', 'qrms' ); ?></label></th>
			<td>
				<input type="text" id="qmo_chatbot_closed_message" name="qmo_chatbot_closed_message" class="large-text"
					value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_closed_message' ) ); ?>">
			</td>
		</tr>
	</table>
	<?php
	qmo_chatbot_form_kapat();
	qmo_chatbot_sayfa_bitir();
}
