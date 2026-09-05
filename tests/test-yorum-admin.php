<?php
/**
 * Yorum & Feedback yönetim sayfası ve hub testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

// menu.php'deki rozet hesabı qrm_pro_trend_drop_state() (trend.php) çağırır;
// gerçek yüklemede (qr-menu-reviews.php) tüm admin dosyaları tek istekte
// yüklendiği için sıra sorun değil, ama burada trend.php ayrıca require
// edilmezse fonksiyon tanımsız kalır.
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/trend.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/menu.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/hub.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/module.php';

/*
 * Rozetlerin ve hub özetinin beslendiği veri fonksiyonları $wpdb'ye ve modülün
 * option şemasına dayanır; burada test edilen şey sunum (hangi ekran nerede
 * görünüyor) olduğu için veri tarafı sabitlenir.
 */
// qrm_pro_get_settings() gerçek settings.php'den gelir (Gemini testleri onu
// yükler); ödül rozetini süren tek ayar option üzerinden verilir.
update_option( 'qrm_settings', array( 'qrm_reward_enabled' => 1 ) );

// 8z-2 bölümü ödül modülünün GERÇEK functions.php'sini yüklüyor; oradaki
// qrm_reward_is_active zaten bu senaryoda false döner (google_review_url
// boş). Gerçek fonksiyon yüklenmemişse diye taklidi burada duruyor.
if ( ! function_exists( 'qrm_reward_is_active' ) ) {
	function qrm_reward_is_active( $settings ) {
		return false;
	}
}

function qrm_cf_unread_total() {
	return 2;
}

// qrm_pro_review_stats() de gerçek install.php'den gelir; sayaçları yukarıdaki
// QRMS_Test_Wpdb besler (tablo var, sayımlar sabit).

/**
 * Yorum sayaçlarının taklidi — hub ve rozet testleri için.
 *
 * qrm_pro_review_stats() istek içi memo'yu ($GLOBALS['qrm_pro_stats_memo'])
 * olduğu gibi döndürdüğü için ekranlar veritabanına hiç gitmeden beslenebilir.
 *
 * @param array $args Ezilecek alanlar.
 * @return array
 */
function qrms_sahte_yorum_stats( $args = array() ) {
	$stats = array_merge(
		array(
			'table_ok'        => true,
			'total'           => 40,
			'approved'        => 36,
			'pending'         => 4,
			'avg'             => 3.9,
			'google_eligible' => 20,
			'threshold'       => 3.5,
			'crit'            => array( 1 => 4.0, 2 => 4.0, 3 => 4.0, 4 => 4.0, 5 => 4.0 ),
		),
		$args
	);

	$stats['sentiment'] = qrm_pro_empty_sentiment_stats( qrm_pro_sentiment_threshold() );

	return $stats;
}

/**
 * Bir özet kutusunun sınıf özniteliği (etiketinden bulunur).
 *
 * @param string $html  Hub çıktısı.
 * @param string $label Kutunun etiketi.
 * @return string Bulunamazsa boş string.
 */
function qrms_yf_stat_class( $html, $label ) {
	$desen = '/<a class="([^"]+)"[^>]*>\s*<div class="qrms-hub-stat-label">'
		. preg_quote( $label, '/' ) . '</u';

	return preg_match( $desen, $html, $m ) ? $m[1] : '';
}

/**
 * Hub ekranını verilen sayaçlarla basar ve HTML'ini döndürür.
 *
 * @param array|null $stats qrms_sahte_yorum_stats() argümanları.
 * @return string
 */
function qrms_yf_hub_html( $stats = array() ) {
	$GLOBALS['qrm_pro_stats_memo'] = qrms_sahte_yorum_stats( $stats );

	ob_start();
	qrm_pro_admin_hub();

	return ob_get_clean();
}

echo "\nYorum & Feedback sayfaları\n";

