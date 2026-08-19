<?php
/**
 * Ön yüz: varlıklar ve çıktı tamponları.
 *
 * QR MENÜ (RMA) eklentisi artık kart, kategori, modal ve sabit arayüz
 * metinlerini rma_translate_field() üzerinden KAYNAĞINDA çeviriyor. Bu
 * yüzden RMA'nın AJAX eylemlerinde tampon hiç kurulmaz.
 *
 * Sayfa tamponu açık kalır: tema, Elementor ve diğer eklentilerin bastığı
 * metinler hâlâ ona muhtaç. RMA'nın kısa kod çıktısı tampondan geçse bile
 * eşleşme bulamaz — sözlük anahtarları Türkçe, o çıktı zaten çevrilmiş.
 * Tamamen kapatmak için yönetimdeki "Çıktı üzerinde çeviri" seçeneği.
 *
 * TR ziyaretçide hiçbir tampon kurulmaz — sıfır ek maliyet.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'rma_ceviri_varliklar' );
/**
 * Dil seçici CSS/JS.
 *
 * CSS koşulsuz yükleniyor (kısa kod sayfanın neresinde olursa olsun bayrak
 * "flash" etmesin diye) — eski sürümdeki wp_head davranışının karşılığı.
 * Ayara bağlı renkler statik dosyayı bozmamak için CSS değişkeni olarak
 * ekleniyor.
 */
if ( ! function_exists( 'rma_ceviri_varliklar' ) ) {
	function rma_ceviri_varliklar() {
		wp_enqueue_style(
			'rma-ceviri',
			RMA_CEVIRI_URL . 'assets/css/ceviri.css',
			array(),
			RMA_CEVIRI_VERSION
		);

		$renk_metin = get_option( 'qrmenu_bg_color_text', '#111111' );
		$renk_bayr  = get_option( 'qrmenu_bg_color_only', '#111111' );

		wp_add_inline_style(
			'rma-ceviri',
			sprintf(
				':root{--rma-ceviri-bg-text:%s;--rma-ceviri-bg-only:%s;}',
				esc_attr( $renk_metin ),
				esc_attr( $renk_bayr )
			)
		);

		wp_enqueue_script(
			'rma-ceviri',
			RMA_CEVIRI_URL . 'assets/js/ceviri.js',
			array(),
			RMA_CEVIRI_VERSION,
			true
		);

		wp_localize_script(
			'rma-ceviri',
			'rmaCeviri',
			array(
				'dil'    => rma_get_current_lang(),
				'cookie' => RMA_CEVIRI_COOKIE,
			)
		);
	}
}

add_action( 'wp_head', 'rma_ceviri_dil_degiskeni', 1 );
/**
 * Aktif dili sayfanın en başında ilan et.
 *
 * QR Menu Official'ın sepet.js dosyası para birimini seçmek için
 * `window.rmaCeviriDil` / sessionStorage['rma_dil'] okuyor (sepet.js:30).
 * Bu satır olmazsa EN/DE ziyaretçide sepet TL'de kalır.
 */
if ( ! function_exists( 'rma_ceviri_dil_degiskeni' ) ) {
	function rma_ceviri_dil_degiskeni() {
		$dil = rma_get_current_lang();
		?>
		<script id="rma-ceviri-dil">
			window.rmaCeviriDil = <?php echo wp_json_encode( $dil ); ?>;
			try { sessionStorage.setItem( 'rma_dil', window.rmaCeviriDil ); } catch ( e ) {}
		</script>
		<?php
	}
}

/**
 * Çıktı tamponu açık mı? (Kaynak entegrasyonu tamamlanınca kapatılabilir.)
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_tampon_acik_mi' ) ) {
	function rma_ceviri_tampon_acik_mi() {
		return (bool) get_option( 'rma_ceviri_tampon_acik', 1 );
	}
}

add_action( 'template_redirect', 'rma_ceviri_sayfa_tamponu_kur', 5 );
/**
 * Sayfa çıktısı için tampon kur.
 *
 * Öncelik 5: dil yönlendirmesi (dil.php, öncelik 1) çalışıp exit edebilir;
 * tamponu ondan sonra açıyoruz.
 */
