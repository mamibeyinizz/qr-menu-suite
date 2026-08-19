<?php
/**
 * Yönetim sayfası: QR Menü → QR Chatbot (eski adıyla "Chatbot Ayarları").
 *
 * Eski eklentideki `admin/settings-page.php` buraya taşındı. İki değişiklik
 * dışında içerik aynen korundu:
 *
 *   1. Ayar KAYDI (register_setting) eski eklentide `admin/admin-menu.php`
 *      içindeydi; suite'te modül kendi menüsünü kaydetmediği için kayıt da
 *      sayfanın yanında, bu dosyada durur (qr-masa-oturum-guvenligi ile aynı
 *      düzen).
 *   2. "Firebase / Şube Bağlantısı" bölümü buradan çıkarıldı ve
 *      _qmo-ortak/firebase-ayarlari.php içindeki ortak forma taşındı: aynı
 *      yapılandırmayı qr-analiz de kullanır ve iki modül bağımsız
 *      lisanslanır. Bölüm burada chatbot formunun DIŞINDA, kendi <form>'uyla
 *      basılır.
 *
 * Option adları eskisiyle birebir aynıdır (gemini_*) — canlı sitelerdeki
 * kayıtlı değerler korunur.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

/** Chatbot görünüm/davranış ayarlarının grubu. */
if ( ! defined( 'QMO_CHATBOT_AYAR_GRUBU' ) ) {
	define( 'QMO_CHATBOT_AYAR_GRUBU', 'gemini_chat_settings' );
}

add_action( 'admin_init', 'qmo_chatbot_ayarlarini_kaydet' );

/**
 * Chatbot ayarlarını register_setting ile kaydeder.
 *
 * NOT: Firebase alanları (qmo_branch_id / qmo_firebase_sa / qmo_ana_site) bu
 * grupta DEĞİLDİR; options.php gönderilen grubun tüm option'larını yeniden
 * yazdığı için onlar kendi grubunda durur (bkz. _qmo-ortak/firebase-ayarlari.php).
 *
 * @return void
 */
if ( ! function_exists( 'qmo_chatbot_ayarlarini_kaydet' ) ) {
	function qmo_chatbot_ayarlarini_kaydet() {
		$grup = QMO_CHATBOT_AYAR_GRUBU;

		// Metin ayarları.
		$metin = array(
			'gemini_api_key',
			'gemini_bot_name',
			'gemini_placeholder_text',
			'gemini_welcome_text',
			'gemini_show_toggle_text',
			'gemini_active_preset',
			'qmo_gemini_model',
		);
		foreach ( $metin as $ad ) {
			register_setting(
				$grup,
				$ad,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}

		register_setting(
			$grup,
			'gemini_bot_icon',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			$grup,
			'gemini_system_prompt',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);

		// Sayısal ayarlar.
		foreach ( array( 'gemini_icon_size', 'gemini_border_radius' ) as $ad ) {
			register_setting(
				$grup,
				$ad,
				array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				)
			);
		}

		// Renk ayarları — hepsi hex olarak doğrulanır.
		foreach ( array_keys( qmo_renk_varsayilanlari() ) as $ad ) {
			register_setting(
				$grup,
				$ad,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);
		}
	}
}

/**
 * Ayarlar sayfası.
 */