qrms_test(
	'dört ekranın hepsi kayıt defterinde ve her birinin callback\'i var',
	function () {
		$pages = qrm_pro_admin_pages();

		// Ana Yorum / İletişim ayrı sayfa değil; Formlar listesinde sistem satırı.
		qrms_assert_same(
			array(
				'qrms-yf-yorumlar',
				'qrms-yf-formlar',
				'qrms-yf-ayarlar',
				'qrms-yf-odul',
			),
			array_keys( $pages ),
			'sayfa listesi'
		);

		foreach ( $pages as $slug => $page ) {
			foreach ( array( 'title', 'menu_title', 'render', 'desc', 'icon', 'group' ) as $key ) {
				qrms_assert_true( ! empty( $page[ $key ] ), $slug . ' -> ' . $key . ' dolu' );
			}

			qrms_assert_true(
				array_key_exists( $page['group'], qrm_pro_admin_page_groups() ),
				$slug . ' -> tanımlı bir gruba ait'
			);
		}

		qrms_assert_false(
			array_key_exists( 'qrms-yf-icgoruler', $pages ),
			'kaldırılan İçgörüler ekranı kayıtlı değil'
		);
		qrms_assert_false( function_exists( 'qrm_pro_admin_insights' ), 'callback de kalmadı' );
		qrms_assert_false( function_exists( 'qrm_ai_generate_summary' ), 'Gemini özeti de kalmadı' );
	}
);

qrms_test(
	'hub slug\'ı suite\'in modül satırıyla aynıdır',
	function () {
		qrms_assert_same(
			QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
			qrm_pro_hub_slug(),
			'hub slug\'ı'
		);
	}
);

qrms_test(
	'eski adresler yeni sayfalara yönlendirilir',
	function () {
		$bekleyen = array(
			// [eski slug, parametreler, hedef slug, hedefte beklenen ek parametre]
			array( 'qrm-pro-main', array(), 'qrms-yf-yorumlar', '' ),
			// Kaldırılan İçgörüler ekranının iki adresi de yorum listesine düşer.
			array( 'qrm-pro-main', array( 'tab' => 'insights' ), 'qrms-yf-yorumlar', '' ),
			array( 'qrm-pro-insights', array(), 'qrms-yf-yorumlar', '' ),
			array( 'qrm-pro-settings', array(), 'qrms-yf-ayarlar', '' ),
			array( 'qrm-pro-settings', array( 'sub' => 'alanlar' ), 'qrms-yf-formlar', 'system=review' ),
			array( 'qrm-pro-form', array(), 'qrms-yf-formlar', 'system=review' ),
			array( 'qrms-yf-form-alanlari', array(), 'qrms-yf-formlar', 'system=review' ),
			array( 'qrm-pro-contact', array(), 'qrms-yf-formlar', 'system=contact' ),
			array( 'qrms-yf-iletisim', array(), 'qrms-yf-formlar', 'system=contact' ),
			array( 'qrm-pro-rewards', array(), 'qrms-yf-odul', '' ),
			array( 'qrm-pro-rewards', array( 'tab' => 'codes' ), 'qrms-yf-odul', 'tab=codes' ),
			array( 'qrm-forms', array(), 'qrms-yf-formlar', '' ),
			array( 'qrm-forms', array( 'tab' => 'submissions' ), 'qrms-yf-formlar', 'tab=submissions' ),
			array( 'qrm-form-edit', array( 'form_id' => 3 ), 'qrms-yf-formlar', 'form_id=3' ),
			array( 'qrm-form-submissions', array( 'form_id' => 7 ), 'qrms-yf-formlar', 'form_id=7' ),
		);

		foreach ( $bekleyen as $case ) {
			list( $eski, $args, $hedef_slug, $ek ) = $case;

			$target = qrm_pro_legacy_page_target( $eski, $args );

			qrms_assert_true(
				false !== strpos( $target, 'page=' . $hedef_slug ),
				$eski . ' -> ' . $hedef_slug . ' (gelen: ' . $target . ')'
			);

			if ( '' !== $ek ) {
				qrms_assert_true(
					false !== strpos( $target, $ek ),
					$eski . ' -> ' . $ek . ' korunur'
				);
			}
		}
	}
);

qrms_test(
	'bilinmeyen ve güncel slug\'lar yönlendirilmez',
	function () {
		qrms_assert_same( '', qrm_pro_legacy_page_target( 'qrms-yf-yorumlar' ), 'güncel slug' );
		qrms_assert_same( '', qrm_pro_legacy_page_target( 'baska-eklenti' ), 'yabancı slug' );
		qrms_assert_same( '', qrm_pro_legacy_page_target( '' ), 'boş slug' );
	}
);

