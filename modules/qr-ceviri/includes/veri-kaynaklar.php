<?php
/**
 * P1 yönetici verisi: option / form_field / cf_field / cf_form.
 *
 * Kod sabitlerinden (ui_string, splash, …) farkı: anahtar METİN değil
 * kimliktir. Yönetici metni değiştirince satır kalır; original_hash
 * uyuşmazsa çeviri ön yüzde basılmaz (hash kapısı). Ürün satırlarına
 * yayılmaz.
 *
 * CSV sütunları ve tablo şeması değişmez. item_type varchar(20):
 * option (6), form_field (10), cf_field (8), cf_form (7).
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kimlik anahtarlı veri tipleri (hash kapısı bunlara uygulanır).
 *
 * @return array<string,string> tip => Sistem Durumu etiketi.
 */
if ( ! function_exists( 'rma_ceviri_veri_tipleri' ) ) {
	function rma_ceviri_veri_tipleri() {
		return array(
			'option'     => 'Yönetici ayarları',
			'form_field' => 'Form alanları',
			'cf_field'   => 'Özel form alanları',
			'cf_form'    => 'Özel form metinleri',
		);
	}
}

/**
 * Bu tipte eskimiş çeviri ön yüzde basılmasın mı?
 *
 * Yalnız P1 veri tipleri. product/category/… mevcut davranışı korur.
 *
 * @param string $tip item_type.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_hash_kapisi_mi' ) ) {
	function rma_ceviri_hash_kapisi_mi( $tip ) {
		return isset( rma_ceviri_veri_tipleri()[ $tip ] );
	}
}

/**
 * Hash hesabında kullanılacak metin (alan bazlı normalizasyon).
 *
 * hfb_footer.copyright: yıl dinamik olduğu için 4 haneli yıl → %s.
 *
 * @param string $orijinal Canlı yönetici metni.
 * @param string $field    Defter alanı; boşsa ham metin.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_hash_kaynagi' ) ) {
	function rma_ceviri_hash_kaynagi( $orijinal, $field = '' ) {
		$metin = (string) $orijinal;

		if ( 'hfb_footer.copyright' === $field ) {
			return (string) preg_replace( '/\b(19|20)\d{2}\b/', '%s', $metin, 1 );
		}

		return $metin;
	}
}

/**
 * Hash kapılı alanlar için original_hash üret.
 *
 * @param string $orijinal Canlı yönetici metni.
 * @param string $field    Defter alanı.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_hash_olustur' ) ) {
	function rma_ceviri_hash_olustur( $orijinal, $field = '' ) {
		return md5( rma_ceviri_hash_kaynagi( $orijinal, $field ) );
	}
}

/**
 * Kayıtlı hash canlı metinle uyuşuyor mu?
 *
 * Boş hash = bilinmiyor → kapı kapatır (çeviri basılmaz).
 *
 * @param string $orijinal Canlı yönetici metni.
 * @param string $hash     Tablodaki original_hash.
 * @param string $field    Defter alanı (telif yılı normalizasyonu için).
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_hash_guncel_mi' ) ) {
	function rma_ceviri_hash_guncel_mi( $orijinal, $hash, $field = '' ) {
		$hash = (string) $hash;

		return '' !== $hash && $hash === rma_ceviri_hash_olustur( $orijinal, $field );
	}
}

/**
 * Yönetici option kayıt defteri.
 *
 * field = CSV'deki field (kararlı yol). option = WP option adı.
 * anahtar = iç dizi anahtarı; düz option'da null.
 * yalniz_ozel: varsayılanla aynıysa CSV'ye çıkma (P0 chat/ui_string).
 *
 * @return array<string,array<string,mixed>>
 */
