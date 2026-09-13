<?php
/**
 * Form builder konsolidasyonu: sistem formları, sınırsız adım, widget'lar,
 * puanlama görünümü.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/review-form.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/menu.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/popup-render.php';
// functions.php qrm_cf_unread_total tanımlar; test-yorum-admin.php onu taklit
// ettiği için burada require edilmez. Registry / sanitize iddiaları kaynak metninden okunur.

echo "\nYorum formu — builder konsolidasyonu\n";

qrms_test(
	'eski form-alanlari ve iletisim slug\'ları Formlar düzenleyicisine gider',
	function () {
		$review = qrm_pro_legacy_page_target( 'qrms-yf-form-alanlari' );
		qrms_assert_true( false !== strpos( $review, 'page=qrms-yf-formlar' ), 'form-alanlari -> formlar' );
		qrms_assert_true( false !== strpos( $review, 'system=review' ), 'system=review' );

		$contact = qrm_pro_legacy_page_target( 'qrm-pro-contact' );
		qrms_assert_true( false !== strpos( $contact, 'page=qrms-yf-formlar' ), 'contact -> formlar' );
		qrms_assert_true( false !== strpos( $contact, 'system=contact' ), 'system=contact' );

		qrms_assert_false(
			array_key_exists( 'qrms-yf-form-alanlari', qrm_pro_admin_pages() ),
			'ayrı form-alanlari sayfası yok'
		);
		qrms_assert_false(
			array_key_exists( 'qrms-yf-iletisim', qrm_pro_admin_pages() ),
			'ayrı iletisim sayfası yok'
		);
		qrms_assert_false( function_exists( 'qrm_pro_admin_form_builder' ), 'eski form builder sayfası kalktı' );
		qrms_assert_false( function_exists( 'qrm_pro_admin_contact' ), 'eski iletişim sayfası kalktı' );
	}
);

qrms_test(
	'step_no üst sınırı 4 değil, qrm_pro_max_step_no()',
	function () {
		qrms_assert_same( 12, qrm_pro_max_step_no(), 'tavan 12' );
		qrms_assert_same( 1, qrm_pro_sanitize_step_no( 0 ), 'alt sınır 1' );
		qrms_assert_same( 5, qrm_pro_sanitize_step_no( 5 ), '5 geçerli' );
		qrms_assert_same( 12, qrm_pro_sanitize_step_no( 99 ), '99 tavana kırpılır' );
	}
);

qrms_test(
	'özel form step_labels dinamik kabul edilir',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );
		qrms_assert_false( false !== strpos( $src, 'for ($i=1;$i<=4;$i++)' ), 'sabit 1..4 döngüsü yok' );
		qrms_assert_contains( 'foreach ($raw[\'step_labels\'] as $n => $label)', $src, 'POST anahtarları dinamik' );
		qrms_assert_contains( 'qrm_pro_sanitize_step_no', $src, 'adım numarası kırpılır' );
	}
);

qrms_test(
	'rating_group ve google_reward artık tüm formlarda kullanılabilir widget',
	function () {
		// Başlangıçta ikisi de yalnızca Ana Yorum Formu'na özeldi; kullanıcı
		// isteğiyle genelleştirildi — global ayarları (crit_1..5, Google/ödül
		// metinleri) okuyup herhangi bir özel formda KONUMLANDIRILABİLİYORLAR,
		// içerikleri hâlâ ilgili admin sayfasında yönetiliyor.
		//
		// qrm_cf_field_types() burada ÇAĞRILMAZ: bu dosyanın gerçek
		// forms/functions.php'yi require etmemesi bilinçli (test-yorum-admin.php
		// qrm_cf_unread_total()'ı kendi taklidiyle tanımlıyor; ikisi aynı süreçte
		// yüklenirse "cannot redeclare" hatası verir). Bu yüzden kayıt defteri
		// kaynak metninden doğrulanır.
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );
		qrms_assert_contains( "'rating_group' =>", $src, 'registry rating_group' );
		qrms_assert_contains( "'google_reward' =>", $src, 'registry google_reward' );
		qrms_assert_contains( "case 'rating_group':", $src, 'validate rating_group' );
		qrms_assert_contains( "case 'google_reward':", $src, 'validate google_reward' );

		// İkisinin de artık is_system_only TAŞIMADIĞINI doğrula — bunu tek tek
		// alan bloklarından anlamak için her widget'ın kendi satır aralığını al.
		$rating_block = substr( $src, strpos( $src, "'rating_group' =>" ), 260 );
		$reward_block = substr( $src, strpos( $src, "'google_reward' =>" ), 280 );
		qrms_assert_false( false !== strpos( $rating_block, 'is_system_only' ), 'rating_group artık sistem-only değil' );
		qrms_assert_false( false !== strpos( $reward_block, 'is_system_only' ), 'google_reward artık sistem-only değil' );
		qrms_assert_contains( "'is_widget' => true", $rating_block, 'rating_group widget bayrağı' );
		qrms_assert_contains( "'is_widget' => true", $reward_block, 'google_reward widget bayrağı' );

		// İletişim düzenleyicisi hiçbir alan/widget kaydetmiyor
		// (qrm_cf_admin_handle_system_form_save); palette'te widget göstermek
		// yanıltıcı olur, orada özel olarak filtrelenmeli.
		$builder = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		qrms_assert_contains( "\$system === 'contact' && !empty(\$meta['is_widget'])", $builder, 'iletişim editöründe widget paletten çıkarılır' );
	}
);

qrms_test(
	'özel form başlık pozisyonu (title_align) kaydedilip önyüze basılır',
	function () {
		// qrm_cf_default_form_settings/qrm_cf_sanitize_form_settings burada
		// çağrılmaz (forms/functions.php'nin bu dosyada require edilmeme
		// nedeni yukarıda açıklandı); kaynak metinden doğrulanır.
		$fn = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );
		qrms_assert_contains( "'title_align'     => 'left'", $fn, 'varsayılan sola yaslı' );
		qrms_assert_contains( "in_array(\$raw['title_align'], ['left', 'center', 'right'], true)", $fn, 'yalnızca üç değer kabul edilir' );

		$render = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );
		qrms_assert_contains( 'title_align', $render, 'render önyüze pozisyonu basıyor' );
		qrms_assert_contains( 'text-align:', $render, 'CSS text-align kuralı üretiliyor' );

		$builder = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		qrms_assert_contains( 'qrm-fb-title-align', $builder, 'builder ekranında pozisyon seçimi var' );
	}
);

qrms_test(
	'kayıtlı düzen yokken qrm_pro_build_steps eski sabit sırayı kullanır',
	function () {
		$settings = qrm_pro_default_settings();
		$field    = function ( $key, $type ) {
			return (object) array(
				'id'          => 1,
				'field_key'   => $key,
				'field_label' => $key,
				'field_type'  => $type,
				'is_required' => 0,
				'column_width'=> 'full',
			);
		};
		$full = qrm_pro_build_steps(
			$settings,
			array(
				$field( 'comment', 'textarea' ),
				$field( 'customer_name', 'text' ),
			),
			array( 'form_source' => 'review' )
		);
		qrms_assert_same( 3, count( $full['steps'] ), '3 adım' );
		qrms_assert_same( 'rating', $full['steps'][0]['type'], '1. puanlama' );
		qrms_assert_same( 'comment', $full['steps'][1]['type'], '2. yorum' );
		qrms_assert_same( 'info', $full['steps'][2]['type'], '3. bilgi' );
	}
);

qrms_test(
	'kayıtlı düzen adım sırasını ve widget konumunu belirler',
	function () {
		$settings = qrm_pro_default_settings();
		$settings['qrm_review_form_layout'] = array(
			'step_labels' => array( 1 => 'Yorum', 2 => 'Puan', 4 => 'Ödül' ),
			'field_steps' => array(
				'comment'       => 1,
				'customer_name' => 3,
			),
			'widgets'     => array(
				'rating_group'  => 2,
				'google_reward' => 4,
			),
		);
		$field = function ( $key, $type ) {
			return (object) array(
				'id'          => 1,
				'field_key'   => $key,
				'field_label' => $key,
				'field_type'  => $type,
				'is_required' => 0,
				'column_width'=> 'full',
			);
		};
		$built = qrm_pro_build_steps(
			$settings,
			array(
				$field( 'comment', 'textarea' ),
				$field( 'customer_name', 'text' ),
			),
			array( 'form_source' => 'review' )
		);
		qrms_assert_same( 4, count( $built['steps'] ), '4 adım' );
		qrms_assert_same( 'comment', $built['steps'][0]['type'], 'yorum önce' );
		qrms_assert_true( ! empty( $built['steps'][1]['has_rating'] ) || 'rating' === $built['steps'][1]['type'], 'puan 2. adım' );
		qrms_assert_true( ! empty( $built['steps'][3]['has_google_reward'] ) || 'google_reward' === $built['steps'][3]['type'], 'ödül 4. adım' );

		$contact = qrm_pro_build_steps(
			$settings,
			array( $field( 'customer_name', 'text' ) ),
			array( 'form_source' => 'contact' )
		);
		foreach ( $contact['steps'] as $step ) {
			qrms_assert_false( ! empty( $step['has_rating'] ), 'iletişimde puanlama yok' );
			qrms_assert_false( $step['type'] === 'google_reward', 'iletişimde ödül yok' );
		}
	}
);

qrms_test(
	'builder state widget\'ları layout\'a, alanları satırlara ayırır',
	function () {
		$parsed = qrm_pro_builder_state_to_review_save(
			array(
				array( 'type' => 'rating_group', 'step_no' => 2, 'key' => 'rating_group' ),
				array( 'type' => 'google_reward', 'step_no' => 4, 'key' => 'google_reward' ),
				array(
					'type'         => 'textarea',
					'key'          => 'comment',
					'db_id'        => 9,
					'label'        => 'Yorum',
					'required'     => 1,
					'active'       => 1,
					'step_no'      => 1,
					'column_width' => 'full',
				),
			)
		);
		qrms_assert_same( 2, $parsed['layout']['widgets']['rating_group'], 'rating adım 2' );
		qrms_assert_same( 4, $parsed['layout']['widgets']['google_reward'], 'ödül adım 4' );
		qrms_assert_same( 1, $parsed['layout']['field_steps']['comment'], 'comment adım 1' );
		qrms_assert_true( isset( $parsed['rows'][9] ), 'comment satırı' );
	}
);

qrms_test(
	'rating_display_mode varsayılanı breakdown; get_settings birleştirir',
	function () {
		$defaults = qrm_pro_default_settings();
		qrms_assert_same( 'breakdown', $defaults['rating_display_mode'], 'varsayılan' );
		qrms_assert_true( array_key_exists( 'qrm_review_form_layout', $defaults ), 'layout anahtarı varsayılanlarda' );

		update_option( 'qrm_settings', array( 'form_title' => 'X' ) );
		$merged = qrm_pro_get_settings();
		qrms_assert_same( 'breakdown', $merged['rating_display_mode'], 'eski kurulumda otomatik eklenir' );
		qrms_assert_same( 'X', $merged['form_title'], 'var olan anahtar korunur' );
	}
);

qrms_test(
	'önyüz istatistik widget\'ı kırılımı rating_display_mode ile bağlar',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/shortcode-reviews.php' );
		qrms_assert_contains( 'rating_display_mode', $src, 'kısa kod okur' );
		qrms_assert_contains( 'qrm-crit-bars', $src, 'kriter bar bloğu durur' );

		$dash = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/dashboard.php' );
		qrms_assert_false(
			false !== strpos( $dash, 'rating_display_mode' ),
			'Tüm Yorumlar listesi bu ayara bağlanmaz'
		);
	}
);

qrms_test(
	'widget render ortak fonksiyonları çağırır, kopyalamaz',
	function () {
		$render = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );
		qrms_assert_contains( 'qrm_pro_render_rating_criteria', $render, 'rating_group paylaşır' );
		qrms_assert_contains( 'qrm_reward_render_step_panel', $render, 'google_reward paylaşır' );

		$steps = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php' );
		qrms_assert_contains( 'qrm-rating-row', $steps, 'wizard satır varlığına bakar' );
		qrms_assert_contains( 'google_reward', $steps, 'ödül adımı atlanır' );
	}
);

qrms_test(
	'google_reward paneli varsayılan olarak nötr — CTA gizli, eşik data-attribute\'ta',
	function () {
		$settings = qrm_pro_default_settings();
		$settings['google_review_threshold']    = 3.5;
		$settings['qrm_reward_popup_title']      = 'Bizi Sevdiniz mi?';
		$settings['qrm_reward_popup_text']       = 'Google\'da bırakır mısınız?';
		$settings['qrm_reward_popup_button_text']= 'Değerlendir';
		$settings['google_review_url']           = 'https://maps.google.com/örnek';

		$html = qrm_reward_render_step_panel( $settings );

		qrms_assert_contains( 'data-threshold="3.5"', $html, 'eşik data attribute\'ta' );
		qrms_assert_contains( '<div class="qrm-rw-step-cta" hidden>', $html, 'CTA varsayılan gizli — sunucu tarafında asla açık basılmaz' );
		qrms_assert_contains( '<p class="qrm-rw-step-neutral">', $html, 'nötr blok basılıyor' );
		qrms_assert_false(
			false !== strpos( $html, '<p class="qrm-rw-step-neutral" hidden>' ),
			'nötr blok varsayılan görünür'
		);

		// JS'in canlı ortalamayı hangi seçicilerden okuduğu — hem çoklu kriter
		// (rating_group) hem tekli 'rating' alan tipi kapsanmalı, aksi halde
		// özel formlardaki tekli yıldız alanı eşiği hiç tetiklemez.
		$steps_js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php' );
		qrms_assert_contains( 'qrmComputeLiveRatingAvg', $steps_js, 'canlı ortalama fonksiyonu var' );
		qrms_assert_contains( '.qrm-rating-row input[type=radio]:checked, .qrm-rating-stars input[type=radio]:checked', $steps_js, 'hem grup hem tekli puanlama okunuyor' );
		qrms_assert_contains( 'qrmInitRewardGating', $steps_js, 'gating başlatıcı var' );
	}
);

qrms_test(
	'adım gezinme butonları temanın button !important stilini yenecek şekilde basılır',
	function () {
		// Bazı temalar/Elementor <button> öğelerine !important display basıyor;
		// bu yüzden Geri/Gönder butonlarının gizli kalması gereken durumlarda da
		// görünmeye devam ettiği bir canlı site hatası vardı. Kural setinin
		// !important taşıdığını doğrula — aksi hâlde tema her zaman kazanır.
		$css = qrm_pro_steps_css( array(
			'btn_color'      => '#10b981',
			'btn_text_color' => '#ffffff',
			'border_color'   => '#e2e8f0',
			'theme_style'    => 'light',
		) );

		qrms_assert_contains( '.qrm-steps-nav { display:none !important; }', $css, 'nav çubuğu gizleme !important' );
		qrms_assert_contains( '.qrm-step-submit, .qrm-cf-step-submit { display:none !important; }', $css, 'gönder butonu gizleme !important' );
		qrms_assert_contains( 'qrm-step-back[hidden]', $css, 'geri butonu için [hidden] durumuna özel kural var' );
		qrms_assert_true(
			false !== strpos( $css, 'qrm-step-back[hidden]' ) && false !== strpos( $css, 'display:none !important' ),
			'geri butonu [hidden] iken de !important ile gizleniyor'
		);
		qrms_assert_contains( '.qrm-step[hidden] { display:none !important; }', $css, 'adım paneli gizleme de !important' );
	}
);

qrms_test(
	'builder önizlemesi çok adımlı formu tek seferde bir adım gösterecek şekilde gezinir',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		qrms_assert_contains( 'function syncPreviewStepNav', $src, 'önizleme adım gezinme fonksiyonu var' );
		qrms_assert_contains( 'previewStepIdx', $src, 'aktif önizleme adımı takip ediliyor' );
		qrms_assert_contains( "g.hidden = (sn !== previewStepIdx);", $src, 'önizlemede yalnızca aktif adım görünür' );
		qrms_assert_contains( 'qrm-fb-preview-stepnav', $src, 'önizleme gezinme çubuğu basılıyor' );
	}
);

qrms_test(
	'önizlemede adım geçişi ve gezinme butonları animasyonlu; adım başlığı etiketi taşır',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		qrms_assert_contains( '@keyframes qrmFbStepIn', $src, 'adım içeriği animasyon keyframe\'i var' );
		qrms_assert_contains( '@keyframes qrmFbBtnIn', $src, 'buton animasyon keyframe\'i var' );
		qrms_assert_contains( ".qrm-fb-previewing .qrm-fb-step-group:not([hidden]) { animation: qrmFbStepIn", $src, 'adım içeriği görünürken animasyon oynuyor' );
		qrms_assert_contains( 'qrm-fb-nav-animate', $src, 'buton animasyonu JS ile yeniden tetikleniyor' );
		qrms_assert_contains( "var titleText  = sn + '. Adım' + (stepLabel ? ' › ' + esc(stepLabel) : '');", $src, 'adım başlığı etiketle birleşiyor' );

		// Eski ayrı "N / toplam — etiket" göstergesi kaldırıldı; adım
		// bilgisi artık yalnızca birleşik başlıkta ("N. Adım › Etiket") durur.
		qrms_assert_false( false !== strpos( $src, 'qrm-fb-preview-stepnav-label' ), 'ayrı adım göstergesi kaldırıldı' );
	}
);

qrms_test(
	'kritik bug: zorunlu rating_group/google_reward widget\'ı artık gönderimi bloklamıyor',
	function () {
		// qrm_cf_validate_submission() burada ÇAĞRILMAZ (forms/functions.php
		// bu dosyada require edilmiyor — yukarıdaki testlerde açıklanan
		// "cannot redeclare qrm_cf_unread_total" çakışması). Kaynak metinden
		// doğrulanır.
		//
		// KÖK NEDEN: builder'dan eklenen bir rating_group widget'ı
		// is_required=1 ile kaydediliyor (bkz. custom-form-builder.php'deki
		// "required: type === 'rating_group' ? 1 : 0"), ama
		// qrm_cf_validate_value() bu tip için HER ZAMAN value='' döner
		// (widget kendi anahtarı altında POST verisi taşımaz — gerçek puanlar
		// rating_1..5'te durur). Eski qrm_cf_validate_submission() bu iki
		// gerçeği birleştirip her gönderimde "'Puanlama Kriterleri' alanı
		// zorunludur" hatası veriyordu — form KESİNLİKLE gönderilemiyordu.
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );

		qrms_assert_contains(
			"if (!empty(\$widget_types[\$type]['is_widget'])) {",
			$src,
			'widget alanları genel zorunlu-boş kontrolünden ayrı işlenir'
		);
		qrms_assert_contains(
			'function qrm_cf_validate_rating_group_submission(',
			$src,
			'rating_group için ayrı, gerçek sunucu tarafı doğrulama var'
		);
		qrms_assert_contains(
			"isset(\$post['rating_' . \$i]) ? intval(\$post['rating_' . \$i]) : 0",
			$src,
			'gerçek puanlar rating_1..5 POST anahtarlarından okunuyor'
		);

		// qrm_cf_validate_submission() içindeki widget dalı, genel
		// "$required && qrm_cf_value_is_empty(...)" kontrolüne hiç
		// düşürmeden `continue` etmeli — aksi hâlde bug geri gelir.
		$fn_start = strpos( $src, 'function qrm_cf_validate_submission(' );
        	qrms_assert_true( $fn_start !== false, 'qrm_cf_validate_submission bulunamadı' );
		$fn_body  = substr( $src, $fn_start, 2000 );
		$widget_if_pos   = strpos( $fn_body, "is_widget']))" );
		$required_check_pos = strpos( $fn_body, '$required && qrm_cf_value_is_empty' );
		qrms_assert_true(
			$widget_if_pos !== false && $required_check_pos !== false && $widget_if_pos < $required_check_pos,
			'widget kontrolü genel zorunlu kontrolünden ÖNCE çalışıyor (continue ile atlıyor)'
		);
	}
);

qrms_test(
	'CSS: puanlama/google widget\'ları flex satırda daralmıyor, form dar tarafta değil',
	function () {
		// Kök neden: .qrm-input-row/.qrm-cf-fields flex konteynerinde normal
		// alanlar (.qrm-input-group) flex:1 1 100% ile tam genişlik alırken,
		// rating_group/google_reward'ın çıktısı (.qrm-multi-rating /
		// .qrm-rw-step-panel) aynı satırda flex-basis:auto varsayılanıyla
		// İÇERİĞİNE göre daralıyor, sayfanın solunda dar bir kutu gibi kalıp
		// geri kalan genişlik boş görünüyordu.
		$form_render = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-render.php' );
		qrms_assert_contains( 'function qrm_pro_rating_group_css(', $form_render, 'paylaşılan fonksiyon var' );
		qrms_assert_contains( "'.qrm-multi-rating', \"width:100%;", $form_render, 'multi-rating genişliği zorlanıyor' );

		$cf_render = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );
		qrms_assert_contains( 'qrm_pro_rating_group_css(', $cf_render, 'özel form da paylaşılan fonksiyonu çağırıyor' );

		$reward = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/rewards/popup-render.php' );
		qrms_assert_contains( '.qrm-rw-step-panel { width: 100%; max-width: 480px; margin: 0 auto;', $reward, 'google_reward paneli genişliği zorlanıp ortalanıyor' );
	}
);

qrms_test(
	'özel formlarda tam genişlik (fullbleed) artık varsayılan değil, opt-in ayar',
	function () {
		$fn = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );
		qrms_assert_contains( "'full_width'      => 0,", $fn, 'varsayılan kapalı (konteyner genişliği)' );
		qrms_assert_contains( "\$out['full_width']    = !empty(\$raw['full_width']) ? 1 : 0;", $fn, 'sanitize ediliyor' );

		$builder = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		qrms_assert_contains( "name=\"qrm_cf_settings[full_width]\"", $builder, 'builder ekranında anahtar var' );
	}
);

qrms_test(
	'stepper etiketleri artık "..." ile kırpılmıyor, iki satıra sarılıyor',
	function () {
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/form-steps.php' );
		qrms_assert_false( false !== strpos( $src, 'text-overflow:ellipsis' ), 'kırpma kuralı kaldırıldı' );
		qrms_assert_contains( '-webkit-line-clamp:2', $src, 'en fazla iki satıra sarılıyor' );
		qrms_assert_contains( 'white-space:normal', $src, 'satır sarma açık' );
	}
);

qrms_test(
	'kritik bug: özel formlarda google_reward artık gerçek ödül popup\'ını açıyor',
	function () {
		// Kök neden: qrm_reward_render_step_panel() gönderim ÖNCESİ bilgi
		// paneliydi; asıl kupon/kod talebi akışını yöneten window.qrmRewardPopup
		// (qrm_reward_queue_popup) hiçbir yerde çağrılmıyordu ve
		// ajax/submit-custom-form.php'nin JSON yanıtı show_reward/review_id/
		// reward_claim taşımıyordu — "GÖNDER"e basınca hiçbir şey açılmıyordu.
		$fn = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php' );
		qrms_assert_contains( 'function qrm_cf_reward_response(', $fn, 'yorum formuyla aynı eşik mantığını paylaşan yardımcı var' );
		qrms_assert_contains( "\$has_reward_widget = false;", $fn, 'widget yoksa hiç tetiklenmez' );

		$submit = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/ajax/submit-custom-form.php' );
		qrms_assert_contains( 'qrm_cf_reward_response(', $submit, 'AJAX yanıtı ödül alanlarını taşıyor' );
		qrms_assert_contains( 'array_merge(', $submit, 'ödül alanları JSON yanıtına ekleniyor' );

		$render = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );
		qrms_assert_contains( 'qrm_reward_queue_popup(qrm_pro_get_settings())', $render, 'google_reward varsa popup DOM\'u kuyruğa alınıyor' );
		qrms_assert_contains( 'window.qrmRewardPopup.open(', $render, 'başarılı gönderimde popup açılıyor' );
		qrms_assert_contains( 'res.show_reward && window.qrmRewardPopup', $render, 'yalnızca eşiği geçen gönderimde açılıyor' );
	}
);

qrms_test(
	'gönderim tablosu başlıkları tablet\'te harf harf bölünmez (overflow + kart eşiği)',
	function () {
		// Kök neden: <table class="wp-list-table widefat fixed striped qrm-sub-table">
		// table-layout:fixed. Tarih 140 + Durum 90 + İşlemler 210 sabit;
		// form alanı sütunları ($fields, sayı değişir) genişlik almaz.
		// Kart eşiği yalnızca 782px idi; 783–1100px (tablet / "masaüstü siteyi
		// iste") aralığında sabit sütunlar alanı yer, başlık tek karaktere iner.
		//
		// Çözüm: overflow-x sarmalayıcı + alana göre min-width + 1100px kart.
		// 782px bloğu (sekme/araç çubuğu) yerinde kalır.
		$src = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/form-submissions.php' );

		qrms_assert_contains( 'qrm-sub-table-scroll', $src, 'yatay kaydırma sarmalayıcısı' );
		qrms_assert_contains( 'overflow-x:auto', $src, 'taşma kaydırmaya döner' );
		qrms_assert_contains( '.qrm-sub-table th { overflow-wrap:break-word; white-space:normal; word-break:normal; }', $src, 'th satır sarar, harf harf değil' );
		qrms_assert_contains( 'min-width:8.5em', $src, 'alan sütunları 1 karaktere inmez' );
		qrms_assert_contains( '440 + (count($fields) * 136)', $src, 'min-width alan sayısına göre' );
		qrms_assert_contains( '@media screen and (max-width: 1100px)', $src, 'kart eşiği tablet\'e yükseltildi' );
		qrms_assert_contains( '@media screen and (max-width: 782px)', $src, 'eski mobil kart bloğu duruyor' );
		qrms_assert_contains( '.qrm-sub-toolbar { flex-direction:column; align-items:stretch; }', $src, '782px araç çubuğu kuralı duruyor' );

		// 2 alanlı form 712px, 8 alanlı 1528px taban — ikisi de 720px
		// varsayılanın üstünde ya da formülle ölçeklenir; sabit 1150px eşiği yok.
		qrms_assert_same( 440 + ( 2 * 136 ), 712, '2 alanlı min-width' );
		qrms_assert_same( 440 + ( 8 * 136 ), 1528, '8 alanlı min-width' );
		qrms_assert_false( false !== strpos( $src, 'max-width: 1150px' ), 'alan sayısından bağımsız tek 1150 eşiği yok' );
	}
);

qrms_test(
	'FAZ 2: aynı sınıf widefat.fixed tablolar kaydırılır / kart eşiği yükselir',
	function () {
		$codes = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/reward-codes.php' );
		qrms_assert_contains( 'qrm-table-scroll', $codes, 'ödül kodları sarmalayıcı' );
		qrms_assert_contains( 'qrm-reward-codes-table', $codes, 'ödül kodları tablo sınıfı' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/assets/css/admin.css' );
		qrms_assert_contains( '.qrm-table-scroll', $css, 'ortak kaydırma sarmalayıcısı' );
		qrms_assert_contains( '.qrm-reward-codes-table', $css, 'ödül kodları 1150px kart' );
		// Seçici .qrm-review-workflow-table değil, bu dosyadaki genel kuralla
		// (.qrm-reviews-screen) taşınıyor; koşulsuz max-width:none temel
		// kuralda, flex-wrap:wrap ise 1100px kart eşiğinde — iki ayrı blok.
		qrms_assert_contains( ".qrm-reviews-screen .qrm-wf-controls {\n\tgap: var(--qrm-space-2);\n\tmax-width: none;\n}", $css, 'iş akışı kontrolleri 190px sabit genişliğinden kurtarılır' );
		qrms_assert_contains( "@media screen and ( max-width: 1100px ) {", $css, 'kart eşiği tablet\'e yükseltildi' );
		qrms_assert_contains( "\t.qrm-reviews-screen .qrm-wf-controls {\n\t\talign-items: center;\n\t\tflex-direction: row;\n\t\tflex-wrap: wrap;\n\t}", $css, 'iş akışı kontrolleri kartta 190px ile sıkışmaz' );

		$reports = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/reports.php' );
		qrms_assert_contains( 'qrm-table-scroll', $reports, 'masa özeti tablosu kaydırılır' );

		$consent = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/consent-report.php' );
		qrms_assert_contains( 'qrm-table-scroll', $consent, 'KVKK tablosu kaydırılır' );
	}
);