qrms_test(
	'form düzenleyici adresi düzenleyici görünümünü hedefler',
	function () {
		$target = qrm_pro_legacy_page_target( 'qrm-form-edit', array( 'form_id' => 3 ) );

		qrms_assert_true( false !== strpos( $target, 'view=edit' ), 'view=edit korunur' );
	}
);

echo "\nYorum & Feedback menü ve hub\n";

qrms_test(
	'dört ekran da gizli sayfa olarak kaydedilir, menüde satırları olmaz',
	function () {
		$GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] = array(
			qrms_submenu_satiri( 'Genel Bakış', QRMS_Admin::MENU_SLUG ),
			qrms_submenu_satiri( 'Yorum & Feedback', QRMS_Admin::get_module_page_slug( 'yorum-feedback' ) ),
			qrms_submenu_satiri( 'Genel Ayarlar', QRMS_Admin::SETTINGS_SLUG ),
		);

		qrms_module_yorum_feedback_admin_menu();

		qrms_assert_same(
			array_keys( qrm_pro_admin_pages() ),
			array_map(
				function ( $item ) {
					return $item['slug'];
				},
				$GLOBALS['qrms_test']['submenus']
			),
			'kaydedilen sayfalar'
		);

		// Etiketlerde artık "—" öneki yok: satırlar menüde hiç görünmüyor.
		foreach ( $GLOBALS['qrms_test']['submenus'] as $item ) {
			qrms_assert_false( 0 === strpos( $item['title'], '—' ), $item['slug'] . ' önekli değil' );
		}

		// Gizleme, beyaz liste üzerinden çekirdeğin işi.
		update_option( 'qrms_active_modules', array( 'yorum-feedback' ) );
		QRMS_Admin::hide_module_subpages();

		qrms_assert_same(
			array(
				QRMS_Admin::MENU_SLUG,
				QRMS_Admin::get_module_page_slug( 'yorum-feedback' ),
				QRMS_Admin::SETTINGS_SLUG,
			),
			qrms_submenu_sluglari( $GLOBALS['submenu'][ QRMS_Admin::MENU_SLUG ] ),
			'menüde kalan satırlar'
		);
	}
);

qrms_test(
	'modül lisansta aktif değilken hiçbir sayfa kaydedilmez',
	function () {
		qrms_module_yorum_feedback_admin_menu();

		qrms_assert_same( array(), $GLOBALS['qrms_test']['submenus'], 'kayıt yok' );
	}
);

qrms_test(
	'rozet modülün TEK satırında toplanır — bekleyen yorum dahil',
	function () {
		// Alt satırlar kalktığı için bekleyen iş yalnızca "Yorum & Feedback"
		// satırında görünebilir. qrm_pro_menu_badge_state() istek başına bir kez
		// hesaplanıp static'te tutulduğu için sayaçlar ilk çağrıdan ÖNCE
		// sabitlenir (yorum sayaçları burada memo üzerinden verilir).
		$GLOBALS['qrm_pro_stats_memo'] = qrms_sahte_yorum_stats( array( 'pending' => 3 ) );

		$label = qrms_module_yorum_feedback_menu_label( 'Yorum & Feedback', 'yorum-feedback' );

		qrms_assert_contains( 'Yorum & Feedback', $label, 'modül adı korunur' );
		qrms_assert_contains( 'update-count', $label, 'bekleyen iş rozeti' );

		// 3 onay bekleyen yorum + 2 okunmamış gönderim (qrm_cf_unread_total taklidi).
		qrms_assert_contains( '>5<', $label, 'iki sayaç toplanır' );
		qrms_assert_contains( 'onay bekleyen yorum', $label, 'rozetin sebebi başlıkta' );

		qrms_assert_same(
			'QR Masa',
			qrms_module_yorum_feedback_menu_label( 'QR Masa', 'qr-masa' ),
			'başka modülün etiketine dokunulmaz'
		);
	}
);