if ( ! function_exists( 'rma_ceviri_option_defteri' ) ) {
	function rma_ceviri_option_defteri() {
		$qrm = function_exists( 'qrm_pro_default_settings' )
			? qrm_pro_default_settings()
			: array();

		$kayit = array(
			'gemini_bot_name'          => array(
				'etiket'     => 'Chatbot: bot adı',
				'option'     => 'gemini_bot_name',
				'anahtar'    => null,
				'varsayilan' => 'Asistan',
			),
			'gemini_placeholder_text'  => array(
				'etiket'     => 'Chatbot: giriş placeholder',
				'option'     => 'gemini_placeholder_text',
				'anahtar'    => null,
				'varsayilan' => 'Bir şeyler sorun...',
			),
			'gemini_welcome_text'      => array(
				'etiket'     => 'Chatbot: karşılama',
				'option'     => 'gemini_welcome_text',
				'anahtar'    => null,
				'varsayilan' => 'Merhaba! Size nasıl yardımcı olabilirim?',
			),
			'qmo_chatbot_teaser_text'  => array(
				'etiket'     => 'Chatbot: teaser metni',
				'option'     => 'qmo_chatbot_teaser_text',
				'anahtar'    => null,
				'varsayilan' => 'Bir şey sormak ister misiniz?',
			),
			'qmo_chatbot_welcome_intro' => array(
				'etiket'     => 'Chatbot: karşılama tanıtımı',
				'option'     => 'qmo_chatbot_welcome_intro',
				'anahtar'    => null,
				'varsayilan' => 'Menü, öneriler ve sipariş için buradayım.',
			),
			'qmo_chatbot_welcome_btn'  => array(
				'etiket'     => 'Chatbot: karşılama butonu',
				'option'     => 'qmo_chatbot_welcome_btn',
				'anahtar'    => null,
				'varsayilan' => 'Sohbete Başla',
			),
			'qmo_chatbot_closed_message' => array(
				'etiket'     => 'Chatbot: kapalı mesajı',
				'option'     => 'qmo_chatbot_closed_message',
				'anahtar'    => null,
				'varsayilan' => 'Şu an kapalıyız, yakında görüşmek üzere.',
			),
			'hfb_footer.links_title'   => array(
				'etiket'     => 'Footer: hızlı menü başlığı',
				'option'     => 'hfb_footer_options',
				'anahtar'    => 'links_title',
				'varsayilan' => 'Hızlı Menü',
			),
			'hfb_footer.hours_title'   => array(
				'etiket'     => 'Footer: çalışma saatleri başlığı',
				'option'     => 'hfb_footer_options',
				'anahtar'    => 'hours_title',
				'varsayilan' => 'Çalışma Saatlerimiz',
			),
			'hfb_footer.contact_title' => array(
				'etiket'     => 'Footer: iletişim başlığı',
				'option'     => 'hfb_footer_options',
				'anahtar'    => 'contact_title',
				'varsayilan' => 'İletişim',
			),
			'hfb_footer.call_garson_label' => array(
				'etiket'      => 'Footer: garson butonu',
				'option'      => 'hfb_footer_options',
				'anahtar'     => 'call_garson_label',
				'varsayilan'  => 'Garson Çağır',
				'yalniz_ozel' => true,
			),
			'hfb_footer.call_hesap_label'  => array(
				'etiket'      => 'Footer: hesap butonu',
				'option'      => 'hfb_footer_options',
				'anahtar'     => 'call_hesap_label',
				'varsayilan'  => 'Hesap İste',
				'yalniz_ozel' => true,
			),
			'hfb_header.brand_line1'       => array(
				'etiket'     => 'Header: marka üst satır',
				'option'     => 'hfb_header_options',
				'anahtar'    => 'brand_line1',
				'varsayilan' => 'QR MENU',
			),
			'hfb_header.brand_line2'       => array(
				'etiket'     => 'Header: marka alt satır',
				'option'     => 'hfb_header_options',
				'anahtar'    => 'brand_line2',
				'varsayilan' => 'OFFİCİAL',
			),
			'hfb_footer.brand_line1'       => array(
				'etiket'     => 'Footer: marka üst satır',
				'option'     => 'hfb_footer_options',
				'anahtar'    => 'brand_line1',
				'varsayilan' => 'QR MENU',
			),
			'hfb_footer.brand_line2'       => array(
				'etiket'     => 'Footer: marka alt satır',
				'option'     => 'hfb_footer_options',
				'anahtar'    => 'brand_line2',
				'varsayilan' => 'OFFİCİAL',
			),
			'hfb_footer.description'       => array(
				'etiket'      => 'Footer: kısa açıklama',
				'option'      => 'hfb_footer_options',
				'anahtar'     => 'description',
				'varsayilan'  => '',
				'yalniz_ozel' => true,
			),
			'hfb_footer.copyright'         => array(
				'etiket'      => 'Footer: telif metni',
				'option'      => 'hfb_footer_options',
				'anahtar'     => 'copyright',
				'varsayilan'  => '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ),
				'yalniz_ozel' => true,
			),
		);

		$qrm_alanlar = array(
			'form_title'                       => 'Yorum formu başlığı',
			'contact_form_title'               => 'İletişim formu başlığı',
			'crit_1_name'                      => 'Kriter 1 adı',
			'crit_2_name'                      => 'Kriter 2 adı',
			'crit_3_name'                      => 'Kriter 3 adı',
			'crit_4_name'                      => 'Kriter 4 adı',
			'crit_5_name'                      => 'Kriter 5 adı',
			'google_review_headline'           => 'Google CTA başlık',
			'google_review_subtext'            => 'Google CTA metin',
			'google_review_btn_text'           => 'Google CTA buton',
			'google_review_skip_text'          => 'Google CTA atla',
			'qrm_reward_popup_title'           => 'Ödül popup başlık',
			'qrm_reward_popup_text'            => 'Ödül popup metin',
			'qrm_reward_popup_button_text'     => 'Ödül popup buton',
			'qrm_reward_popup_claim_text'      => 'Ödül popup kod al',
			'qrm_reward_popup_waiting_text'    => 'Ödül popup bekleniyor',
			'qrm_reward_popup_skip_text'       => 'Ödül popup atla',
			'qrm_reward_popup_email_step_title' => 'Ödül e-posta adımı başlık',
			'qrm_reward_popup_email_step_text' => 'Ödül e-posta adımı metin',
			'qrm_reward_popup_email_placeholder' => 'Ödül e-posta placeholder',
			'qrm_reward_popup_email_button_text' => 'Ödül e-posta buton',
			'qrm_reward_popup_success_text'    => 'Ödül başarı metni',
			'qrm_reward_popup_already_used_text' => 'Ödül e-posta kullanılmış',
			'qrm_reward_popup_error_text'      => 'Ödül hata metni',
			'qrm_reward_popup_copy_text'       => 'Ödül kopyala',
			'qrm_reward_popup_copied_text'     => 'Ödül kopyalandı',
			'qrm_reward_email_subject'         => 'Ödül e-posta konu',
			'qrm_reward_email_intro'           => 'Ödül e-posta giriş',
		);

		foreach ( $qrm_alanlar as $anahtar => $etiket ) {
			$kayit[ 'qrm_settings.' . $anahtar ] = array(
				'etiket'     => $etiket,
				'option'     => 'qrm_settings',
				'anahtar'    => $anahtar,
				'varsayilan' => isset( $qrm[ $anahtar ] ) ? (string) $qrm[ $anahtar ] : '',
			);
		}

		/**
		 * P1 option kayıt defterini süz/genişlet.
		 *
		 * @param array<string,array<string,mixed>> $kayit Defter.
		 */
		foreach ( rma_ceviri_hamburger_bloklari() as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$blok_id = isset( $block['id'] ) ? sanitize_key( (string) $block['id'] ) : '';
			if ( '' === $blok_id ) {
				continue;
			}

			$type = isset( $block['type'] ) ? (string) $block['type'] : '';

			if ( 'button' === $type ) {
				$field = 'hfb_hamburger.block.' . $blok_id . '.label';
				$kayit[ $field ] = array(
					'etiket'      => 'Hamburger: buton metni (' . $blok_id . ')',
					'option'      => 'hfb_hamburger_options',
					'anahtar'     => null,
					'blok_id'     => $blok_id,
					'blok_alan'   => 'label',
					'varsayilan'  => 'Buton',
					'yalniz_ozel' => true,
				);
			}

			if ( 'logo' === $type ) {
				$field = 'hfb_hamburger.block.' . $blok_id . '.description';
				$kayit[ $field ] = array(
					'etiket'      => 'Hamburger: logo açıklama (' . $blok_id . ')',
					'option'      => 'hfb_hamburger_options',
					'anahtar'     => null,
					'blok_id'     => $blok_id,
					'blok_alan'   => 'description',
					'varsayilan'  => '',
					'yalniz_ozel' => true,
				);
			}
		}

		if ( function_exists( 'qmo_chatbot_sorulari_oku' ) ) {
			foreach ( qmo_chatbot_sorulari_oku() as $satir ) {
				$id = isset( $satir['id'] ) ? (string) $satir['id'] : '';
				if ( '' === $id ) {
					continue;
				}
				$kayit[ 'qmo_chatbot_qr.' . $id . '.label' ]    = array(
					'etiket'      => 'Chatbot hazır soru etiketi',
					'option'      => 'qmo_chatbot_quick_replies',
					'anahtar'     => null,
					'liste_id'    => $id,
					'liste_alan'  => 'label',
					'varsayilan'  => isset( $satir['label'] ) ? (string) $satir['label'] : '',
				);
				$kayit[ 'qmo_chatbot_qr.' . $id . '.question' ] = array(
					'etiket'      => 'Chatbot hazır soru',
					'option'      => 'qmo_chatbot_quick_replies',
					'anahtar'     => null,
					'liste_id'    => $id,
					'liste_alan'  => 'question',
					'varsayilan'  => isset( $satir['question'] ) ? (string) $satir['question'] : '',
				);
			}
		}

		return apply_filters( 'rma_ceviri_option_defteri', $kayit );
	}
}

