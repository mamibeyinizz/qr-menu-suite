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
		__( 'Müşterilerinizin sohbeti başlatmasını kolaylaştıracak soru önerilerini yönetin.', 'qrms' )
	);

	$sorular = qmo_chatbot_sorulari_oku();
	$azami   = (int) qmo_chatbot_ayar( 'qmo_chatbot_quick_max' );

	qmo_chatbot_form_ac();
	?>
	<div class="qmo-cb-wizard">
		<div class="qmo-cb-wizard-main">
			<section class="qmo-cb-card qmo-cb-card-compact">
				<div class="qmo-cb-field">
					<label for="qmo_chatbot_quick_max"><?php esc_html_e( 'Müşteriye kaç soru gösterilsin?', 'qrms' ); ?></label>
					<p class="qmo-cb-field-desc"><?php esc_html_e( 'Sohbet açıldığında müşterilerin göreceği soru sayısını belirleyin.', 'qrms' ); ?></p>
					<input type="number" id="qmo_chatbot_quick_max" name="qmo_chatbot_quick_max" class="small-text" min="1" max="12"
						value="<?php echo esc_attr( $azami ); ?>">
				</div>
			</section>

			<div class="qmo-cb-question-list" id="qmo-cb-soru-listesi" role="list" aria-label="<?php esc_attr_e( 'Hazır sorular', 'qrms' ); ?>">
				<?php foreach ( $sorular as $i => $satir ) : ?>
					<?php qmo_chatbot_soru_karti( $i, $satir ); ?>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="button" class="button button-primary" id="qmo-cb-soru-ekle">+ <?php esc_html_e( 'Yeni Soru', 'qrms' ); ?></button>
			</p>
		</div>

		<?php qmo_chatbot_onizleme_blogu(); ?>
	</div>
	<?php
	qmo_chatbot_form_kapat();
	qmo_chatbot_sayfa_bitir();
}

/**
 * Tek bir hazır soru kartı.
 *
 * "Asistana aynı soruyu gönder" varsayılan AÇIKTIR; yalnızca label !==
 * question olan (daha önce elle özelleştirilmiş) satırlarda kapalı
 * başlar — böylece mevcut özelleştirmeler gizlenmeden görünür kalır.
 * Yeni veri alanı eklenmez: bu tamamen ekrandaki bir çıkarımdır.
 *
 * @param int                                                    $i     Satır index'i (name="...[i]").
 * @param array{id:string,label:string,question:string,enabled:int} $satir Soru verisi.
 * @return void
 */
function qmo_chatbot_soru_karti( $i, $satir ) {
	$ayni  = ( $satir['label'] === $satir['question'] );
	$aktif = ! empty( $satir['enabled'] );
	?>
	<div class="qmo-cb-question-card" draggable="true" role="listitem">
		<div class="qmo-cb-question-head">
			<button type="button" class="qmo-cb-drag-handle" aria-label="<?php esc_attr_e( 'Sürükleyerek sırala', 'qrms' ); ?>">⠿</button>
			<span class="qmo-cb-question-index" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', (int) $i + 1 ) ); ?></span>

			<div class="qmo-cb-question-main">
				<input type="hidden" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( $satir['id'] ); ?>">
				<label class="screen-reader-text" for="qmo-cb-soru-label-<?php echo (int) $i; ?>"><?php esc_html_e( 'Müşteriye gösterilecek soru', 'qrms' ); ?></label>
				<?php qmo_chatbot_i18n_wrap_ac( $satir['label'] ); ?>
				<input type="text" id="qmo-cb-soru-label-<?php echo (int) $i; ?>" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][label]"
					class="qmo-cb-question-label" placeholder="<?php esc_attr_e( 'Müşteriye gösterilecek soru', 'qrms' ); ?>"
					value="<?php echo esc_attr( $satir['label'] ); ?>">
				<?php qmo_chatbot_i18n_wrap_kapat( 'qmo_chatbot_qr.' . $satir['id'] . '.label' ); ?>
			</div>

			<label class="qmo-cb-toggle">
				<input type="hidden" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][enabled]" value="0">
				<input type="checkbox" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][enabled]" value="1"
					<?php checked( $aktif ); ?>>
				<span class="qmo-cb-toggle-ui" aria-hidden="true"></span>
				<span class="qmo-cb-toggle-text"><?php echo $aktif ? esc_html__( 'Aktif', 'qrms' ) : esc_html__( 'Pasif', 'qrms' ); ?></span>
			</label>

			<button type="button" class="button-link qmo-cb-question-edit" aria-expanded="<?php echo $ayni ? 'false' : 'true'; ?>">
				<?php esc_html_e( 'Düzenle', 'qrms' ); ?>
			</button>
			<button type="button" class="qmo-cb-question-delete" aria-label="<?php esc_attr_e( 'Soruyu sil', 'qrms' ); ?>">&times;</button>
		</div>

		<div class="qmo-cb-question-advanced" <?php echo $ayni ? 'hidden' : ''; ?>>
			<label class="qmo-cb-inline-check">
				<input type="checkbox" class="qmo-cb-same-question" <?php checked( $ayni ); ?>>
				<?php esc_html_e( 'Asistana aynı soruyu gönder', 'qrms' ); ?>
			</label>
			<div class="qmo-cb-question-ai" <?php echo $ayni ? 'hidden' : ''; ?>>
				<label for="qmo-cb-soru-question-<?php echo (int) $i; ?>"><?php esc_html_e( 'Asistana gönderilecek soru', 'qrms' ); ?></label>
				<input type="text" id="qmo-cb-soru-question-<?php echo (int) $i; ?>" name="qmo_chatbot_quick_replies[<?php echo (int) $i; ?>][question]"
					class="qmo-cb-question-question regular-text" value="<?php echo esc_attr( $satir['question'] ); ?>">
			</div>
		</div>
	</div>
	<?php
}