qrms_test(
	'hub dört ekranı da üç başlık altında basar',
	function () {
		$html = qrms_yf_hub_html();

		qrms_assert_contains( 'qrms-hub-grid', $html, 'ortak kart ızgarası' );
		qrms_assert_contains( 'qrms-hub-stats', $html, 'özet kutuları' );

		foreach ( qrm_pro_admin_pages() as $slug => $page ) {
			qrms_assert_contains( 'page=' . $slug, $html, $slug . ' kartı' );
		}

		// Üç grup başlığı, kayıt defterindeki sırayla.
		$sira = array();
		foreach ( qrm_pro_admin_page_groups() as $baslik ) {
			$konum = strpos( $html, '>' . $baslik . '<' );
			qrms_assert_true( false !== $konum, $baslik . ' başlığı basılır' );
			$sira[] = $konum;
		}

		$sirali = $sira;
		sort( $sirali );
		qrms_assert_same( $sirali, $sira, 'başlık sırası: Yorumlar, Formlar, Ayarlar' );

		qrms_assert_contains( 'Ana yorum formu, iletişim formu', $html, 'Formlar kart açıklaması' );
		qrms_assert_false( false !== strpos( $html, 'page=qrms-yf-form-alanlari' ), 'eski form alanları kartı yok' );
		qrms_assert_false( false !== strpos( $html, 'page=qrms-yf-iletisim' ), 'eski iletişim kartı yok' );
		qrms_assert_false( false !== strpos( $html, 'Özel Formlar' ), 'eski özel formlar adı yok' );
		qrms_assert_false( false !== strpos( $html, 'İçgörüler' ), 'kaldırılan kart yok' );
	}
);

qrms_test(
	'dört özet kutusunun dördü de filtrelenmiş listeye gider',
	function () {
		$html = qrms_yf_hub_html( array( 'pending' => 4, 'total' => 40, 'approved' => 36 ) );

		// Kutular <a> olarak basılır: sayıyı gören, kayıtlara da tıklayarak gider.
		qrms_assert_same( 4, substr_count( $html, 'qrms-hub-stat-label' ), 'dört kutu' );
		qrms_assert_same( 4, substr_count( $html, '<a class="qrms-hub-stat' ), 'dördü de bağlantı' );

		qrms_assert_contains( 'page=qrms-yf-yorumlar&amp;durum=bekleyen', $html, 'onay bekleyen -> bekleyen filtresi' );
		qrms_assert_contains( 'page=qrms-yf-yorumlar&amp;durum=onayli', $html, 'genel ortalama -> yayındakiler' );
		qrms_assert_contains( 'page=qrms-yf-formlar&amp;tab=submissions', $html, 'okunmamış gönderim -> gönderiler' );

		// Bekleyen iş varken kutu vurgulanır.
		qrms_assert_contains(
			'qrms-hub-stat-alert',
			qrms_yf_stat_class( $html, 'Onay Bekleyen' ),
			'onay bekleyen kutusu vurgulanır'
		);
		qrms_assert_contains( '3.9 ★', $html, 'ortalama basılır' );
	}
);

qrms_test(
	'bekleyen yorum yokken kutu vurgulanmaz, puan yokken "—" yazmaz',
	function () {
		$html = qrms_yf_hub_html( array( 'pending' => 0, 'total' => 0, 'approved' => 0 ) );

		qrms_assert_false(
			false !== strpos( qrms_yf_stat_class( $html, 'Onay Bekleyen' ), 'qrms-hub-stat-alert' ),
			'vurgu yok'
		);

		// Boş ortalamanın "—" hâli hiçbir şey anlatmıyordu.
		qrms_assert_contains( 'Henüz puan yok', $html, 'boş ortalama açıklanır' );
		qrms_assert_false(
			false !== strpos( $html, '<span class="qrms-stat-value">—</span>' ),
			'kutuda tire kalmadı'
		);
	}
);