/**
 * Hamburger panel blokları (hfb_hamburger_options.blocks).
 *
 * @return array<int,array<string,mixed>>
 */
if ( ! function_exists( 'rma_ceviri_hamburger_bloklari' ) ) {
	function rma_ceviri_hamburger_bloklari() {
		$opts = get_option( 'hfb_hamburger_options', array() );

		if ( ! is_array( $opts ) || empty( $opts['blocks'] ) || ! is_array( $opts['blocks'] ) ) {
			return array();
		}

		return $opts['blocks'];
	}
}

/**
 * Hamburger blok alanı (option defteri field yolu).
 *
 * @param string $blok_id Blok kimliği (örn. blk_3).
 * @param string $alan    Blok alanı (label, description, content).
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_hamburger_blok_field' ) ) {
	function rma_ceviri_hamburger_blok_field( $blok_id, $alan ) {
		$blok_id = sanitize_key( (string) $blok_id );
		$alan    = sanitize_key( (string) $alan );

		if ( '' === $blok_id || '' === $alan ) {
			return '';
		}

		return 'hfb_hamburger.block.' . $blok_id . '.' . $alan;
	}
}

/**
 * Option kaydının canlı (veya varsayılan) metni.
 *
 * @param array<string,mixed> $kayit Defter satırı.
 * @param bool                $varsayilan_doldur Boşsa varsayılanı yaz.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_option_degeri_oku' ) ) {
	function rma_ceviri_option_degeri_oku( $kayit, $varsayilan_doldur = true ) {
		$opt = get_option( $kayit['option'], null );

		if ( ! empty( $kayit['liste_id'] ) && ! empty( $kayit['liste_alan'] ) ) {
			$liste = array();
			if ( function_exists( 'qmo_chatbot_sorulari_oku' ) ) {
				$liste = qmo_chatbot_sorulari_oku();
			} elseif ( is_array( $opt ) ) {
				$liste = $opt;
			}
			$deger = '';
			foreach ( $liste as $satir ) {
				if ( is_array( $satir ) && (string) ( $satir['id'] ?? '' ) === (string) $kayit['liste_id'] ) {
					$deger = isset( $satir[ $kayit['liste_alan'] ] ) ? $satir[ $kayit['liste_alan'] ] : '';
					break;
				}
			}
		} elseif ( ! empty( $kayit['blok_id'] ) && ! empty( $kayit['blok_alan'] ) ) {
			$deger   = '';
			$hedef   = sanitize_key( (string) $kayit['blok_id'] );
			$blok_alan = (string) $kayit['blok_alan'];

			if ( is_array( $opt ) && ! empty( $opt['blocks'] ) && is_array( $opt['blocks'] ) ) {
				foreach ( $opt['blocks'] as $block ) {
					if ( ! is_array( $block ) ) {
						continue;
					}

					$bid = isset( $block['id'] ) ? sanitize_key( (string) $block['id'] ) : '';
					if ( $bid !== $hedef ) {
						continue;
					}

					$deger = isset( $block[ $blok_alan ] ) ? (string) $block[ $blok_alan ] : '';
					break;
				}
			}
		} elseif ( null !== $kayit['anahtar'] ) {
			if ( ! is_array( $opt ) ) {
				$opt = array();
			}
			$deger = isset( $opt[ $kayit['anahtar'] ] ) ? $opt[ $kayit['anahtar'] ] : '';
		} else {
			$deger = ( null === $opt ) ? '' : $opt;
		}

		$deger = is_scalar( $deger ) ? (string) $deger : '';

		if ( $varsayilan_doldur && '' === trim( $deger ) && isset( $kayit['varsayilan'] ) ) {
			return (string) $kayit['varsayilan'];
		}

		return $deger;
	}
}

/**
 * Option satırının güncel orijinali (bayatlık / import).
 *
 * @param string $field Defter anahtarı.
 * @return string|null
 */
