<?php
/**
 * QR Restoran Menü — native Elementor widget kayıt testleri.
 *
 * Gerçek Elementor kurulumu gerekmez; kayıt zamanlaması ve Elementor yokken
 * fatal oluşmaması doğrulanır.
 *
 * @package QR_Menu_Suite
 */

echo "\nQR Restoran Menü — native Elementor widget (1-2-8)\n";

qrms_test(
	'Elementor yokken class-elementor-widget.php yüklendiğinde fatal oluşmaz',
	function () {
		$dosya = QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-elementor-widget.php';
		qrms_assert_true( is_readable( $dosya ), 'dosya okunabilir' );

		require_once $dosya;

		qrms_assert_true( function_exists( 'rma_bootstrap_elementor_menu_widget' ), 'bootstrap fonksiyonu tanımlı' );
		qrms_assert_false( class_exists( 'RMA_Elementor_Menu_Widget', false ), 'Elementor yokken widget sınıfı tanımlanmamalı' );
	}
);

qrms_test(
	'kaynak: widget kaydı elementor/loaded sonrasına bağlı (plugins_loaded erken çıkış hatası giderildi)',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/class-elementor-widget.php' );

		qrms_assert_contains( "add_action( 'elementor/loaded'", $kaynak, 'elementor/loaded kancası' );
		qrms_assert_contains( "add_action( 'elementor/widgets/register'", $kaynak, 'widgets/register kancası' );
		qrms_assert_contains( 'do_shortcode( \'[restaurant_menu', $kaynak, 'shortcode render köprüsü' );
		qrms_assert_contains( 'qrms-restoran-menu', $kaynak, 'özel Elementor kategorisi' );
		qrms_assert_false(
			strpos( $kaynak, "add_action( 'plugins_loaded', 'rma_register_elementor_addon'" ) !== false,
			'eski plugins_loaded + erken return deseni kaldırıldı'
		);
	}
);

qrms_test(
	'stub Elementor: elementor/loaded sonrası widget sınıfı kayıt edilir',
	function () {
		if ( ! class_exists( '\Elementor\Widget_Base', false ) ) {
			// Minimal Elementor API taklidi — gerçek Elementor değil.
			eval( '
				namespace Elementor {
					class Widget_Base {
						public function get_settings_for_display() { return array( "show_search" => "yes" ); }
					}
					class Controls_Manager {
						const TAB_CONTENT = "content";
					}
					class Elements_Manager {
						public function add_category( $id, $args ) {
							$GLOBALS["qrms_test"]["elementor_categories"][ $id ] = $args;
						}
					}
					class Widgets_Manager {
						public $registered = array();
						public function register( $widget ) {
							$this->registered[] = $widget;
						}
					}
				}
			' );
		}

		$GLOBALS['qrms_test']['elementor_categories'] = array();
		$GLOBALS['qrms_test']['elementor_widgets']    = array();

		// Yeniden bootstrap: integration fonksiyonunu doğrudan çağır.
		qrms_assert_true( function_exists( 'rma_register_elementor_menu_widget_integration' ), 'integration fonksiyonu mevcut' );

		rma_register_elementor_menu_widget_integration();

		qrms_assert_true( class_exists( 'RMA_Elementor_Menu_Widget', false ), 'widget sınıfı tanımlandı' );

		$widget = new RMA_Elementor_Menu_Widget();
		qrms_assert_same( 'rma_menu_widget', $widget->get_name(), 'widget adı asset tespitiyle uyumlu' );
		qrms_assert_same( array( 'qrms-restoran-menu' ), $widget->get_categories(), 'kategori' );

		$mgr = new \Elementor\Widgets_Manager();
		rma_register_elementor_menu_widget_instance( $mgr );
		qrms_assert_same( 1, count( $mgr->registered ), 'widgets manager üzerinden kayıt' );
		qrms_assert_true( $mgr->registered[0] instanceof RMA_Elementor_Menu_Widget, 'kayıtlı örnek doğru sınıf' );

		$elements = new \Elementor\Elements_Manager();
		rma_register_elementor_menu_widget_category( $elements );
		qrms_assert_true( isset( $GLOBALS['qrms_test']['elementor_categories']['qrms-restoran-menu'] ), 'panel kategorisi eklendi' );
	}
);

qrms_test(
	'widget render: [restaurant_menu] shortcode köprüsünü kullanır',
	function () {
		if ( ! class_exists( 'RMA_Elementor_Menu_Widget', false ) ) {
			rma_register_elementor_menu_widget_integration();
		}
		if ( ! class_exists( 'RMA_Elementor_Menu_Widget', false ) ) {
			qrms_assert_true( false, 'RMA_Elementor_Menu_Widget tanımlı değil' );
			return;
		}

		$widget = new RMA_Elementor_Menu_Widget();
		$render = new ReflectionMethod( $widget, 'render' );
		$render->setAccessible( true );

		ob_start();
		$render->invoke( $widget );
		$html = ob_get_clean();

		qrms_assert_contains( '[restaurant_menu', $html, 'render çıktısı shortcode köprüsünü kullanır' );
	}
);