qrms_test(
	'hiç yorum yokken kartların üstünde kısa kod yönlendirmesi çıkar',
	function () {
		$bos = qrms_yf_hub_html( array( 'pending' => 0, 'total' => 0, 'approved' => 0 ) );

		qrms_assert_contains( 'qrms-hub-hint', $bos, 'yönlendirme basılır' );
		qrms_assert_contains( '[qr_menu_reviews]', $bos, 'kısa kod' );
		qrms_assert_contains( 'data-qrms-copy=', $bos, 'kopyalama butonu (ortak admin.js)' );
		qrms_assert_contains( 'QR kodunuzu', $bos, 'QR kodun paylaşılması gerektiği söylenir' );

		// Kartların ÜSTÜNDE: ilk kart ızgarasından önce gelir.
		qrms_assert_true(
			strpos( $bos, 'qrms-hub-hint' ) < strpos( $bos, 'qrms-hub-grid' ),
			'kartlardan önce'
		);

		// Tek yorum bile gelmişse yönlendirme kaybolur.
		$dolu = qrms_yf_hub_html( array( 'total' => 1, 'approved' => 1, 'pending' => 0 ) );

		qrms_assert_false( false !== strpos( $dolu, 'qrms-hub-hint' ), 'yorum gelince kaybolur' );
	}
);

qrms_test(
	'her ekranın ikonu dashicon\'dur',
	function () {
		// Emoji admin'de kutu karakterine düşebiliyor; kart ikonları dashicons
		// setinden gelmeli.
		foreach ( qrm_pro_admin_pages() as $slug => $page ) {
			qrms_assert_same( 0, strpos( $page['icon'], 'dashicons-' ), $slug . ' ikonu' );
		}
	}
);

/* ------------------------------------------------------------------------ */

echo "\n";

/* ---------------------------------------------------------------------------
 * 14. Açılış Ekranı (qr-acilis-ekrani)
 * ------------------------------------------------------------------------ */



/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P0 köprü / yorum-feedback 7-1)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';

qrms_test(
	'review kaynak metinleri katalogda; biçim dizeleri korunur',
	function () {
		$metinler = rma_ceviri_modul_stringleri( 'review' );
		$beklenen = array(
			'Devam Et →',
			'← Geri',
			'Gönder',
			'Güvenlik sorusu:',
			'0 (5__) ___ __ __',
			'%d Değerlendirme',
			'Henüz yayınlanmış bir değerlendirme yok. İlk yorumu siz bırakın!',
			'Daha Fazla Göster',
			'Anonim Misafir',
			'Misafir',
			'Kapat',
			'Kod ayrıca e-posta adresinize gönderildi.',
			'Tamam',
			'Tekrar Dene',
			'Lütfen geçerli bir e-posta adresi girin.',
			'Seçiniz…',
			'Bir şeyler ters gitti, lütfen tekrar deneyin.',
			'Devam etmek için lütfen tüm kriterleri puanlayın.',
			'Değerlendirmeniz için teşekkürler!',
			'Yükleniyor…',
			'Çok sık gönderim yapıyorsunuz, lütfen %d dakika sonra tekrar deneyin.',
			'"%s" alanı zorunludur.',
		);

		foreach ( $beklenen as $metin ) {
			qrms_assert_same(
				$metin,
				$metinler[ rma_ceviri_ui_anahtari( $metin ) ],
				$metin
			);
		}
	}
);

qrms_test(
	'yorum PHP iskeleti review köprüsüyle sarılı; honeypot çevrilmez',
	function () {
		$form = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-render.php' );
		$liste = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/shortcode-reviews.php' );
		$kart  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/reviews-list.php' );
		$popup = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/popup-render.php' );
		$cf    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );

		qrms_assert_contains( "qrm_ceviri_review(__('Devam Et →', 'qrms'))", $form, 'Devam Et' );
		qrms_assert_contains( "qrm_ceviri_review(__('← Geri', 'qrms'))", $form, 'Geri' );
		qrms_assert_contains( "qrm_ceviri_review(__('Gönder', 'qrms'))", $form, 'Gönder' );
		qrms_assert_contains( "qrm_ceviri_review(__('Güvenlik sorusu:', 'qrms'))", $form, 'captcha' );
		qrms_assert_contains( "qrm_ceviri_review(__('0 (5__) ___ __ __', 'qrms'))", $form, 'telefon maskesi' );
		qrms_assert_contains( '<label for="qrm_website">Web sitesi</label>', $form, 'honeypot TR kalır' );
		qrms_assert_contains( 'name="qrm_website"', $form, 'honeypot name' );
		qrms_assert_contains( 'çevirmek yemi değiştirir', $form, 'honeypot gerekçe' );

		qrms_assert_contains( "qrm_ceviri_review(__('%d Değerlendirme', 'qrms'))", $liste, 'çoğul biçim' );
		qrms_assert_contains( "qrm_ceviri_review(__('Daha Fazla Göster', 'qrms'))", $liste, 'load more' );
		qrms_assert_contains( "qrm_ceviri_review(__('Anonim Misafir', 'qrms'))", $kart, 'anonim' );
		qrms_assert_contains( "qrm_ceviri_review(__('Misafir', 'qrms'))", $kart, 'misafir' );
		qrms_assert_contains( "qrm_ceviri_review(__('Kapat', 'qrms'))", $popup, 'popup kapat' );
		qrms_assert_contains( "qrm_ceviri_review(__('Seçiniz…', 'qrms'))", $cf, 'select placeholder' );
		qrms_assert_contains( '<label for="<?php echo esc_attr($prefix); ?>-website">Web sitesi</label>', $cf, 'cf honeypot TR' );
	}
);

