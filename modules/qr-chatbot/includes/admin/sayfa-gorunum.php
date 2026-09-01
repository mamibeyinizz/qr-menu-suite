<?php
/**
 * Görünüm sayfası — 3 adımlı sihirbaz + karşılama ekranı + canlı önizleme.
 *
 * Eski 14 renk alanının name attribute'ları gelişmiş bölümde durur.
 * gemini_icon_size ve gemini_border_radius gizli alan olarak senkron tutulur.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Görünüm sihirbazı.
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_sayfa_gorunum' ) ) {
function qmo_chatbot_sayfa_gorunum() {
	qmo_chatbot_sayfa_basligi(
		__( 'Görünüm', 'qrms' ),
		__( 'İkon, renk ve şekli soldan ayarlayın; sağda anında görün.', 'qrms' )
	);

	$renkler     = qmo_chatbot_renkleri_coz();
	$sablonlar   = qmo_renk_sablonlari();
	$aktif       = (string) get_option( 'gemini_active_preset', '' );
	$bot_adi     = get_option( 'gemini_bot_name', 'Asistan' );
	$karsilama   = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
	$ipucu       = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
	$ikon_url    = get_option( 'gemini_bot_icon', '' );
	$preset      = (string) qmo_chatbot_ayar( 'qmo_chatbot_icon_preset' );
	$boyut       = (string) qmo_chatbot_ayar( 'qmo_chatbot_icon_size_preset' );
	$konum       = (string) qmo_chatbot_ayar( 'qmo_chatbot_position' );
	$yukseklik   = (string) qmo_chatbot_ayar( 'qmo_chatbot_offset' );
	$hareket     = (string) qmo_chatbot_ayar( 'qmo_chatbot_attention' );
	$rozet       = (string) qmo_chatbot_ayar( 'qmo_chatbot_badge' );
	$kose        = (string) qmo_chatbot_ayar( 'qmo_chatbot_radius_preset' );
	$genislik    = (string) qmo_chatbot_ayar( 'qmo_chatbot_window_width' );
	$gelismis    = (string) qmo_chatbot_ayar( 'qmo_chatbot_advanced_colors' );
	$elle        = qmo_chatbot_renk_elle_liste();
	$ikonlar     = qmo_chatbot_hazir_ikonlar();
	$boyut_px    = qmo_chatbot_boyut_haritasi();
	$kose_px     = qmo_chatbot_kose_haritasi();

	$ikon_boyut = isset( $boyut_px[ $boyut ] ) ? $boyut_px[ $boyut ] : 48;
	$kose_deger = isset( $kose_px[ $kose ] ) ? $kose_px[ $kose ] : 16;

	$etiketler = array(
		'gemini_toggle_bg_color'     => __( 'Açma butonu zemini', 'qrms' ),
		'gemini_toggle_text_color'   => __( 'Açma butonu yazısı', 'qrms' ),
		'gemini_header_bg_color'     => __( 'Başlık şeridi', 'qrms' ),
		'gemini_header_text_color'   => __( 'Başlık yazısı', 'qrms' ),
		'gemini_header_icon_color'   => __( 'Başlık ikonu', 'qrms' ),
		'gemini_chat_bg_color'       => __( 'Sohbet zemini', 'qrms' ),
		'gemini_text_color'          => __( 'Yazı rengi', 'qrms' ),
		'gemini_border_color'        => __( 'Kenarlık', 'qrms' ),
		'gemini_user_msg_color'      => __( 'Sizin balonunuz', 'qrms' ),
		'gemini_user_msg_text_color' => __( 'Sizin balon yazınız', 'qrms' ),
		'gemini_bot_msg_color'       => __( 'Asistan balonu', 'qrms' ),
		'gemini_bot_msg_text_color'  => __( 'Asistan balon yazısı', 'qrms' ),
		'gemini_input_bg_color'      => __( 'Yazma kutusu zemini', 'qrms' ),
		'gemini_input_area_bg_color' => __( 'Yazma bölümü zemini', 'qrms' ),
		'gemini_send_btn_bg_color'   => __( 'Gönder butonu', 'qrms' ),
		'gemini_send_btn_icon_color' => __( 'Gönder ikonu', 'qrms' ),
	);

	qmo_chatbot_form_ac();
	?>
	<input type="hidden" name="gemini_active_preset" id="gemini_active_preset" value="<?php echo esc_attr( $aktif ); ?>">
	<input type="hidden" name="gemini_icon_size" id="gemini_icon_size" value="<?php echo esc_attr( $ikon_boyut ); ?>">
	<input type="hidden" name="gemini_border_radius" id="gemini_border_radius" value="<?php echo esc_attr( $kose_deger ); ?>">
	<input type="hidden" name="qmo_chatbot_color_overrides" id="qmo_chatbot_color_overrides" value="<?php echo esc_attr( wp_json_encode( $elle ) ); ?>">
	<input type="hidden" name="gemini_main_color" id="gemini_main_color" value="<?php echo esc_attr( $renkler['gemini_main_color'] ); ?>">

	<div class="qmo-cb-wizard">
		<div class="qmo-cb-wizard-main">
			<nav class="qmo-cb-steps" aria-label="<?php esc_attr_e( 'Görünüm adımları', 'qrms' ); ?>">
				<button type="button" class="qmo-cb-step is-active" data-step="1"><span>1</span> <?php esc_html_e( 'İkon', 'qrms' ); ?></button>
				<button type="button" class="qmo-cb-step" data-step="2"><span>2</span> <?php esc_html_e( 'Renkler', 'qrms' ); ?></button>
				<button type="button" class="qmo-cb-step" data-step="3"><span>3</span> <?php esc_html_e( 'Şekil', 'qrms' ); ?></button>
			</nav>

			<section class="qmo-cb-panel is-active" data-step-panel="1">
				<h2><?php esc_html_e( 'Hazır ikonlar', 'qrms' ); ?></h2>
				<div class="qmo-cb-icon-grid" id="qmo-cb-icon-grid">
					<?php foreach ( $ikonlar as $slug => $ikon ) : ?>
						<button type="button" class="qmo-cb-icon-tile<?php echo $preset === $slug ? ' is-selected' : ''; ?>"
							data-icon="<?php echo esc_attr( $slug ); ?>"
							aria-pressed="<?php echo $preset === $slug ? 'true' : 'false'; ?>">
							<span class="qmo-cb-icon-tile-svg"><?php echo wp_kses( $ikon['svg'], qmo_svg_kses() ); ?></span>
							<span><?php echo esc_html( $ikon['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
					<button type="button" class="qmo-cb-icon-tile<?php echo 'custom' === $preset ? ' is-selected' : ''; ?>"
						data-icon="custom" aria-pressed="<?php echo 'custom' === $preset ? 'true' : 'false'; ?>">
						<span class="qmo-cb-icon-tile-svg" aria-hidden="true">+</span>
						<span><?php esc_html_e( 'Kendi görselimi yükleyeceğim', 'qrms' ); ?></span>
					</button>
				</div>
				<input type="hidden" name="qmo_chatbot_icon_preset" id="qmo_chatbot_icon_preset" value="<?php echo esc_attr( $preset ); ?>">

				<div class="qmo-cb-custom-icon<?php echo 'custom' === $preset ? ' is-open' : ''; ?>" id="qmo-cb-custom-icon">
					<label for="gemini_bot_icon"><?php esc_html_e( 'Görsel adresi', 'qrms' ); ?></label>
					<div class="qmo-cb-inline">
						<input type="url" id="gemini_bot_icon" name="gemini_bot_icon" class="regular-text"
							value="<?php echo esc_url( $ikon_url ); ?>">
						<button type="button" class="button" id="qmo-icon-upload"><?php esc_html_e( 'Medya Kütüphanesinden Seç', 'qrms' ); ?></button>
					</div>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qmo_chatbot_icon_color"><?php esc_html_e( 'İkon rengi', 'qrms' ); ?></label></th>
						<td>
							<input type="color" id="qmo_chatbot_icon_color" name="qmo_chatbot_icon_color"
								value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_icon_color' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qmo_chatbot_icon_bg_color"><?php esc_html_e( 'İkon arka plan rengi', 'qrms' ); ?></label></th>
						<td>
							<input type="color" id="qmo_chatbot_icon_bg_color" name="qmo_chatbot_icon_bg_color"
								value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_icon_bg_color' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Boyut', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_secenek_grup( 'qmo_chatbot_icon_size_preset', $boyut, array( 'small' => __( 'Küçük', 'qrms' ), 'medium' => __( 'Orta', 'qrms' ), 'large' => __( 'Büyük', 'qrms' ) ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Konum', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_secenek_grup( 'qmo_chatbot_position', $konum, array( 'right' => __( 'Sağ alt köşe', 'qrms' ), 'left' => __( 'Sol alt köşe', 'qrms' ) ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Yerden yükseklik', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_secenek_grup( 'qmo_chatbot_offset', $yukseklik, array( 'low' => __( 'Az', 'qrms' ), 'mid' => __( 'Orta', 'qrms' ), 'high' => __( 'Çok', 'qrms' ) ) ); ?>
							<p class="description"><?php esc_html_e( 'Orta seçilince ikon, Garson Çağır ve Hesap İste butonlarının biraz üstünde durur.', 'qrms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dikkat çekme hareketi', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_secenek_grup( 'qmo_chatbot_attention', $hareket, array( 'none' => __( 'Yok', 'qrms' ), 'pulse' => __( 'Hafif nabız', 'qrms' ), 'shake' => __( 'Sallanma', 'qrms' ) ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Okunmamış rozeti', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_ac_kapa( 'qmo_chatbot_badge', $rozet, __( 'Yeni cevap veya karşılama balonu çıktığında ikonun üstünde kırmızı nokta görünsün.', 'qrms' ) ); ?>
						</td>
					</tr>
				</table>
			</section>

			<section class="qmo-cb-panel" data-step-panel="2">
				<h2><?php esc_html_e( 'Hazır Şablonlar', 'qrms' ); ?></h2>
				<div class="qmo-preset-grid">
					<?php foreach ( $sablonlar as $slug => $sablon ) : ?>
						<div class="qmo-preset-card<?php echo $aktif === $slug ? ' is-active' : ''; ?>"
							data-preset="<?php echo esc_attr( $slug ); ?>">
							<?php if ( $aktif === $slug ) : ?>
								<span class="qmo-active-badge"><?php esc_html_e( 'Aktif', 'qrms' ); ?></span>
							<?php endif; ?>
							<div class="qmo-preset-swatches">
								<?php foreach ( $sablon['preview'] as $swatch ) : ?>
									<span class="qmo-swatch" style="background:<?php echo esc_attr( $swatch ); ?>;"></span>
								<?php endforeach; ?>
							</div>
							<div class="qmo-preset-info">
								<strong><?php echo esc_html( $sablon['label'] ); ?></strong>
								<p><?php echo esc_html( $sablon['description'] ); ?></p>
							</div>
							<button type="button" class="button qmo-apply-preset" data-preset="<?php echo esc_attr( $slug ); ?>">
								<?php esc_html_e( 'Şablonu Uygula', 'qrms' ); ?>
							</button>
						</div>
					<?php endforeach; ?>
				</div>

				<h2><?php esc_html_e( 'Üç ana renk', 'qrms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qmo_cb_ana"><?php esc_html_e( 'Ana renk', 'qrms' ); ?></label></th>
						<td>
							<input type="color" id="qmo_cb_ana" class="qmo-cb-ana-renk" data-main="gemini_main_color"
								value="<?php echo esc_attr( $renkler['gemini_main_color'] ); ?>">
							<p class="description"><?php esc_html_e( 'Başlık şeridi, gönder butonu ve sizin balonunuz.', 'qrms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_chat_bg_color"><?php esc_html_e( 'Sohbet zemini', 'qrms' ); ?></label></th>
						<td>
							<input type="color" id="gemini_chat_bg_color" name="gemini_chat_bg_color" class="qmo-color-input qmo-cb-ana-renk"
								data-color-key="gemini_chat_bg_color" value="<?php echo esc_attr( $renkler['gemini_chat_bg_color'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_text_color"><?php esc_html_e( 'Yazı rengi', 'qrms' ); ?></label></th>
						<td>
							<input type="color" id="gemini_text_color" name="gemini_text_color" class="qmo-color-input qmo-cb-ana-renk"
								data-color-key="gemini_text_color" value="<?php echo esc_attr( $renkler['gemini_text_color'] ); ?>">
						</td>
					</tr>
				</table>

				<details class="qmo-cb-advanced" <?php echo 'yes' === $gelismis ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Gelişmiş renk ayarları', 'qrms' ); ?></summary>
					<input type="hidden" name="qmo_chatbot_advanced_colors" id="qmo_chatbot_advanced_colors" value="<?php echo esc_attr( $gelismis ); ?>">
					<p class="description"><?php esc_html_e( 'Bir rengi elle değiştirirseniz o alan artık otomatik türetilmez.', 'qrms' ); ?></p>
					<table class="form-table" role="presentation">
						<?php foreach ( $etiketler as $anahtar => $etiket ) : ?>
							<?php if ( in_array( $anahtar, array( 'gemini_chat_bg_color', 'gemini_text_color' ), true ) ) { continue; } ?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $anahtar ); ?>"><?php echo esc_html( $etiket ); ?></label></th>
								<td>
									<input type="color" id="<?php echo esc_attr( $anahtar ); ?>" name="<?php echo esc_attr( $anahtar ); ?>"
										class="qmo-color-input qmo-cb-adv-renk" data-color-key="<?php echo esc_attr( $anahtar ); ?>"
										value="<?php echo esc_attr( $renkler[ $anahtar ] ); ?>">
									<code><?php echo esc_html( $renkler[ $anahtar ] ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</details>

				<p>
					<button type="button" class="button" id="qmo-cb-reset-colors"><?php esc_html_e( 'Varsayılana dön', 'qrms' ); ?></button>
				</p>
			</section>

			<section class="qmo-cb-panel" data-step-panel="3">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Köşe yumuşaklığı', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_secenek_grup( 'qmo_chatbot_radius_preset', $kose, array( 'sharp' => __( 'Keskin', 'qrms' ), 'soft' => __( 'Yumuşak', 'qrms' ), 'round' => __( 'Tam yuvarlak', 'qrms' ) ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pencere genişliği', 'qrms' ); ?></th>
						<td>
							<?php qmo_chatbot_secenek_grup( 'qmo_chatbot_window_width', $genislik, array( 'narrow' => __( 'Dar', 'qrms' ), 'normal' => __( 'Normal', 'qrms' ), 'wide' => __( 'Geniş', 'qrms' ) ) ); ?>
						</td>
					</tr>
				</table>
			</section>

			<h2><?php esc_html_e( 'Karşılama ekranı', 'qrms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Giriş ekranı', 'qrms' ); ?></th>
					<td>
						<?php qmo_chatbot_ac_kapa( 'qmo_chatbot_welcome_screen', qmo_chatbot_ayar( 'qmo_chatbot_welcome_screen' ), __( 'Sohbet ilk açıldığında bot adı, ikon ve kısa tanıtım gösterilsin.', 'qrms' ) ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_chatbot_welcome_intro"><?php esc_html_e( 'Tanıtım metni', 'qrms' ); ?></label></th>
					<td>
						<textarea id="qmo_chatbot_welcome_intro" name="qmo_chatbot_welcome_intro" rows="3" class="large-text"><?php echo esc_textarea( qmo_chatbot_ayar( 'qmo_chatbot_welcome_intro' ) ); ?></textarea>
						<?php
						if ( function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
							echo rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, 'qmo_chatbot_welcome_intro' ) ) );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_chatbot_welcome_btn"><?php esc_html_e( 'Başla butonu metni', 'qrms' ); ?></label></th>
					<td>
						<input type="text" id="qmo_chatbot_welcome_btn" name="qmo_chatbot_welcome_btn" class="regular-text"
							value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_welcome_btn' ) ); ?>">
						<?php
						if ( function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
							echo rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, 'qmo_chatbot_welcome_btn' ) ) );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Karşılama baloncuğu', 'qrms' ); ?></th>
					<td>
						<?php qmo_chatbot_ac_kapa( 'qmo_chatbot_teaser', qmo_chatbot_ayar( 'qmo_chatbot_teaser' ), __( 'İkonun yanında birkaç saniye sonra küçük bir baloncuk çıksın. Ziyaretçi kapatırsa bu oturumda tekrar çıkmaz.', 'qrms' ) ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_chatbot_teaser_text"><?php esc_html_e( 'Baloncuk metni', 'qrms' ); ?></label></th>
					<td>
						<input type="text" id="qmo_chatbot_teaser_text" name="qmo_chatbot_teaser_text" class="regular-text"
							value="<?php echo esc_attr( qmo_chatbot_ayar( 'qmo_chatbot_teaser_text' ) ); ?>">
						<?php
						if ( function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
							echo rma_ceviri_bayat_uyari_html( rma_ceviri_bayat_uyari_metni( rma_ceviri_veri_dil_sayisi( 'option', 0, 'qmo_chatbot_teaser_text' ) ) );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qmo_chatbot_teaser_delay"><?php esc_html_e( 'Kaç saniye sonra çıksın', 'qrms' ); ?></label></th>
					<td>
						<input type="number" id="qmo_chatbot_teaser_delay" name="qmo_chatbot_teaser_delay" class="small-text" min="1" max="30"
							value="<?php echo esc_attr( (int) qmo_chatbot_ayar( 'qmo_chatbot_teaser_delay' ) ); ?>">
					</td>
				</tr>
			</table>
		</div>

		<aside class="qmo-cb-preview-col" aria-label="<?php esc_attr_e( 'Canlı önizleme', 'qrms' ); ?>">
			<div class="qmo-cb-preview-toolbar">
				<div class="qmo-cb-seg" role="tablist">
					<button type="button" class="is-active" data-preview-device="phone"><?php esc_html_e( 'Telefon', 'qrms' ); ?></button>
					<button type="button" data-preview-device="desktop"><?php esc_html_e( 'Masaüstü', 'qrms' ); ?></button>
				</div>
				<div class="qmo-cb-seg" role="tablist">
					<button type="button" class="is-active" data-preview-state="closed"><?php esc_html_e( 'Kapalı hali', 'qrms' ); ?></button>
					<button type="button" data-preview-state="open"><?php esc_html_e( 'Açık hali', 'qrms' ); ?></button>
				</div>
			</div>
			<div id="qmo-cb-live" class="qmo-cb-live is-phone is-closed">
				<div class="qmo-cb-live-stage">
					<div id="qmo-preview-toggle" class="qmo-cb-live-toggle">
						<span id="qmo-preview-badge" class="qmo-cb-live-badge">1</span>
						<div id="qmo-preview-icon"></div>
					</div>
					<div id="qmo-preview-teaser" class="qmo-cb-live-teaser"><?php echo esc_html( qmo_chatbot_ayar( 'qmo_chatbot_teaser_text' ) ); ?></div>
					<div id="qmo-preview-wrap">
						<div id="qmo-preview-header">
							<div id="qmo-preview-header-left">
								<div id="qmo-preview-header-icon"></div>
								<span id="qmo-preview-title"><?php echo esc_html( $bot_adi ); ?></span>
							</div>
							<span id="qmo-preview-close" aria-hidden="true">&times;</span>
						</div>
						<div id="qmo-preview-welcome" class="qmo-cb-live-welcome">
							<div id="qmo-preview-welcome-icon"></div>
							<strong id="qmo-preview-welcome-name"><?php echo esc_html( $bot_adi ); ?></strong>
							<p id="qmo-preview-welcome-text"><?php echo esc_html( qmo_chatbot_ayar( 'qmo_chatbot_welcome_intro' ) ); ?></p>
							<span id="qmo-preview-welcome-btn"><?php echo esc_html( qmo_chatbot_ayar( 'qmo_chatbot_welcome_btn' ) ); ?></span>
						</div>
						<div id="qmo-preview-log">
							<div id="qmo-preview-bot-bubble" class="qmo-preview-bubble"><?php echo esc_html( $karsilama ); ?></div>
							<div id="qmo-preview-user-bubble" class="qmo-preview-bubble"><?php esc_html_e( 'Örnek kullanıcı mesajı', 'qrms' ); ?></div>
						</div>
						<div id="qmo-preview-chips" class="qmo-cb-live-chips">
							<span><?php esc_html_e( 'Menüde ne var?', 'qrms' ); ?></span>
							<span><?php esc_html_e( 'Şef önerisi', 'qrms' ); ?></span>
						</div>
						<div id="qmo-preview-input-area">
							<input type="text" id="qmo-preview-input" readonly value="<?php echo esc_attr( $ipucu ); ?>">
							<div id="qmo-preview-send">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
							</div>
						</div>
					</div>
				</div>
			</div>
		</aside>
	</div>
	<?php
	qmo_chatbot_form_kapat();
	echo '<div id="qmo-toast"></div>';
	qmo_chatbot_sayfa_bitir();
}
}

/**
 * Hazır seçenek buton grubu (radyo).
 *
 * @param string               $name    Alan adı.
 * @param string               $secili  Seçili değer.
 * @param array<string,string> $secenek Etiketler.
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_secenek_grup' ) ) {
function qmo_chatbot_secenek_grup( $name, $secili, $secenek ) {
	echo '<div class="qmo-cb-seg qmo-cb-seg-input">';
	foreach ( $secenek as $deger => $etiket ) {
		$id = $name . '_' . $deger;
		printf(
			'<label><input type="radio" name="%1$s" id="%2$s" value="%3$s" %4$s> %5$s</label>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $deger ),
			checked( $secili, $deger, false ),
			esc_html( $etiket )
		);
	}
	echo '</div>';
}
}

/**
 * Açık/kapalı onay kutusu.
 *
 * @param string $name   Alan.
 * @param string $deger  yes|no.
 * @param string $etiket Açıklama.
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ac_kapa' ) ) {
function qmo_chatbot_ac_kapa( $name, $deger, $etiket ) {
	echo '<label>';
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="no">';
	echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="yes" ' . checked( 'yes', $deger, false ) . '> ';
	echo esc_html( $etiket );
	echo '</label>';
}
}