if ( ! function_exists( 'rma_ceviri_option_guncel' ) ) {
	function rma_ceviri_option_guncel( $field ) {
		$defter = rma_ceviri_option_defteri();

		if ( ! isset( $defter[ $field ] ) ) {
			return null;
		}

		return rma_ceviri_option_degeri_oku( $defter[ $field ], true );
	}
}

/**
 * Option CSV satırları.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_option_satirlari' ) ) {
	function rma_ceviri_option_satirlari() {
		foreach ( rma_ceviri_option_defteri() as $field => $kayit ) {
			$ham = rma_ceviri_option_degeri_oku( $kayit, false );

			if ( ! empty( $kayit['yalniz_ozel'] ) ) {
				$varsayilan = isset( $kayit['varsayilan'] ) ? (string) $kayit['varsayilan'] : '';
				if ( '' === trim( $ham ) || $ham === $varsayilan ) {
					continue;
				}
				$metin = $ham;
			} else {
				$metin = rma_ceviri_option_degeri_oku( $kayit, true );
			}

			if ( '' === trim( $metin ) ) {
				continue;
			}

			yield array(
				'item_id'   => 0,
				'item_type' => 'option',
				'field'     => $field,
				'original'  => $metin,
			);
		}
	}
}

/**
 * Veritabanı tablosu var mı?
 *
 * @param string $tablo Tam tablo adı.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_db_tablo_var_mi' ) ) {
	function rma_ceviri_db_tablo_var_mi( $tablo ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return false;
		}

		$tablo = (string) $tablo;
		if ( '' === $tablo ) {
			return false;
		}

		$bulunan = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tablo ) );

		return (string) $bulunan === $tablo;
	}
}

/**
 * Yorum formu alan satırları (qrm_form_fields).
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_form_field_satirlari' ) ) {
	function rma_ceviri_form_field_satirlari() {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$tablo = $wpdb->prefix . 'qrm_form_fields';
		if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
			return;
		}

		$satirlar = $wpdb->get_results( "SELECT id, field_label FROM {$tablo}" );
		if ( empty( $satirlar ) ) {
			return;
		}

		foreach ( $satirlar as $satir ) {
			$etiket = isset( $satir->field_label ) ? trim( (string) $satir->field_label ) : '';
			if ( '' === $etiket ) {
				continue;
			}

			yield array(
				'item_id'   => (int) $satir->id,
				'item_type' => 'form_field',
				'field'     => 'label',
				'original'  => $etiket,
			);
		}
	}
}

/**
 * Özel form alan satırları (qrm_custom_form_fields).
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_cf_field_satirlari' ) ) {
	function rma_ceviri_cf_field_satirlari() {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$tablo = function_exists( 'qrm_cf_fields_table' )
			? qrm_cf_fields_table()
			: $wpdb->prefix . 'qrm_custom_form_fields';

		if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
			return;
		}

		$satirlar = $wpdb->get_results( "SELECT id, label FROM {$tablo}" );
		if ( empty( $satirlar ) ) {
			return;
		}

		foreach ( $satirlar as $satir ) {
			$etiket = isset( $satir->label ) ? trim( (string) $satir->label ) : '';
			if ( '' === $etiket ) {
				continue;
			}

			yield array(
				'item_id'   => (int) $satir->id,
				'item_type' => 'cf_field',
				'field'     => 'label',
				'original'  => $etiket,
			);
		}
	}
}

/**
 * Özel form başlık / gönder / başarı metinleri.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_cf_form_satirlari' ) ) {
	function rma_ceviri_cf_form_satirlari() {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$tablo = function_exists( 'qrm_cf_forms_table' )
			? qrm_cf_forms_table()
			: $wpdb->prefix . 'qrm_custom_forms';

		if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
			return;
		}

		$satirlar = $wpdb->get_results( "SELECT id, title, settings FROM {$tablo}" );
		if ( empty( $satirlar ) ) {
			return;
		}

		foreach ( $satirlar as $satir ) {
			$id = (int) $satir->id;
			if ( '' !== trim( (string) $satir->title ) ) {
				yield array(
					'item_id'   => $id,
					'item_type' => 'cf_form',
					'field'     => 'title',
					'original'  => (string) $satir->title,
				);
			}

			$ayar = function_exists( 'qrm_cf_get_form_settings' )
				? qrm_cf_get_form_settings( $satir )
				: array();

			foreach ( array( 'submit_text', 'success_message' ) as $alan ) {
				$metin = isset( $ayar[ $alan ] ) ? trim( (string) $ayar[ $alan ] ) : '';
				if ( '' === $metin ) {
					continue;
				}

				yield array(
					'item_id'   => $id,
					'item_type' => 'cf_form',
					'field'     => $alan,
					'original'  => $metin,
				);
			}
		}
	}
}

/**
 * form_field / cf_field / cf_form güncel orijinal.
 *
 * @param int    $item_id ID.
 * @param string $tip     form_field|cf_field|cf_form.
 * @param string $field   Alan.
 * @return string|null
 */