qrms_test(
	'yorum JS metin() yedeği korur; AJAX rma_get_current_lang zincirinde',
	function () {
		$js    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-script.php' );
		$ajax  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-review.php' );
		$cagri = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/rewards.php' );
		$sec   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/security.php' );
		$set   = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php' );

		qrms_assert_contains( 'qrm_ceviri_review_js_metinleri', $js, 'JS yükü' );
		qrms_assert_contains( 'function metin(anahtar, yedek)', $js, 'metin köprüsü' );
		qrms_assert_contains( "metin('genericError'", $js, 'generic yedek' );
		qrms_assert_contains( "metin('rateRequired'", $js, 'kriter yedek' );
		qrms_assert_contains( "metin('thanks'", $js, 'teşekkür yedek' );
		qrms_assert_contains( "metin('loading'", $js, 'yükleniyor yedek' );
		qrms_assert_contains( "credentials: 'same-origin'", $js, 'cookie gider' );
		qrms_assert_contains( "qrm_ceviri_review(__('Değerlendirmeniz yayınlandı.', 'qrms'))", $ajax, 'teşekkür AJAX' );
		qrms_assert_contains( "qrm_ceviri_review(__('Lütfen geçerli bir e-posta adresi girin.', 'qrms'))", $cagri, 'e-posta AJAX' );
		qrms_assert_contains( "qrm_ceviri_review(__('Çok sık gönderim yapıyorsunuz, lütfen %d dakika sonra tekrar deneyin.', 'qrms'))", $sec, 'cooldown biçim' );
		qrms_assert_contains( 'function qrm_ceviri_review', $set, 'köprü yardımcı' );
		qrms_assert_contains( 'rma_get_current_lang', $set, 'AJAX dil notu' );
	}
);

qrms_test(
	'qrm_ceviri_review çeviri yoksa Türkçe; P1 option/DB sarıldı',
	function () {
		qrms_assert_same( 'Devam Et →', qrm_ceviri_review( 'Devam Et →' ), 'tablo yokken Türkçe' );
		qrms_assert_same(
			'3 Değerlendirme',
			sprintf( qrm_ceviri_review( '%d Değerlendirme' ), 3 ),
			'çoğul sayı korunur'
		);

		$set  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php' );
		$inst = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php' );
		$form = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-render.php' );

		qrms_assert_contains( 'function qrm_ceviri_option', $set, 'option köprü' );
		qrms_assert_contains( 'function qrm_ceviri_form_alan', $set, 'form_field köprü' );
		qrms_assert_contains( "'form_title' => 'Deneyiminizi Paylaşın'", $set, 'varsayılan duruyor' );
		qrms_assert_contains( 'item_type=form_field', $inst, 'install P1 notu' );
		qrms_assert_contains( "'Adınız Soyadınız'", $inst, 'field_label tohum' );
		qrms_assert_contains( "qrm_ceviri_option('qrm_settings.form_title'", $form, 'form başlığı sarıldı' );
		qrms_assert_contains( 'qrm_ceviri_form_alan($f->id, $f->field_label)', $form, 'alan etiketi sarıldı' );
		qrms_assert_false(
			(bool) preg_match( "/qrm_ceviri_review\(__\('Deneyiminizi Paylaşın'/", $set ),
			'option review köprüsüne düşmedi'
		);
	}
);

echo "\nQR Çeviri (P0 köprü / header-footer-builder)\n";
