<?php
/**
 * İletişim formu tam genişlik ve alan sütunu testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nİletişim formu — tam genişlik ve alan sütunu\n";

qrms_test(
	'alan sütun genişliği yalnızca full veya half kabul eder',
	function () {
		qrms_assert_same( 'full', qrm_pro_sanitize_column_width( 'full' ), 'full' );
		qrms_assert_same( 'half', qrm_pro_sanitize_column_width( 'half' ), 'half' );
		qrms_assert_same( 'full', qrm_pro_sanitize_column_width( '33' ), 'bilinmeyen değer tam genişlik' );
		qrms_assert_same( 'full', qrm_pro_sanitize_column_width( '' ), 'boş değer tam genişlik' );
	}
);

qrms_test(
	'kayıtsız alanda eski otomatik yarım genişlik korunur; kayıtlı değer baskın gelir',
	function () {
		qrms_assert_same(
			'half',
			qrm_pro_field_column_width( array( 'field_key' => 'customer_name' ), 'review' ),
			'eski yorum alanı: ad'
		);
		qrms_assert_same(
			'full',
			qrm_pro_field_column_width( array( 'field_key' => 'comment' ), 'review' ),
			'yorum metni tam genişlik'
		);
		qrms_assert_same(
			'full',
			qrm_pro_field_column_width(
				array( 'field_key' => 'customer_name', 'column_width' => 'full' ),
				'review'
			),
			'kayıtlı tam genişlik otomatik half\'i ezer'
		);
		qrms_assert_same(
			'half',
			qrm_pro_field_column_width( array( 'field_type' => 'email' ), 'custom' ),
			'eski özel form e-posta alanı'
		);
		qrms_assert_same(
			'full',
			qrm_pro_field_column_width(
				array( 'field_type' => 'email', 'column_width' => 'full' ),
				'custom'
			),
			'yeni e-posta alanı tekli kalabilir'
		);
	}
);

qrms_test(
	'iletişim kısa kodu fullbleed, yorum listesi boxed kalır',
	function () {
		$iletisim = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/shortcode-contact.php' );
		$yorum    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/frontend/shortcode-reviews.php' );
		$ozel     = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );

		qrms_assert_contains( 'qrm-form-fullbleed', $iletisim, 'iletişim wrapper' );
		qrms_assert_contains( 'qrm-form-fullbleed', $ozel, 'özel form wrapper' );
		qrms_assert_false(
			false !== strpos( $yorum, 'qrm-form-fullbleed' ),
			'yorum listesi max-width 800px kutusunu korur'
		);
	}
);

qrms_test(
	'özel form alanı tipi otomatik half class eklemez',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/render.php' );

		qrms_assert_false(
			false !== strpos( $kaynak, "in_array(\$type, ['text', 'email', 'tel', 'number', 'date']" ),
			'tipe göre otomatik ikili yok'
		);
		qrms_assert_contains( "qrm_pro_field_column_width(\$field, 'custom')", $kaynak, 'alan bazında genişlik' );
	}
);

qrms_test(
	'Elementor max-width CSS ile ezilir, yatay kaydırma clip ile kesilir',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/assets/css/frontend-form.css' );

		qrms_assert_contains( 'width: 100vw', $css, '100vw' );
		qrms_assert_contains( 'overflow-x: clip', $css, 'yatay kaydırma yok' );
		qrms_assert_contains( 'max-width: 100% !important', $css, 'Elementor max-width ezilir' );
		qrms_assert_contains( 'padding-left: 0 !important', $css, 'Elementor yatay padding ezilir' );
		qrms_assert_contains( '@media (max-width: 767px)', $css, 'mobil kırılım' );
		qrms_assert_contains( '.qrm-form-fullbleed .qrm-input-group.half', $css, 'ikili alan class\'ı' );
		qrms_assert_contains( 'qrms-contact-fullwidth', $css, 'native Elementor Form section class' );
		qrms_assert_contains( '.qrm-form-fullbleed .qrm-form-box', $css, 'kart kapsayıcıyı doldurur' );
		qrms_assert_false( false !== strpos( $css, 'border-radius: 0' ), 'kart köşesi ezilmez' );
	}
);

qrms_test(
	'form düzenleyici sütun genişliğini alan bazında kaydeder',
	function () {
		$builder = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/custom-form-builder.php' );
		$alanlar = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/form-builder.php' );
		$js      = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/assets/js/form-preview.js' );
		$sema    = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/db.php' );
		$install = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/install.php' );

		qrms_assert_contains( "column_width: 'full'", $builder, 'yeni özel alan varsayılanı tekli' );
		qrms_assert_contains( 'data-edit="column_width"', $builder, 'özel form düzenleme paneli' );
		qrms_assert_contains( 'name="fields[<?php echo intval($f->id); ?>][column_width]"', $alanlar, 'yorum formu sütun seçimi' );
		qrms_assert_contains( 'select[name*="[column_width]"]', $js, 'önizleme seçimi okur' );
		qrms_assert_false( false !== strpos( $js, 'HALF_KEYS' ), 'sabit half anahtar listesi kalktı' );
		qrms_assert_contains( "column_width varchar(10) DEFAULT 'full' NOT NULL", $sema, 'özel form şeması' );
		qrms_assert_contains( "column_width varchar(10) DEFAULT 'full' NOT NULL", $install, 'yorum formu şeması' );
		qrms_assert_contains( 'qrm_pro_migrate_column_widths', $install, 'eski alanlar bir kez taşınır' );
	}
);

qrms_test(
	'ön yüz form CSS dosyası asset_version ile kuyruğa alınır',
	function () {
		$modul = file_get_contents( QRMS_PLUGIN_DIR . 'modules/yorum-feedback/module.php' );

		qrms_assert_contains( "add_action( 'wp_enqueue_scripts', 'qrms_module_yorum_feedback_frontend_form_assets' )", $modul, 'kanca' );
		qrms_assert_contains( 'asset_version( \'modules/yorum-feedback/assets/css/frontend-form.css\' )', $modul, 'cache bust' );
	}
);