if ( ! function_exists( 'rma_ceviri_veri_guncel' ) ) {
	function rma_ceviri_veri_guncel( $item_id, $tip, $field ) {
		global $wpdb;

		$item_id = (int) $item_id;
		if ( $item_id <= 0 || ! isset( $wpdb ) ) {
			return null;
		}

		if ( 'form_field' === $tip ) {
			if ( 'label' !== $field ) {
				return null;
			}
			$tablo = $wpdb->prefix . 'qrm_form_fields';
			if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
				return null;
			}
			$etiket = $wpdb->get_var( $wpdb->prepare( "SELECT field_label FROM {$tablo} WHERE id = %d", $item_id ) );

			return ( null === $etiket ) ? null : (string) $etiket;
		}

		if ( 'cf_field' === $tip ) {
			if ( 'label' !== $field ) {
				return null;
			}
			$tablo = function_exists( 'qrm_cf_fields_table' )
				? qrm_cf_fields_table()
				: $wpdb->prefix . 'qrm_custom_form_fields';
			if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
				return null;
			}
			$etiket = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$tablo} WHERE id = %d", $item_id ) );

			return ( null === $etiket ) ? null : (string) $etiket;
		}

		if ( 'cf_form' === $tip ) {
			$tablo = function_exists( 'qrm_cf_forms_table' )
				? qrm_cf_forms_table()
				: $wpdb->prefix . 'qrm_custom_forms';
			if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
				return null;
			}
			$satir = $wpdb->get_row( $wpdb->prepare( "SELECT title, settings FROM {$tablo} WHERE id = %d", $item_id ) );
			if ( ! $satir ) {
				return null;
			}
			if ( 'title' === $field ) {
				return (string) $satir->title;
			}
			$ayar = function_exists( 'qrm_cf_get_form_settings' )
				? qrm_cf_get_form_settings( $satir )
				: array();

			return isset( $ayar[ $field ] ) ? (string) $ayar[ $field ] : null;
		}

		return null;
	}
}

