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
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/functions.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/forms/review-form.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/admin/menu.php';

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
		$s = qrm_cf_sanitize_form_settings(
			array(
				'step_labels' => array(
					1 => 'Bir',
					5 => 'Beş',
					0 => 'geçersiz',
					'x' => 'yok',
				),
			)
		);
		qrms_assert_same( 'Bir', $s['step_labels'][1], 'adım 1' );
		qrms_assert_same( 'Beş', $s['step_labels'][5], 'adım 5' );
		qrms_assert_false( isset( $s['step_labels'][0] ), 'sıfır yok' );
	}
);

qrms_test(
	'rating_group ve google_reward yalnızca sistem paletinde',
	function () {
		$normal = qrm_cf_field_types( false );
		$sys    = qrm_cf_field_types( true );

		qrms_assert_false( isset( $normal['rating_group'] ), 'özel formda rating_group yok' );
		qrms_assert_false( isset( $normal['google_reward'] ), 'özel formda google_reward yok' );
		qrms_assert_true( ! empty( $sys['rating_group']['is_widget'] ), 'rating_group widget' );
		qrms_assert_true( ! empty( $sys['google_reward']['is_system_only'] ), 'google_reward sistem' );
		qrms_assert_false( qrm_cf_is_valid_field_type( 'rating_group' ), 'özel forma widget yazılmaz' );

		$ok = qrm_cf_validate_value( array( 'id' => 0, 'field_type' => 'rating_group', 'label' => 'Puan' ), 'x' );
		qrms_assert_true( $ok['ok'], 'rating_group doğrulaması boş geçer' );
		$ok2 = qrm_cf_validate_value( array( 'id' => 0, 'field_type' => 'google_reward', 'label' => 'Ödül' ), 'x' );
		qrms_assert_true( $ok2['ok'], 'google_reward doğrulaması boş geçer' );
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
