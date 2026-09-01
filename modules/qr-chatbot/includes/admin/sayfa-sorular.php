<?php
/**
 * Hazır Sorular alt sayfası.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hazır soru listesi.
 *
 * @return void
 */
function qmo_chatbot_sayfa_sorular() {
	qmo_chatbot_sayfa_basligi(
		__( 'Hazır Sorular', 'qrms' ),
		__( 'Sohbet açılınca altta çıkan tıklanabilir sorular. Sırayı sürükleyerek değiştirin.', 'qrms' )
	);

	$sorular = qmo_chatbot_sorulari_oku();
	$azami   = (int) qmo_chatbot_ayar( 'qmo_chatbot_quick_max' );

	qmo_chatbot_form_ac();
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="qmo_chatbot_quick_max"><?php esc_html_e( 'En fazla gösterilecek adet', 'qrms' ); ?></label></th>
			<td>
				<input type="number" id="qmo_chatbot_quick_max" name="qmo_chatbot_quick_max" class="small-text" min="1" max="12"
					value="<?php echo esc_attr( $azami ); ?>">
			</td>
		</tr>
	</table>

	<table class="widefat striped" id="qmo-cb-soru-tablo">
		<thead>
			<tr>
				<th class="qmo-cb-drag-col"><?php esc_html_e( 'Sıra', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Görünen metin', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Bota gidecek soru', 'qrms' ); ?></th>
				<th><?php esc_html_e( 'Açık', 'qrms' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody id="qmo-cb-soru-listesi">
			<?php foreach ( $sorular as $i => $satir ) : ?>
				<tr class="qmo-cb-soru-satir" draggable="true">
					<td class="qmo-cb-drag-handle" title="<?php esc_attr_e( 'Sürükle', 'qrms' ); ?>">&#8942;&#8942;</td>
					<td>
						<input type="hidden" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( $satir['id'] ); ?>">
						<input type="text" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][label]" class="regular-text"
							value="<?php echo esc_attr( $satir['label'] ); ?>">
					</td>
					<td>
						<input type="text" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][question]" class="regular-text"
							value="<?php echo esc_attr( $satir['question'] ); ?>">
					</td>
					<td>
						<input type="hidden" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][enabled]" value="0">
						<input type="checkbox" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][enabled]" value="1"
							<?php checked( 1, (int) $satir['enabled'] ); ?>>
					</td>
					<td><button type="button" class="button-link-delete qmo-cb-soru-sil"><?php esc_html_e( 'Sil', 'qrms' ); ?></button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>
		<button type="button" class="button" id="qmo-cb-soru-ekle"><?php esc_html_e( 'Soru ekle', 'qrms' ); ?></button>
	</p>
	<?php
	qmo_chatbot_form_kapat();
	qmo_chatbot_sayfa_bitir();
}