/**
 * Kimlik + alan çevirisi (hash kapısı rma_translate_field içindedir).
 *
 * @param string      $tip   option|form_field|cf_field|cf_form.
 * @param int         $id    item_id.
 * @param string      $field Alan.
 * @param string      $metin Canlı yönetici metni.
 * @param string|null $lang  Dil.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_veri' ) ) {
	function rma_ceviri_veri( $tip, $id, $field, $metin, $lang = null ) {
		$metin = (string) $metin;

		if ( '' === $metin ) {
			return $metin;
		}

		if ( ! function_exists( 'rma_translate_field' ) ) {
			return $metin;
		}

		$ceviri = rma_translate_field( (int) $id, $tip, $field, $metin, $lang );

		return ( '' !== (string) $ceviri ) ? (string) $ceviri : $metin;
	}
}

/**
 * Option kısa yolu (item_id = 0).
 *
 * @param string      $field Defter anahtarı.
 * @param string      $metin Canlı metin.
 * @param string|null $lang  Dil.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_option' ) ) {
	function rma_ceviri_option( $field, $metin, $lang = null ) {
		return rma_ceviri_veri( 'option', 0, $field, $metin, $lang );
	}
}

/**
 * Çeviri satırındaki ID'lerden canlı olmayanları ayır.
 *
 * WordPress'e bağımsız — test edilebilir.
 *
 * @param int[] $ceviri_idler Tablodaki item_id listesi.
 * @param int[] $canli_idler  Hâlâ duran kaynak ID'leri.
 * @return int[]
 */
