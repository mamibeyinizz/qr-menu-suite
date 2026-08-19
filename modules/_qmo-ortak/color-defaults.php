<?php
/**
 * Chatbot renk varsayılanları ve hazır şablonlar.
 *
 * Option adları DEĞİŞTİRİLMEDİ (gemini_*) — canlı sitelerde kayıtlı renk
 * tercihleri korunsun diye. Yalnızca fonksiyon adları qmo_ önekine taşındı;
 * eski adlar geriye dönük uyumluluk için sarmalayıcı olarak duruyor.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'qmo_varsayilan_ikon' ) ) {
	function qmo_varsayilan_ikon() {
		// "AI sparkle" ikonu — Gemini/asistan hissi veren, büyük+küçük dört uçlu yıldız ikilisi.
		// fill="currentColor" ile hem toggle butonda hem header'da o alanın metin rengini otomatik alır.
		return '<svg viewBox="0 0 24 24" width="100%" height="100%" fill="currentColor" style="display:block;"><path d="M11.2 2.7c.42 3.02 1.18 5.2 2.28 6.63 1.46 1.9 3.55 2.98 6.62 3.67-3.07.69-5.16 1.77-6.62 3.67-1.1 1.43-1.86 3.61-2.28 6.63-.42-3.02-1.18-5.2-2.28-6.63-1.46-1.9-3.55-2.98-6.62-3.67 3.07-.69 5.16-1.77 6.62-3.67 1.1-1.43 1.86-3.61 2.28-6.63z"/><path d="M18.7 1c.2 1.36.57 2.35 1.07 3 .52.68 1.28 1.13 2.43 1.42-1.15.29-1.91.74-2.43 1.42-.5.65-.87 1.64-1.07 3-.2-1.36-.57-2.35-1.07-3-.52-.68-1.28-1.13-2.43-1.42 1.15-.29 1.91-.74 2.43-1.42.5-.65.87-1.64 1.07-3z"/></svg>';
	}
}

if ( ! function_exists( 'qmo_renk_varsayilanlari' ) ) {
	/**
	 * Tüm renk ayarlarının varsayılan değerlerini tek yerden döndürür.
	 * Hem ayarlar sayfasında hem de shortcode çıktısında kullanılır,
	 * böylece "varsayılan" tek bir kaynaktan yönetilir.
	 */
	function qmo_renk_varsayilanlari() {
		return array(
			'gemini_main_color'            => '#8a2be2',
			'gemini_toggle_bg_color'       => '#8a2be2',
			'gemini_toggle_text_color'     => '#ffffff',

			'gemini_header_bg_color'       => '#8a2be2',
			'gemini_header_text_color'     => '#ffffff',
			'gemini_header_icon_color'     => '#ffffff',

			'gemini_text_color'            => '#333333',
			'gemini_border_color'          => '#e2e8f0',
			'gemini_bg_color'              => '#f8fafc',
			'gemini_chat_bg_color'         => '#f8fafc',

			'gemini_user_msg_color'        => '#e1f5fe',
			'gemini_user_msg_text_color'   => '#1a1a1a',
			'gemini_bot_msg_color'         => '#ffffff',
			'gemini_bot_msg_text_color'    => '#1a1a1a',

			'gemini_input_bg_color'        => '#f8fafc',
			'gemini_input_area_bg_color'   => '#ffffff',
			'gemini_send_btn_bg_color'     => '#8a2be2',
			'gemini_send_btn_icon_color'   => '#ffffff',
		);
	}
}