if ( ! function_exists( 'rma_ceviri_sayfa_tamponu_kur' ) ) {
	function rma_ceviri_sayfa_tamponu_kur() {
		if ( ! rma_ceviri_on_yuz_istegi_mi() ) {
			return;
		}

		$cevirecek = rma_ceviri_aktif_mi() && rma_ceviri_tampon_acik_mi();
		$toplayacak = rma_ceviri_toplama_calissin_mi();

		if ( ! $cevirecek && ! $toplayacak ) {
			return;
		}

		ob_start( 'rma_ceviri_sayfa_tamponu' );
	}
}

/**
 * Sayfa çıktısını çevir.
 *
 * @param string $html Tam sayfa HTML'i.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_sayfa_tamponu' ) ) {
	function rma_ceviri_sayfa_tamponu( $html ) {
		if ( ! rma_ceviri_html_yaniti_mi() ) {
			return $html;
		}

		// Toplama modu çıktıyı değiştirmez, yalnızca okur.
		if ( rma_ceviri_toplama_calissin_mi() ) {
			rma_ceviri_metin_topla( $html );
		}

		if ( ! rma_ceviri_aktif_mi() || ! rma_ceviri_tampon_acik_mi() ) {
			return $html;
		}

		$sozluk = rma_ceviri_metin_sozlugu();
		if ( empty( $sozluk ) ) {
			return $html;
		}

		return rma_ceviri_html_degistir( $html, $sozluk );
	}
}

/**
 * Yanıt HTML mi? (PDF/görsel üreten şablonlara dokunmayalım.)
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_html_yaniti_mi' ) ) {
	function rma_ceviri_html_yaniti_mi() {
		foreach ( headers_list() as $baslik ) {
			if ( 0 === stripos( $baslik, 'content-type:' ) ) {
				return (bool) stripos( $baslik, 'text/html' );
			}
		}

		// Content-Type hiç ayarlanmadıysa WordPress varsayılanı text/html'dir.
		return true;
	}
}

add_filter( 'nav_menu_item_title', 'rma_ceviri_menu_baslik', 10, 2 );
/**
 * Menü öğesi etiketlerini çevir (footer/header menüleri).
 *
 * Çıktı tamponuyla değil, çekirdeğin kendi filtresiyle: her öğe kendi
 * ID'siyle eşleştiği için aynı etiket sayfanın başka yerinde geçse bozulmaz.
 *
 * @param string $baslik Menü etiketi.
 * @param object $oge    Menü öğesi.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_menu_baslik' ) ) {
	function rma_ceviri_menu_baslik( $baslik, $oge ) {
		if ( ! rma_ceviri_aktif_mi() || ! isset( $oge->ID ) ) {
			return $baslik;
		}

		return rma_translate_field( (int) $oge->ID, 'nav_menu', 'title', $baslik );
	}
}

/*
 * Menü öğesi AÇIKLAMALARI için ayrı bir filtre yok: çekirdeğin
 * `nav_menu_description` filtresi öğe nesnesini geçirmediği için hangi
 * öğeye ait olduğu güvenilir biçimde çözülemiyor. Açıklamalar CSV'ye
 * yine çıkar (nav_menu/description) ve sayfa tamponu tarafından metin
 * eşleşmesiyle çevrilir — nadir kullanıldıkları için bu yeterli.
 */