if ( ! function_exists( 'rma_ceviri_yetim_idleri_filtrele' ) ) {
	function rma_ceviri_yetim_idleri_filtrele( $ceviri_idler, $canli_idler ) {
		$ceviri = array_values( array_unique( array_map( 'intval', (array) $ceviri_idler ) ) );
		$canli  = array_values( array_unique( array_map( 'intval', (array) $canli_idler ) ) );

		return array_values( array_diff( $ceviri, $canli ) );
	}
}

/**
 * ID tabanlı yetim tipler.
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_yetim_tipleri' ) ) {
	function rma_ceviri_yetim_tipleri() {
		return array( 'form_field', 'cf_field', 'cf_form', 'product', 'category', 'allergen', 'nav_menu', 'elementor' );
	}
}

/**
 * Bir tipin canlı kaynak ID'leri.
 *
 * @param string $tip item_type.
 * @return int[]
 */
if ( ! function_exists( 'rma_ceviri_canli_idler' ) ) {
	function rma_ceviri_canli_idler( $tip ) {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return array();
		}

		switch ( $tip ) {
			case 'form_field':
				$tablo = $wpdb->prefix . 'qrm_form_fields';
				if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
					return array();
				}
				return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$tablo}" ) );

			case 'cf_field':
				$tablo = function_exists( 'qrm_cf_fields_table' )
					? qrm_cf_fields_table()
					: $wpdb->prefix . 'qrm_custom_form_fields';
				if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
					return array();
				}
				return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$tablo}" ) );

			case 'cf_form':
				$tablo = function_exists( 'qrm_cf_forms_table' )
					? qrm_cf_forms_table()
					: $wpdb->prefix . 'qrm_custom_forms';
				if ( ! rma_ceviri_db_tablo_var_mi( $tablo ) ) {
					return array();
				}
				return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$tablo}" ) );

			case 'product':
				$tipler = function_exists( 'rma_ceviri_urun_tipleri' ) ? rma_ceviri_urun_tipleri() : array();
				if ( empty( $tipler ) ) {
					return array();
				}
				$in = "'" . implode( "','", array_map( 'esc_sql', $tipler ) ) . "'";
				return array_map(
					'intval',
					(array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$in})" )
				);

			case 'category':
			case 'allergen':
				return array_map(
					'intval',
					(array) $wpdb->get_col( "SELECT term_id FROM {$wpdb->terms}" )
				);

			case 'nav_menu':
				return array_map(
					'intval',
					(array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item'" )
				);

			case 'elementor':
				return array_map(
					'intval',
					(array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status != 'trash'" )
				);
		}

		return array();
	}
}

/**
 * Yetim çeviri özeti: tip => yetim item_id listesi.
 *
 * @return array<string,int[]>
 */
if ( ! function_exists( 'rma_ceviri_yetim_haritasi' ) ) {
	function rma_ceviri_yetim_haritasi() {
		$sonuc = array();

		if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
			return $sonuc;
		}

		foreach ( rma_ceviri_yetim_tipleri() as $tip ) {
			$ceviri = RMA_Ceviri_Tablo::tip_item_idleri( $tip );
			if ( empty( $ceviri ) ) {
				continue;
			}
			$yetim = rma_ceviri_yetim_idleri_filtrele( $ceviri, rma_ceviri_canli_idler( $tip ) );
			if ( ! empty( $yetim ) ) {
				$sonuc[ $tip ] = $yetim;
			}
		}

		return $sonuc;
	}
}

/**
 * Yetim satır (çeviri satırı) sayısı.
 *
 * @param array<string,int[]>|null $harita Hazır harita.
 * @return int
 */
if ( ! function_exists( 'rma_ceviri_yetim_satir_sayisi' ) ) {
	function rma_ceviri_yetim_satir_sayisi( $harita = null ) {
		if ( null === $harita ) {
			$harita = rma_ceviri_yetim_haritasi();
		}

		if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
			return 0;
		}

		$toplam = 0;
		foreach ( $harita as $tip => $idler ) {
			$toplam += RMA_Ceviri_Tablo::tip_id_satir_sayisi( $tip, $idler );
		}

		return $toplam;
	}
}

/**
 * Yetim satırları sil (geri alınamaz).
 *
 * @return int Silinen satır.
 */
if ( ! function_exists( 'rma_ceviri_yetimleri_sil' ) ) {
	function rma_ceviri_yetimleri_sil() {
		$silinen = 0;
		foreach ( rma_ceviri_yetim_haritasi() as $tip => $idler ) {
			$silinen += RMA_Ceviri_Tablo::tip_idleri_sil( $tip, $idler );
		}

		if ( $silinen > 0 && function_exists( 'rma_ceviri_onbellek_temizle' ) ) {
			rma_ceviri_onbellek_temizle();
		}

		return $silinen;
	}
}