if ( ! function_exists( 'qmo_ayarlar_sayfasi' ) ) {
	function qmo_ayarlar_sayfasi() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		$ikon_url      = get_option( 'gemini_bot_icon', '' );
		$varsayilanlar = qmo_renk_varsayilanlari();
		$sablonlar     = qmo_renk_sablonlari();
		$aktif_sablon  = get_option( 'gemini_active_preset', '' );

		/**
		 * Kayıtlı değer yoksa varsayılanı kullan.
		 *
		 * @param string $key Option adı.
		 * @return string
		 */
		$val = function ( $key ) use ( $varsayilanlar ) {
			return get_option( $key, $varsayilanlar[ $key ] );
		};
		?>
		<div class="wrap qmo-wrap">
			<h1>QR Menü — Chatbot Ayarları</h1>

			<?php settings_errors(); ?>

			<div id="qmo-toast"></div>

			<form method="post" action="options.php" id="qmo-settings-form">
				<?php settings_fields( QMO_CHATBOT_AYAR_GRUBU ); ?>

				<h2 class="title">Genel Ayarlar</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gemini_api_key">Gemini API Anahtarı</label></th>
						<td>
							<input type="password" id="gemini_api_key" name="gemini_api_key"
								value="<?php echo esc_attr( get_option( 'gemini_api_key' ) ); ?>"
								class="regular-text" autocomplete="off" />
							<p class="description">Chatbot ve sipariş notu çevirisi bu anahtarı kullanır.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qmo_gemini_model">Gemini Modeli</label></th>
						<td>
							<input type="text" id="qmo_gemini_model" name="qmo_gemini_model"
								value="<?php echo esc_attr( get_option( 'qmo_gemini_model', '' ) ); ?>"
								class="regular-text" placeholder="gemini-3-flash-preview" />
							<p class="description">Boş bırakılırsa <code>gemini-3-flash-preview</code> kullanılır.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_bot_name">Chatbot İsmi / Başlık</label></th>
						<td>
							<input type="text" id="gemini_bot_name" name="gemini_bot_name"
								value="<?php echo esc_attr( get_option( 'gemini_bot_name', 'ChatBot' ) ); ?>" size="40" />
							<p class="description">Sohbet penceresinin başlık şeridinde ve kapalıyken toggle buton üzerinde görünür.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_welcome_text">Karşılama Metni (İlk Mesaj)</label></th>
						<td>
							<input type="text" id="gemini_welcome_text" name="gemini_welcome_text"
								value="<?php echo esc_attr( get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' ) ); ?>" size="60" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_placeholder_text">Giriş Alanı (Placeholder) Metni</label></th>
						<td>
							<input type="text" id="gemini_placeholder_text" name="gemini_placeholder_text"
								value="<?php echo esc_attr( get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' ) ); ?>" size="40" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_show_toggle_text">Butonda İsim Yazsın mı?</label></th>
						<td>
							<select id="gemini_show_toggle_text" name="gemini_show_toggle_text">
								<option value="yes" <?php selected( get_option( 'gemini_show_toggle_text', 'yes' ), 'yes' ); ?>>Evet</option>
								<option value="no" <?php selected( get_option( 'gemini_show_toggle_text', 'yes' ), 'no' ); ?>>Hayır</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_bot_icon">Chatbot İkonu / Logo (URL)</label></th>
						<td>
							<input type="text" id="gemini_bot_icon" name="gemini_bot_icon"
								value="<?php echo esc_attr( $ikon_url ); ?>" size="60" />
							<button type="button" class="button" id="qmo-upload-icon">İkon Seç / Yükle</button>
							<p class="description">
								Logo hem toggle buton üzerinde hem sohbet penceresinin başlık kısmında görünür.
								Logonun <strong>arka planı</strong> aşağıdaki "Header Arkaplan Rengi" ile değişir.
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Boyut ve Şekil</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gemini_icon_size">Buton İkon Büyüklüğü (px)</label></th>
						<td><input type="number" id="gemini_icon_size" name="gemini_icon_size" min="16" max="96"
							value="<?php echo esc_attr( get_option( 'gemini_icon_size', '24' ) ); ?>" /> px</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_border_radius">Kenar Yumuşaklığı (px)</label></th>
						<td>
							<input type="number" id="gemini_border_radius" name="gemini_border_radius" min="0" max="40"
								value="<?php echo esc_attr( get_option( 'gemini_border_radius', '16' ) ); ?>" /> px
							<p class="description">Mesaj balonlarının köşe yuvarlaklığı.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">🎨 Hazır Renk Şablonları</h2>
				<p class="description">Bir şablona tıklayın; tüm renk alanları ve sağdaki canlı önizleme anında güncellenir. Kaydetmeyi unutmayın.</p>

				<div class="qmo-preset-grid">
					<?php foreach ( $sablonlar as $key => $sablon ) : ?>
						<div class="qmo-preset-card <?php echo ( $aktif_sablon === $key ) ? 'is-active' : ''; ?>"
							data-preset="<?php echo esc_attr( $key ); ?>">
							<div class="qmo-preset-swatches">
								<?php foreach ( $sablon['preview'] as $renk ) : ?>
									<span class="qmo-swatch" style="background:<?php echo esc_attr( $renk ); ?>;"></span>
								<?php endforeach; ?>
							</div>
							<div class="qmo-preset-info">
								<strong><?php echo esc_html( $sablon['label'] ); ?></strong>
								<p><?php echo esc_html( $sablon['description'] ); ?></p>
							</div>
							<button type="button" class="button button-primary qmo-apply-preset"
								data-preset="<?php echo esc_attr( $key ); ?>">Bu Şablonu Uygula</button>
							<?php if ( $aktif_sablon === $key ) : ?>
								<span class="qmo-active-badge">✓ Aktif</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<input type="hidden" name="gemini_active_preset" id="qmo_active_preset"
					value="<?php echo esc_attr( $aktif_sablon ); ?>" />

				<h2 class="title">Renk Özelleştirmeleri</h2>

				<?php
				$renk_gruplari = array(
					'1) Kapalıyken Görünen Toggle Buton' => array(
						'gemini_toggle_bg_color'   => array( 'Toggle Buton Arkaplan Rengi', 'Sohbet kapalıyken görünen butonun arkaplanı.' ),
						'gemini_toggle_text_color' => array( 'Toggle Buton Yazı/İkon Rengi', 'Buton üzerindeki chatbot ismi yazısının rengi.' ),
					),
					'2) Header (Logo + Başlık Şeridi)'   => array(
						'gemini_header_bg_color'   => array( 'Header Arkaplan Rengi', 'Sohbet açıkken en üstteki logo + isim şeridinin arkaplanı.' ),
						'gemini_header_text_color' => array( 'Header Başlık Yazı Rengi', '' ),
						'gemini_header_icon_color' => array( 'Header Kapatma (×) İkon Rengi', '' ),
					),
					'3) Genel Yazı, Çerçeve ve Zemin'    => array(
						'gemini_text_color'    => array( 'Yazı Rengi (Genel)', '' ),
						'gemini_border_color'  => array( 'Çerçeve Rengi', 'Mesaj balonları, input kutusu ve ayırıcı çizgiler.' ),
						'gemini_chat_bg_color' => array( 'Sohbet Ekranı Arkaplan Rengi', 'Mesajların listelendiği orta alan.' ),
					),
					'4) Mesaj Balonları'                 => array(
						'gemini_user_msg_color'      => array( 'Kullanıcı Mesaj Balonu Rengi', '' ),
						'gemini_user_msg_text_color' => array( 'Kullanıcı Mesaj Yazı Rengi', '' ),
						'gemini_bot_msg_color'       => array( 'Asistan Mesaj Balonu Rengi', '' ),
						'gemini_bot_msg_text_color'  => array( 'Asistan Mesaj Yazı Rengi', '' ),
					),
					'5) Mesaj Yazma (Input) Alanı'       => array(
						'gemini_input_area_bg_color' => array( 'Input Alanı Şerit Arkaplanı', 'En alttaki yazı kutusu + gönder butonu şeridi.' ),
						'gemini_input_bg_color'      => array( 'Yazı Kutusu Arkaplan Rengi', '' ),
						'gemini_send_btn_bg_color'   => array( 'Gönder Butonu Arkaplan Rengi', '' ),
						'gemini_send_btn_icon_color' => array( 'Gönder Butonu İkon Rengi', '' ),
					),
				);

				foreach ( $renk_gruplari as $baslik => $alanlar ) :
					?>
					<h3><?php echo esc_html( $baslik ); ?></h3>
					<table class="form-table" role="presentation">
						<?php foreach ( $alanlar as $ad => $bilgi ) : ?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $ad ); ?>"><?php echo esc_html( $bilgi[0] ); ?></label></th>
								<td>
									<input type="text" id="<?php echo esc_attr( $ad ); ?>" name="<?php echo esc_attr( $ad ); ?>"
										value="<?php echo esc_attr( $val( $ad ) ); ?>" class="qmo-color-picker" />
									<?php if ( $bilgi[1] ) : ?>
										<p class="description"><?php echo esc_html( $bilgi[1] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php
				endforeach;
				?>

				<h2 class="title">Sistem Talimatı</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gemini_system_prompt">System Prompt</label></th>
						<td>
							<textarea id="gemini_system_prompt" name="gemini_system_prompt" rows="10" cols="60"><?php echo esc_textarea( get_option( 'gemini_system_prompt' ) ); ?></textarea>
							<p class="description">Garson/hesap ve sipariş kuralları buna otomatik eklenir; burada yalnızca restoranın kendi tonunu tanımlayın.</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php
			// Firebase/şube bağlantısı: ortak bölüm, kendi ayar grubu ve kendi
			// formu. Chatbot'un garson/hesap çağrısı ve sipariş yazımı buna
			// bağlıdır (QMO_Firestore bu option'ları okur).
			qmo_firebase_ayar_formu();

			// Menü verisi yükleme bölümü (gemini_menu_json_data) bu göçte
			// taşınmadı; kaynak dosyası (admin/menu-data-upload.php) hâlâ eski
			// eklentide. Yan yana çalışıldığında tanımlıysa basılır.
			if ( function_exists( 'qmo_menu_verisi_bolumu' ) ) {
				qmo_menu_verisi_bolumu();
			}
			?>

			<h2 class="title">🔍 Canlı Önizleme</h2>
			<p class="description">Renkleri değiştirdikçe (kaydetmeden) anında güncellenir.</p>

			<div id="qmo-preview-wrap">
				<div id="qmo-preview-header">
					<div id="qmo-preview-header-left">
						<div id="qmo-preview-icon">
							<?php if ( $ikon_url ) : ?>
								<img src="<?php echo esc_url( $ikon_url ); ?>" alt="" />
							<?php else : ?>
								<?php echo wp_kses( qmo_varsayilan_ikon(), qmo_svg_kses() ); ?>
							<?php endif; ?>
						</div>
						<span id="qmo-preview-title"><?php echo esc_html( get_option( 'gemini_bot_name', 'ChatBot' ) ); ?></span>
					</div>
					<span id="qmo-preview-close">&times;</span>
				</div>
				<div id="qmo-preview-log">
					<div class="qmo-preview-bubble" id="qmo-preview-bot-bubble"><?php echo esc_html( get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' ) ); ?></div>
					<div class="qmo-preview-bubble" id="qmo-preview-user-bubble">Merhaba, menüde ne önerirsiniz?</div>
				</div>
				<div id="qmo-preview-input-area">
					<div id="qmo-preview-input"><?php echo esc_html( get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' ) ); ?></div>
					<div id="qmo-preview-send">➤</div>
				</div>
			</div>
		</div>
		<?php
	}
}