if ( ! function_exists( 'qmo_renk_sablonlari' ) ) {
	/**
	 * 3 hazır premium renk şablonu.
	 * Anahtarlar gemini_get_color_defaults() ile birebir aynı isimlerde olmalı.
	 */
	function qmo_renk_sablonlari() {
		return array(
			'royal_violet' => array(
				'label' => 'Royal Violet & Gold',
				'description' => 'Mor-altın temalı, mevcut buton tasarımıyla uyumlu premium görünüm.',
				'preview' => array('#8a2be2', '#ffd700', '#111111'),
				'colors' => array(
					'gemini_main_color'            => '#8a2be2',
					'gemini_toggle_bg_color'       => '#8a2be2',
					'gemini_toggle_text_color'     => '#ffd700',

					'gemini_header_bg_color'       => '#111111',
					'gemini_header_text_color'     => '#ffd700',
					'gemini_header_icon_color'     => '#ffffff',

					'gemini_text_color'            => '#2b2b2b',
					'gemini_border_color'          => '#e6d9ff',
					'gemini_bg_color'              => '#faf7ff',
					'gemini_chat_bg_color'         => '#faf7ff',

					'gemini_user_msg_color'        => '#f3e8ff',
					'gemini_user_msg_text_color'   => '#3b0764',
					'gemini_bot_msg_color'         => '#ffffff',
					'gemini_bot_msg_text_color'    => '#1a1a1a',

					'gemini_input_bg_color'        => '#faf7ff',
					'gemini_input_area_bg_color'   => '#ffffff',
					'gemini_send_btn_bg_color'     => '#8a2be2',
					'gemini_send_btn_icon_color'   => '#ffd700',
				),
			),
			'emerald_noir' => array(
				'label' => 'Emerald Noir',
				'description' => 'Koyu zümrüt yeşili + siyah, lüks restoran/cafe hissi.',
				'preview' => array('#0f3d2e', '#1d9e6f', '#0a0a0a'),
				'colors' => array(
					'gemini_main_color'            => '#1d9e6f',
					'gemini_toggle_bg_color'       => '#0f3d2e',
					'gemini_toggle_text_color'     => '#ffffff',

					'gemini_header_bg_color'       => '#0f3d2e',
					'gemini_header_text_color'     => '#ffffff',
					'gemini_header_icon_color'     => '#ffffff',

					'gemini_text_color'            => '#1a1a1a',
					'gemini_border_color'          => '#d7ece3',
					'gemini_bg_color'              => '#f3faf7',
					'gemini_chat_bg_color'         => '#f3faf7',

					'gemini_user_msg_color'        => '#d6f3e6',
					'gemini_user_msg_text_color'   => '#0f3d2e',
					'gemini_bot_msg_color'         => '#ffffff',
					'gemini_bot_msg_text_color'    => '#1a1a1a',

					'gemini_input_bg_color'        => '#f3faf7',
					'gemini_input_area_bg_color'   => '#ffffff',
					'gemini_send_btn_bg_color'     => '#1d9e6f',
					'gemini_send_btn_icon_color'   => '#ffffff',
				),
			),
			'rose_blush' => array(
				'label' => 'Rose Blush',
				'description' => 'Yumuşak pudra pembesi + bordo vurgu, şık ve sıcak.',
				'preview' => array('#b76e79', '#fbe4e8', '#4a1f24'),
				'colors' => array(
					'gemini_main_color'            => '#b76e79',
					'gemini_toggle_bg_color'       => '#b76e79',
					'gemini_toggle_text_color'     => '#ffffff',

					'gemini_header_bg_color'       => '#4a1f24',
					'gemini_header_text_color'     => '#fbe4e8',
					'gemini_header_icon_color'     => '#ffffff',

					'gemini_text_color'            => '#3a2326',
					'gemini_border_color'          => '#f3d4d9',
					'gemini_bg_color'              => '#fff7f8',
					'gemini_chat_bg_color'         => '#fff7f8',

					'gemini_user_msg_color'        => '#fbe4e8',
					'gemini_user_msg_text_color'   => '#4a1f24',
					'gemini_bot_msg_color'         => '#ffffff',
					'gemini_bot_msg_text_color'    => '#1a1a1a',

					'gemini_input_bg_color'        => '#fff7f8',
					'gemini_input_area_bg_color'   => '#ffffff',
					'gemini_send_btn_bg_color'     => '#b76e79',
					'gemini_send_btn_icon_color'   => '#ffffff',
				),
			),
			'dark_mode' => array(
				'label' => 'Dark Mode',
				'description' => 'Siyah zemin + altın vurgu, premium gece teması. Ekran görüntüsündeki ChefAI tasarımıyla birebir.',
				'preview' => array('#0a0a0a', '#c9a84c', '#f5f0e8'),
				'colors' => array(
					'gemini_main_color'            => '#c9a84c',
					'gemini_toggle_bg_color'       => '#0a0a0a',
					'gemini_toggle_text_color'     => '#c9a84c',

					'gemini_header_bg_color'       => '#0a0a0a',
					'gemini_header_text_color'     => '#f5f0e8',
					'gemini_header_icon_color'     => '#c9a84c',

					'gemini_text_color'            => '#f5f0e8',
					'gemini_border_color'          => '#2a2a2a',
					'gemini_bg_color'              => '#0a0a0a',
					'gemini_chat_bg_color'         => '#0a0a0a',

					'gemini_user_msg_color'        => '#2a2a2a',
					'gemini_user_msg_text_color'   => '#f5f0e8',
					'gemini_bot_msg_color'         => '#111111',
					'gemini_bot_msg_text_color'    => '#f5f0e8',

					'gemini_input_bg_color'        => '#111111',
					'gemini_input_area_bg_color'   => '#0a0a0a',
					'gemini_send_btn_bg_color'     => '#c9a84c',
					'gemini_send_btn_icon_color'   => '#0a0a0a',
				),
			),
		);
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk — eski ChatBot eklentisi fonksiyon adları
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'gemini_get_default_operator_icon' ) ) {
	/**
	 * @deprecated qmo_varsayilan_ikon() kullanın.
	 * @return string
	 */
	function gemini_get_default_operator_icon() {
		return qmo_varsayilan_ikon();
	}
}

if ( ! function_exists( 'gemini_get_color_defaults' ) ) {
	/**
	 * @deprecated qmo_renk_varsayilanlari() kullanın.
	 * @return array
	 */
	function gemini_get_color_defaults() {
		return qmo_renk_varsayilanlari();
	}
}

if ( ! function_exists( 'gemini_get_color_presets' ) ) {
	/**
	 * @deprecated qmo_renk_sablonlari() kullanın.
	 * @return array
	 */
	function gemini_get_color_presets() {
		return qmo_renk_sablonlari();
	}
}