/**
 * Bir veri satırının kaç dilde çevirisi var?
 *
 * @param string $tip     item_type.
 * @param int    $item_id ID.
 * @param string $field   Alan; boşsa o ID'nin tüm alanları.
 * @return int
 */
if ( ! function_exists( 'rma_ceviri_veri_dil_sayisi' ) ) {
	function rma_ceviri_veri_dil_sayisi( $tip, $item_id, $field = '' ) {
		if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
			return 0;
		}

		return RMA_Ceviri_Tablo::alan_dil_sayisi( $tip, (int) $item_id, $field );
	}
}

/**
 * Düzenleme ekranı uyarı metni. Kaydı ENGELLEMEZ.
 *
 * @param int $adet Dil sayısı.
 * @return string Boşsa uyarı yok.
 */
if ( ! function_exists( 'rma_ceviri_bayat_uyari_metni' ) ) {
	function rma_ceviri_bayat_uyari_metni( $adet ) {
		$adet = (int) $adet;

		if ( $adet < 1 ) {
			return '';
		}

		return sprintf(
			'Bu metnin %d dilde çevirisi var. Değiştirirseniz çeviriler eskiyecek ve ziyaretçiler Türkçe görecek.',
			$adet
		);
	}
}

/**
 * Ekran düzeyinde uyarı (birden çok alan).
 *
 * @param int $adet Dil sayısı (distinct).
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_bayat_uyari_ekran_metni' ) ) {
	function rma_ceviri_bayat_uyari_ekran_metni( $adet ) {
		$adet = (int) $adet;

		if ( $adet < 1 ) {
			return '';
		}

		return sprintf(
			'Bu ekrandaki metinlerin %d dilde çevirisi var. Değiştirirseniz çeviriler eskiyecek ve ziyaretçiler Türkçe görecek.',
			$adet
		);
	}
}

/**
 * Uyarı HTML'i (kaydetmeyi engellemez).
 *
 * @param string $metin Uyarı; boşsa çıktı yok.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_bayat_uyari_html' ) ) {
	function rma_ceviri_bayat_uyari_html( $metin ) {
		$metin = (string) $metin;
		if ( '' === $metin ) {
			return '';
		}

		return '<div class="notice notice-warning inline rma-ceviri-bayat-uyari"><p>' . esc_html( $metin ) . '</p></div>';
	}
}

/**
 * Option field listesi için distinct dil sayısı.
 *
 * @param string[] $fieldler Defter anahtarları.
 * @return int
 */
if ( ! function_exists( 'rma_ceviri_option_alan_dil_sayisi' ) ) {
	function rma_ceviri_option_alan_dil_sayisi( $fieldler ) {
		if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
			return 0;
		}

		return RMA_Ceviri_Tablo::option_alan_dil_sayisi( (array) $fieldler );
	}
}

/**
 * Hash kapısı tiplerinde eskimiş çeviri satırı sayısı.
 *
 * @return array<string,int> tip => adet.
 */
if ( ! function_exists( 'rma_ceviri_eskimis_sayilari' ) ) {
	function rma_ceviri_eskimis_sayilari() {
		$sonuc = array();

		if ( ! class_exists( 'RMA_Ceviri_Tablo' ) || ! RMA_Ceviri_Tablo::tablo_var_mi() ) {
			return $sonuc;
		}

		foreach ( array_keys( rma_ceviri_veri_tipleri() ) as $tip ) {
			$satirlar = RMA_Ceviri_Tablo::tip_hash_satirlari( $tip );
			$adet     = 0;

			foreach ( $satirlar as $satir ) {
				if ( 'option' === $tip ) {
					$guncel = rma_ceviri_option_guncel( $satir->field );
				} else {
					$guncel = rma_ceviri_veri_guncel( (int) $satir->item_id, $tip, $satir->field );
				}

				if ( null === $guncel ) {
					continue;
				}

				$field = isset( $satir->field ) ? (string) $satir->field : '';
				if ( ! rma_ceviri_hash_guncel_mi( $guncel, $satir->original_hash, $field ) ) {
					++$adet;
				}
			}

			if ( $adet > 0 ) {
				$sonuc[ $tip ] = $adet;
			}
		}

		return $sonuc;
	}
}
