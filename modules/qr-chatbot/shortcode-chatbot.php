<?php
/**
 * Kısa kod: [gemini_chatbot] — tam ekran AI asistan.
 *
 * OTURUM KOŞULU: Chatbot yalnızca geçerli bir masa oturumu varken render
 * edilir. Oturum yoksa "masadaki QR'ı okutun" bilgi kutusu basılır.
 * Koşul SAYFA bazlı değil OTURUM bazlıdır — böylece menü hangi sayfada
 * olursa olsun aynı kural işler.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'gemini_chatbot', 'qmo_chatbot_shortcode' );

/**
 * Chatbot çıktısı.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_shortcode' ) ) {
	function qmo_chatbot_shortcode() {

		// Oturum yoksa asistan açılmaz — Gemini maliyeti ve sipariş akışı
		// yalnızca masadaki müşteriye aittir.
		if ( ! qmo_oturum() ) {
			return qmo_oturum_uyari_kutusu( 'Asistanı kullanmak için masanızdaki QR kodu okutun.' );
		}

		// Renk değişkenleri qmo_asset_enqueue() içinde stille birlikte eklenir.
		qmo_asset_enqueue( 'qmo-chatbot' );

		$bot_adi   = get_option( 'gemini_bot_name', 'Asistan' );
		$placeholder = get_option( 'gemini_placeholder_text', 'Bir şeyler sorun...' );
		$karsilama = get_option( 'gemini_welcome_text', 'Merhaba! Size nasıl yardımcı olabilirim?' );
		$metin_goster = 'yes' === get_option( 'gemini_show_toggle_text', 'yes' );
		$ikon_url  = get_option( 'gemini_bot_icon', '' );

		ob_start();
		?>
		<div class="gemini-shortcode-container">
			<div class="gemini-chat-toggle-btn" role="button" tabindex="0" aria-label="<?php echo esc_attr( $bot_adi ); ?>">
				<div class="gemini-icon-wrapper">
					<?php qmo_chatbot_ikon( $ikon_url ); ?>
				</div>
				<?php if ( $metin_goster ) : ?>
					<span><?php echo esc_html( $bot_adi ); ?></span>
				<?php endif; ?>
			</div>

			<div class="gemini-chat-overlay">
				<div class="gemini-chat-header">
					<div class="gemini-chat-header-left">
						<div class="gemini-icon-wrapper">
							<?php qmo_chatbot_ikon( $ikon_url ); ?>
						</div>
						<div class="gemini-header-textblock">
							<span><?php echo esc_html( $bot_adi ); ?></span>
							<span class="gemini-header-status">Çevrimiçi</span>
						</div>
					</div>
					<button type="button" class="gemini-chat-close" aria-label="Kapat">&times;</button>
				</div>

				<div class="gemini-chat-log">
					<div class="gemini-msg-bubble gemini-msg-bot"><?php echo esc_html( $karsilama ); ?></div>
				</div>

				<div class="gemini-chat-input-area">
					<input type="text" class="gemini-chat-input" maxlength="1000"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						aria-label="<?php echo esc_attr( $placeholder ); ?>" />
					<button type="button" class="gemini-chat-send" aria-label="Gönder">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

/**
 * Chatbot ikonu (yüklenmiş logo veya varsayılan SVG).
 *
 * @param string $ikon_url Logo adresi.
 */
if ( ! function_exists( 'qmo_chatbot_ikon' ) ) {
	function qmo_chatbot_ikon( $ikon_url ) {
		if ( $ikon_url ) {
			printf( '<img src="%s" alt="" />', esc_url( $ikon_url ) );
			return;
		}
		echo wp_kses( qmo_varsayilan_ikon(), qmo_svg_kses() );
	}
}

/**
 * Ayarlardan gelen renk/boyut değerlerini CSS değişkenine çevirir.
 *
 * Statik chatbot.css yalnızca var(--gm-*) okur; değerler burada üretilir.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_chatbot_degiskenleri' ) ) {
	function qmo_chatbot_degiskenleri() {
		$d = qmo_renk_varsayilanlari();

		/**
		 * Renk option'ını güvenli biçimde oku.
		 *
		 * @param string $key Option adı.
		 * @return string
		 */
		$renk = function ( $key ) use ( $d ) {
			$deger = get_option( $key, $d[ $key ] );
			// Yalnızca hex renk kabul et — CSS enjeksiyonu olmasın.
			$temiz = sanitize_hex_color( $deger );
			return $temiz ? $temiz : $d[ $key ];
		};

		$ikon_boyut = max( 30, (int) get_option( 'gemini_icon_size', 24 ) );
		$radius     = max( 14, (int) get_option( 'gemini_border_radius', 16 ) );
		$toggle_pad = ( 'yes' === get_option( 'gemini_show_toggle_text', 'yes' ) ) ? '13px 26px 13px 14px' : '14px';

		$degiskenler = array(
			'--gm-toggle-bg'     => $renk( 'gemini_toggle_bg_color' ),
			'--gm-toggle-text'   => $renk( 'gemini_toggle_text_color' ),
			'--gm-header-bg'     => $renk( 'gemini_header_bg_color' ),
			'--gm-header-text'   => $renk( 'gemini_header_text_color' ),
			'--gm-header-icon'   => $renk( 'gemini_header_icon_color' ),
			'--gm-text'          => $renk( 'gemini_text_color' ),
			'--gm-border'        => $renk( 'gemini_border_color' ),
			'--gm-chat-bg'       => $renk( 'gemini_chat_bg_color' ),
			'--gm-user-bg'       => $renk( 'gemini_user_msg_color' ),
			'--gm-user-text'     => $renk( 'gemini_user_msg_text_color' ),
			'--gm-bot-bg'        => $renk( 'gemini_bot_msg_color' ),
			'--gm-bot-text'      => $renk( 'gemini_bot_msg_text_color' ),
			'--gm-input-bg'      => $renk( 'gemini_input_bg_color' ),
			'--gm-input-area-bg' => $renk( 'gemini_input_area_bg_color' ),
			'--gm-send-bg'       => $renk( 'gemini_send_btn_bg_color' ),
			'--gm-send-icon'     => $renk( 'gemini_send_btn_icon_color' ),
			'--gm-radius'        => $radius . 'px',
			'--gm-icon-size'     => $ikon_boyut . 'px',
			'--gm-toggle-pad'    => $toggle_pad,
		);

		$satirlar = '';
		foreach ( $degiskenler as $ad => $deger ) {
			$satirlar .= $ad . ':' . $deger . ';';
		}

		// Overlay JS ile body'ye taşındığı için değişkenler onun üzerinde de
		// tanımlanmalı; aksi halde miras kopar ve arkaplanlar şeffaflaşır.
		return '.gemini-shortcode-container,.gemini-chat-overlay{' . $satirlar . '}';
	}
}

/**
 * Kısa kod çıktısında izin verilen SVG etiketleri.
 *
 * @return array
 */
if ( ! function_exists( 'qmo_svg_kses' ) ) {
	function qmo_svg_kses() {
		return array(
			'svg'  => array(
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'stroke'      => true,
				'style'       => true,
				'class'       => true,
				'aria-hidden' => true,
				'xmlns'       => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
			),
			'path' => array(
				'd'                 => true,
				'fill'              => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
			),
		);
	}
}
