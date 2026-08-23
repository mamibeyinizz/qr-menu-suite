<?php
/**
 * Ayarların kaydı.
 *
 * Tek bir option'da (`splash_screen_options`) saklanır. Bağımsız eklentiden
 * taşınırken option ADI KORUNDU: mevcut kurulumdaki ayarlar göç etmeden,
 * olduğu yerden okunmaya devam eder.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_AE_Settings {

	/**
	 * Bir ayar sayfasının gönderdiği alanları option'a işler.
	 *
	 * ONAY KUTULARI. Bağımsız eklentide dört sekme TEK forma bağlıydı; işaretsiz
	 * bir kutu her kayıtta güvenle "kapalı" demekti. Suite'te sekmeler ayrı
	 * sayfalar olduğu için aynı varsayım veri siler: Ödeme sayfasında yöntem
	 * seçip Davranış sayfasını kaydetmek, POST'ta payment_methods bulunmadığı
	 * için seçimleri sıfırlardı. Bu yüzden onay kutuları yalnızca SAHİBİ SAYFA
	 * gönderildiğinde yazılır — her grup kendi kutularını kendi metodunda ele
	 * alır, diğer grupların anahtarlarına hiç dokunmaz.
	 *
	 * @param array  $options Mevcut ayarlar.
	 * @param string $group   Gönderen sayfa: gorunum|butonlar|odeme|davranis|sosyal.
	 * @return array Kaydedilen ayarlar.
	 */
	private function save_settings( $options, $group ) {
		if ( 'gorunum' === $group ) {
			$options = $this->save_group_gorunum( $options );
		} elseif ( 'butonlar' === $group ) {
			$options = $this->save_group_butonlar( $options );
		} elseif ( 'odeme' === $group ) {
			$options = $this->save_group_odeme( $options );
		} elseif ( 'davranis' === $group ) {
			$options = $this->save_group_davranis( $options );
		} elseif ( 'sosyal' === $group ) {
			$options = $this->save_group_sosyal( $options );
		} else {
			return $options;
		}

		update_option( $this->option_name, $options );

		return $options;
	}

	/**
	 * Görünüm: arkaplan, renkler, animasyon, logo şeridi, yüklenme göstergesi.
	 */
	private function save_group_gorunum( $options ) {
		$options['bg_color']          = isset($_POST['bg_color']) ? (sanitize_hex_color(wp_unslash($_POST['bg_color'])) ?: $this->defaults['bg_color']) : $options['bg_color'];
		$options['button_bg_color']   = isset($_POST['button_bg_color']) ? (sanitize_hex_color(wp_unslash($_POST['button_bg_color'])) ?: $this->defaults['button_bg_color']) : $options['button_bg_color'];
		$options['button_text_color'] = isset($_POST['button_text_color']) ? (sanitize_hex_color(wp_unslash($_POST['button_text_color'])) ?: $this->defaults['button_text_color']) : $options['button_text_color'];
		$options['animation_type']    = isset($_POST['animation_type']) ? sanitize_text_field(wp_unslash($_POST['animation_type'])) : $options['animation_type'];
		$options['logo']              = isset($_POST['logo']) ? absint($_POST['logo']) : $options['logo'];
		$options['bg_image']          = isset($_POST['bg_image']) ? absint($_POST['bg_image']) : $options['bg_image'];
		$options['bg_image_wide']     = isset($_POST['bg_image_wide']) ? absint($_POST['bg_image_wide']) : $options['bg_image_wide'];

		if ( isset( $_POST['bg_overlay_strength'] ) ) {
			$options['bg_overlay_strength'] = max(0, min(100, absint($_POST['bg_overlay_strength'])));
		}
		if ( isset( $_POST['bg_scheme'] ) ) {
			$scheme = sanitize_key( wp_unslash( $_POST['bg_scheme'] ) );
			$options['bg_scheme'] = in_array($scheme, array('auto', 'light', 'dark'), true) ? $scheme : 'auto';
		}

		if ( isset( $_POST['button_font_size_px'] ) ) {
			$options['button_font_size'] = max(12, min(24, absint($_POST['button_font_size_px']))) . 'px';
		}
		if ( isset( $_POST['button_opacity'] ) ) {
			$options['button_opacity'] = max(0, min(100, absint($_POST['button_opacity'])));
		}

		// --- Buton yüzeyi (rozetler + isteğe bağlı CTA) ---
		if ( isset( $_POST['btn_surface_color'] ) ) {
			$options['btn_surface_color'] = sanitize_hex_color(wp_unslash($_POST['btn_surface_color'])) ?: $this->defaults['btn_surface_color'];
		}
		if ( isset( $_POST['btn_surface_opacity'] ) ) {
			$options['btn_surface_opacity'] = max(0, min(100, absint($_POST['btn_surface_opacity'])));
		}
		$options['btn_surface_apply_cta'] = isset( $_POST['btn_surface_apply_cta'] ) ? 1 : 0;

		// --- Logo şeridi ---
		if ( isset( $_POST['logo_bar_height'] ) ) {
			$options['logo_bar_height'] = max(48, min(220, absint($_POST['logo_bar_height'])));
		}
		if ( isset( $_POST['logo_bar_color'] ) ) {
			$options['logo_bar_color'] = sanitize_hex_color(wp_unslash($_POST['logo_bar_color'])) ?: $this->defaults['logo_bar_color'];
		}
		if ( isset( $_POST['logo_bar_opacity'] ) ) {
			$options['logo_bar_opacity'] = max(0, min(100, absint($_POST['logo_bar_opacity'])));
		}

		// --- Yüklenme göstergesi (logo şeridinin sağı) ---
		if ( isset( $_POST['loader_type'] ) ) {
			$loader_type = sanitize_key( wp_unslash( $_POST['loader_type'] ) );
			$options['loader_type'] = in_array($loader_type, array('spinner', 'ring', 'dots', 'pulse', 'none'), true)
				? $loader_type
				: $this->defaults['loader_type'];
		}
		if ( isset( $_POST['loader_color'] ) ) {
			$options['loader_color'] = sanitize_hex_color(wp_unslash($_POST['loader_color'])) ?: $this->defaults['loader_color'];
		}
		if ( isset( $_POST['loader_size'] ) ) {
			$options['loader_size'] = max(18, min(44, absint($_POST['loader_size'])));
		}
		// Konum şimdilik tek seçenek; option ileriye dönük olarak yazılır.
		$options['loader_position'] = 'top-right';

		// --- Dil seçici (QR Çeviri bayrağı, logo şeridinin solu) ---
		// Bu sayfanın onay kutusu: işaretsizlik ancak GÖRÜNÜM gönderilince
		// "kapalı" demektir (bkz. save_settings başlığı).
		$options['ceviri_selector'] = isset( $_POST['ceviri_selector'] ) ? 1 : 0;

		if ( isset( $_POST['ceviri_flag_size'] ) ) {
			$options['ceviri_flag_size'] = max( 20, min( 48, absint( wp_unslash( $_POST['ceviri_flag_size'] ) ) ) );
		}

		// Eski "Buton İç Boşluğu" anahtarı artık kullanılmıyor.
		unset( $options['button_padding'] );

		// Dil listesi QR Çeviri kapalıyken formda yoktur; o durumda kayıtlı
		// seçimi silmeyiz. Açıkken işaretsizlik "hiçbiri" demektir.
		if ( $this->ceviri_available() ) {
			$valid    = rma_ceviri_aktif_diller();
			$selected = array();
			if ( isset( $_POST['ceviri_selector_langs'] ) && is_array( $_POST['ceviri_selector_langs'] ) ) {
				foreach ( wp_unslash( $_POST['ceviri_selector_langs'] ) as $raw_kod ) {
					// zh-CN gibi kodlar tire taşır; sanitize_key küçük harfe çevirir.
					$kod = sanitize_text_field( $raw_kod );
					if ( in_array( $kod, $valid, true ) && ! in_array( $kod, $selected, true ) ) {
						$selected[] = $kod;
					}
				}
			}
			$options['ceviri_selector_langs'] = $selected;
		}

		return $options;
	}

	/**
	 * Butonlar: ayraç yazısı, altı buton metni, dört bağlantı.
	 */
	private function save_group_butonlar( $options ) {
		if ( isset( $_POST['divider_text'] ) ) {
			$options['divider_text'] = sanitize_text_field( wp_unslash( $_POST['divider_text'] ) );
		}

		// Dil düğmesi bu sayfanın onay kutusudur: işaretsizliği ancak BU sayfa
		// gönderildiğinde "kapalı" demektir (bkz. save_settings başlığı).
		$options['lang_toggle'] = isset( $_POST['lang_toggle'] ) ? 1 : 0;

		for ( $i = 1; $i <= 6; $i++ ) {
			if ( isset( $_POST[ 'button_text_' . $i ] ) ) {
				$options['button_texts'][ 'btn' . $i ] = sanitize_text_field( wp_unslash( $_POST[ 'button_text_' . $i ] ) );
			}
		}
		for ( $i = 1; $i <= 4; $i++ ) {
			if ( isset( $_POST[ 'link_btn' . $i ] ) ) {
				$options['button_links'][ 'btn' . $i ] = esc_url_raw( wp_unslash( $_POST[ 'link_btn' . $i ] ) );
			}
		}

		return $options;
	}

	/**
	 * Ödeme: yöntem seçimi ve satırın render biçimi.
	 */
	private function save_group_odeme( $options ) {
		// Bu sayfa gönderildiği için işaretsizlik "hiçbiri" demektir.
		$valid_methods = array_keys( $this->payment_methods_map() );
		$selected      = array();
		if ( isset( $_POST['payment_methods'] ) && is_array( $_POST['payment_methods'] ) ) {
			foreach ( wp_unslash( $_POST['payment_methods'] ) as $raw_key ) {
				$key = sanitize_key( $raw_key );
				if ( in_array( $key, $valid_methods, true ) && ! in_array( $key, $selected, true ) ) {
					$selected[] = $key;
				}
			}
		}
		$options['payment_methods'] = $selected;

		// Render biçimi: hangi yöntemlerin basılacağı yukarıdaki tikleme
		// sisteminden gelir; bu ayar yalnızca görünümü değiştirir.
		if ( isset( $_POST['payment_display_mode'] ) ) {
			$mode = sanitize_key( wp_unslash( $_POST['payment_display_mode'] ) );
			$options['payment_display_mode'] = in_array($mode, array('icon_text', 'text_only', 'marquee'), true)
				? $mode
				: $this->defaults['payment_display_mode'];
		}
		$options['payment_marquee_with_icon'] = isset( $_POST['payment_marquee_with_icon'] ) ? 1 : 0;
		if ( isset( $_POST['payment_marquee_speed'] ) ) {
			$options['payment_marquee_speed'] = max(6, min(60, absint($_POST['payment_marquee_speed'])));
		}

		return $options;
	}

	/**
	 * Ayarlar: süreler, yönlendirme ve wifi. Sosyal hesaplara dokunmaz.
	 */
	private function save_group_davranis( $options ) {
		$options['redirect_url']     = isset($_POST['redirect_url']) ? esc_url_raw(wp_unslash($_POST['redirect_url'])) : $options['redirect_url'];
		$options['redirect_seconds'] = isset($_POST['redirect_seconds']) ? absint($_POST['redirect_seconds']) : $options['redirect_seconds'];
		$options['dismiss_duration'] = isset($_POST['dismiss_duration']) ? absint($_POST['dismiss_duration']) : $options['dismiss_duration'];
		$options['wifi_password']    = isset($_POST['wifi_password']) ? sanitize_text_field(wp_unslash($_POST['wifi_password'])) : $options['wifi_password'];

		return $options;
	}

	/**
	 * Sosyal medya: aktif hesaplar ve URL'ler.
	 *
	 * Anahtarlar (social_media, social_media_active) Ayarlar sayfasından
	 * taşındı; sanitize kuralları aynıdır.
	 */
	private function save_group_sosyal( $options ) {
		$valid_social  = array_keys( $this->social_media_map() );
		$posted_active = array();
		if ( isset( $_POST['social_media_active'] ) && is_array( $_POST['social_media_active'] ) ) {
			foreach ( wp_unslash( $_POST['social_media_active'] ) as $raw_key ) {
				$key = sanitize_key( $raw_key );
				if ( in_array( $key, $valid_social, true ) && ! in_array( $key, $posted_active, true ) ) {
					$posted_active[] = $key;
				}
			}
		}

		// Render/kayıt sırası admin'de işaretlenme sırasıdır; JS bunu
		// social_media_order gizli alanında tutar. JS çalışmazsa (progressive
		// enhancement) checkbox'ların DOM/POST sırasına düşülür.
		$click_order = array();
		if ( isset( $_POST['social_media_order'] ) ) {
			foreach ( explode( ',', sanitize_text_field( wp_unslash( $_POST['social_media_order'] ) ) ) as $raw_key ) {
				$key = sanitize_key( $raw_key );
				if ( '' !== $key && ! in_array( $key, $click_order, true ) ) {
					$click_order[] = $key;
				}
			}
		}

		$ordered_active = array();
		foreach ( $click_order as $key ) {
			if ( in_array( $key, $posted_active, true ) ) {
				$ordered_active[] = $key;
			}
		}
		foreach ( $posted_active as $key ) {
			if ( ! in_array( $key, $ordered_active, true ) ) {
				$ordered_active[] = $key;
			}
		}
		// Toplamda en fazla 6 platform aktif olabilir; JS zaten 7. checkbox'ı
		// engelliyor, burası savunma amaçlı sunucu tarafı sınırdır.
		$options['social_media_active'] = array_slice( $ordered_active, 0, 6 );

		foreach ( $valid_social as $key ) {
			if ( isset( $_POST[ 'social_media_url_' . $key ] ) ) {
				$options['social_media'][ $key ] = esc_url_raw( wp_unslash( $_POST[ 'social_media_url_' . $key ] ) );
			}
		}

		return $options;
	}
}
