<?php
/**
 * Canlı Sohbetler alt sayfası — admin devralma paneli.
 *
 * Liste ve yazışma tamamen JS ile (admin-canli-sohbet.js) AJAX üzerinden
 * doldurulur; PHP yalnızca iskeleti basar. Liste periyodik olarak
 * yenilendiği için (bkz. JS) statik bir tabloya gerek yok.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canlı sohbet paneli.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_sayfa_canli' ) ) {
	function qmo_chatbot_sayfa_canli() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		QMO_Chatbot_DB::sema_kontrol();

		qmo_chatbot_sayfa_basligi(
			__( 'Canlı Sohbetler', 'qrms' ),
			__( 'Asistanın eskalasyon önerdiği (müşteriye garson çağırma seçeneği gösterilen) sohbetler burada listelenir. Bir satıra tıklayıp müşteriye doğrudan yazabilirsiniz.', 'qrms' )
		);
		?>
		<div id="qmo-canli-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'qmo_chatbot_canli' ) ); ?>">
			<table class="widefat striped" id="qmo-canli-tablo">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Masa', 'qrms' ); ?></th>
						<th><?php esc_html_e( 'Son müşteri mesajı', 'qrms' ); ?></th>
						<th><?php esc_html_e( 'Durum', 'qrms' ); ?></th>
						<th><?php esc_html_e( 'Son aktivite', 'qrms' ); ?></th>
					</tr>
				</thead>
				<tbody id="qmo-canli-govde">
					<tr><td colspan="4"><?php esc_html_e( 'Yükleniyor…', 'qrms' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div id="qmo-canli-modal" class="qmo-cb-modal" hidden>
			<div class="qmo-cb-modal-box">
				<button type="button" class="button-link qmo-cb-modal-kapat" id="qmo-canli-modal-kapat">&times;</button>
				<h2><?php esc_html_e( 'Yazışma', 'qrms' ); ?> — <span id="qmo-canli-modal-masa"></span></h2>
				<div id="qmo-canli-yazisma" class="qmo-canli-yazisma"></div>
				<div class="qmo-canli-yaz-alani">
					<textarea id="qmo-canli-mesaj" rows="2" maxlength="500"
						placeholder="<?php echo esc_attr__( 'Müşteriye yazın…', 'qrms' ); ?>"></textarea>
					<div class="qmo-canli-yaz-butonlar">
						<button type="button" class="button button-primary" id="qmo-canli-gonder"><?php esc_html_e( 'Gönder', 'qrms' ); ?></button>
						<button type="button" class="button" id="qmo-canli-kapat-buton"><?php esc_html_e( 'Sohbeti Kapat', 'qrms' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
		qmo_chatbot_sayfa_bitir();
	}
}