/**
 * Kurulu QR MENÜ sürümü çeviri entegrasyonunu içeriyor mu?
 *
 * Entegrasyon RMA'nın kendi dosyalarında yaşıyor (trait-helpers.php'deki
 * t()/t_field() sarmalayıcıları). Eğer sitedeki QR MENÜ eklentisi
 * güncellenmemişse bu sarmalayıcılar yoktur; kartlar rma_translate_field()
 * üzerinden GEÇMEZ. O durumda çıktı tamponunu kapatmak çeviriyi tamamen
 * durdurur — menü Türkçe kalır, Elementor metinleri çevrilir. Bu yüzden
 * tamponu yalnızca entegrasyon gerçekten varken devre dışı bırakıyoruz.
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_rma_entegre_mi' ) ) {
	function rma_ceviri_rma_entegre_mi() {
		static $sonuc = null;

		if ( null === $sonuc ) {
			$sonuc = class_exists( 'Restaurant_Menu_Automation' )
				&& method_exists( 'Restaurant_Menu_Automation', 't_field' );
		}

		return $sonuc;
	}
}

/**
 * Bu AJAX eylemi çeviriyi zaten KAYNAKTA yapıyor mu?
 *
 * QR MENÜ (RMA) eklentisi kart, kategori ve modal metinlerini
 * rma_translate_field() üzerinden basıyor. O yanıtları bir de tampondan
 * geçirmek gereksiz iş olurdu; üstelik RMA'nın flush_stray_output()
 * çağrısıyla (trait-ajax.php) aynı tamponu paylaşmaktan da kaçınılmış olur.
 *
 * Entegrasyon yoksa false döner ve tampon kurulur (Faz 1 davranışı) —
 * güncellenmemiş bir QR MENÜ kurulumunda çeviri sessizce kaybolmasın.
 *
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_kaynakta_cevrilen_eylem_mi' ) ) {
	function rma_ceviri_kaynakta_cevrilen_eylem_mi() {
		if ( ! rma_ceviri_rma_entegre_mi() ) {
			return false;
		}

		$eylem = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( '' === $eylem ) {
			return false;
		}

		/**
		 * Kaynağında çevrilen AJAX eylemleri.
		 *
		 * @param string[] $eylemler Eylem adları.
		 */
		$eylemler = apply_filters(
			'rma_ceviri_kaynakta_cevrilen_eylemler',
			array( 'rma_load_items', 'rma_get_product_details' )
		);

		return in_array( $eylem, (array) $eylemler, true );
	}
}

add_action( 'admin_init', 'rma_ceviri_ajax_tamponu_kur', 0 );
/**
 * AJAX yanıtları için tampon kur.
 *
 * admin-ajax.php de admin_init'i çalıştırır. Öncelik 0 ile tamponu RMA'nın
 * kendi işleyicisi çalışmadan önce açıyoruz; wp_send_json() die() etse bile
 * tampon kapanışta geri çağrımızdan geçer.
 *
 * Dil sırası: $_REQUEST['lang'] → cookie. Cookie'yi tarayıcı admin-ajax.php'ye
 * de otomatik gönderdiği için menü kartları RMA'nın JS'ine hiç dokunmadan
 * doğru dilde döner.
 */
if ( ! function_exists( 'rma_ceviri_ajax_tamponu_kur' ) ) {
	function rma_ceviri_ajax_tamponu_kur() {
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$cevirecek  = rma_ceviri_aktif_mi()
			&& rma_ceviri_tampon_acik_mi()
			&& ! rma_ceviri_kaynakta_cevrilen_eylem_mi();
		$toplayacak = rma_ceviri_toplama_calissin_mi();

		if ( ! $cevirecek && ! $toplayacak ) {
			return;
		}

		ob_start( 'rma_ceviri_ajax_tamponu' );
	}
}

/**
 * AJAX yanıtını çevir. JSON ise değerleri, değilse HTML'i.
 *
 * @param string $cikti Yanıt gövdesi.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_ajax_tamponu' ) ) {
	function rma_ceviri_ajax_tamponu( $cikti ) {
		if ( '' === trim( (string) $cikti ) ) {
			return $cikti;
		}

		$veri     = json_decode( $cikti, true );
		$json_mu  = JSON_ERROR_NONE === json_last_error() && ( is_array( $veri ) || is_object( $veri ) );

		// Toplama modu çıktıyı değiştirmez, yalnızca okur.
		if ( rma_ceviri_toplama_calissin_mi() ) {
			if ( $json_mu ) {
				rma_ceviri_json_metin_topla( $veri );
			} else {
				rma_ceviri_metin_topla( $cikti );
			}
		}

		if ( ! rma_ceviri_aktif_mi() || ! rma_ceviri_tampon_acik_mi() || rma_ceviri_kaynakta_cevrilen_eylem_mi() ) {
			return $cikti;
		}

		$sozluk = rma_ceviri_metin_sozlugu();
		if ( empty( $sozluk ) ) {
			return $cikti;
		}

		if ( $json_mu ) {
			$yeni = wp_json_encode( rma_ceviri_json_degistir( $veri, $sozluk ) );
			return false === $yeni ? $cikti : $yeni;
		}

		return rma_ceviri_html_degistir( $cikti, $sozluk );
	}
}
