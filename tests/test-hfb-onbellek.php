<?php
/**
 * HFB açılır liste, önizleme yükü ve önbellek testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\n\033[1mHFB — açılır liste, önizleme yükü, önbellek\033[0m\n";

qrms_test(
	'"Yeni Blok Ekle" listesi sayfa akışını itmez, düğmenin üstüne binmez',
	function () {
		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/css/admin.css' );
		$js  = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/admin.js' );

		preg_match( '/\.hfb-wrap \.hfb-block-add \{(.*?)\}/s', $css, $sarmalayici );
		preg_match( '/\.hfb-wrap \.hfb-block-add__menu \{(.*?)\}/s', $css, $liste );

		qrms_assert_contains( 'position: relative', $sarmalayici[1], 'sarmalayıcı konumlandırma bağlamı kurar' );

		// Ofsetsiz bir mutlak kutu STATİK konumunda kalır — yani tetikleyen
		// düğmenin yerinde — ve onun üzerine biner. Ofset zorunludur.
		qrms_assert_contains( 'position: absolute', $liste[1], 'liste akıştan çıkar' );
		qrms_assert_contains( 'top: calc(100% + 6px)', $liste[1], 'düğmenin altına çapalanır' );
		qrms_assert_contains( 'left: 0', $liste[1], 'yatay çapa' );

		preg_match( '/z-index: (\d+)/', $liste[1], $z );
		qrms_assert_true( (int) $z[1] >= 100, 'altındaki gezinme şeridinin üstünde kalır' );

		// `[hidden]` tek özgüllük birimidir; `.hfb-wrap .hfb-block-add__menu`
		// üzerindeki display kuralı onu ezer ve liste KAPALIYKEN de görünürdü.
		qrms_assert_contains(
			'.hfb-wrap .hfb-block-add__menu[hidden] {',
			$css,
			'gizleme kuralı aynı özgüllükte tekrarlanır'
		);

		// Dışarı tıklama ve Escape kapatır; odak geri verilirken sayfa kaymaz.
		qrms_assert_contains( 'setBlockMenuOpen(false)', $js, 'kapatma yardımcısı' );
		qrms_assert_contains( "e.key === 'Escape'", $js, 'Escape kapatır' );
		qrms_assert_contains( 'preventScroll: true', $js, 'odak scroll konumunu bozmaz' );
	}
);

qrms_test(
	'önizleme yükü iç içe kurulur; blok alanları PHP tarafında bozulmadan çözülür',
	function () {
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/assets/js/admin.js' );

		// Tek delege dinleyici formun tamamını kapsar: sınıf listesine
		// bakılmaz, sonradan eklenen blok alanları da kendiliğinden girer.
		qrms_assert_contains( "on('input change', '#hfb-settings-form'", $js, 'form kökünde delegasyon' );
		qrms_assert_contains( "is('input, select, textarea')", $js, 'her alan tipi kapsanır' );
		qrms_assert_true(
			false === strpos( $js, "'#hfb-settings-form .hfb-preview-trigger'" ),
			'sınıfa bağlı eski dinleyici kaldırıldı'
		);
		qrms_assert_contains( 'var DEBOUNCE_MS = 300;', $js, 'debounce korunur' );

		/*
		 * Asıl kök neden: düz anahtarla gönderilen blok alanları
		 * `data[hfb_hamburger_blocks[blk_1][enabled]]` hâline geliyor, PHP
		 * ise İLK kapanan paranteze göre ayrıştırıp anahtarı
		 * `hfb_hamburger_blocks[blk_1` diye bozuyordu. Aşağıdaki iki
		 * doğrulama, bozuk ve düzeltilmiş yükün sunucudaki karşılığını
		 * yan yana gösterir.
		 */
		parse_str( 'data%5Bhfb_hamburger_blocks%5Bblk_1%5D%5Benabled%5D%5D=1', $bozuk );
		qrms_assert_true(
			! isset( $bozuk['data']['hfb_hamburger_blocks'] ),
			'düz anahtar sunucuda bloklara hiç ulaşmaz'
		);

		parse_str( 'data%5Bhfb_hamburger_blocks%5D%5Bblk_1%5D%5Benabled%5D=1', $duzgun );
		qrms_assert_same(
			'1',
			$duzgun['data']['hfb_hamburger_blocks']['blk_1']['enabled'],
			'iç içe yük doğru çözülür'
		);

		qrms_assert_contains( 'function nameToPath(', $js, 'alan adı yola çevrilir' );
		qrms_assert_contains( 'function assignPath(', $js, 'yol boyunca yazılır' );
	}
);

qrms_test(
	'iç içe önizleme yükü blok değişikliklerini kaydetmeden yansıtır',
	function () {
		$hfb = qrms_hfb();

		// Tarayıcının artık gönderdiği şekil: hfb_hamburger_blocks bir
		// dizidir, düz "…[blk_1][enabled]" anahtarı değil.
		$_POST = array(
			'nonce' => 'test',
			'data'  => array(
				'hfb_hamburger_block_order' => 'blk_9,blk_8',
				'hfb_hamburger_blocks'      => array(
					'blk_9' => array(
						'type'    => 'text',
						'enabled' => '1',
						'align'   => 'left',
						'content' => 'Kaydetmeden görünen not',
					),
					'blk_8' => array(
						'type'    => 'logo',
						'enabled' => '1',
						'align'   => 'center',
					),
				),
			),
		);

		$hfb->ajax_preview();
		$yanit = $GLOBALS['qrms_test']['json'];

		qrms_assert_true( is_array( $yanit ) && ! empty( $yanit['success'] ), 'başarılı yanıt' );
		qrms_assert_contains( 'Kaydetmeden görünen not', $yanit['data']['header'], 'yeni metin bloğu önizlemede' );

		// Kayıt DEĞİŞMEZ: önizleme salt okunurdur.
		$kayitli = $hfb->get_hamburger_options();
		foreach ( $kayitli['blocks'] as $block ) {
			qrms_assert_true(
				! isset( $block['content'] ) || false === strpos( (string) $block['content'], 'Kaydetmeden görünen not' ),
				'önizleme kaydetmez'
			);
		}
	}
);

qrms_test(
	'qmo_tum_onbellek_temizle nesne önbelleğini ve kurulu eklentileri temizler',
	function () {
		// Kurulu eklenti taklitleri: yalnızca function_exists() dallarının
		// çalıştığını göstermek için. Gerçekte kurulu değilse dal atlanır.
		if ( ! function_exists( 'rocket_clean_domain' ) ) {
			function rocket_clean_domain() {
				$GLOBALS['qrms_test']['eklenti_temizlik'][] = 'rocket';
			}
		}
		if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
			function wp_cache_clear_cache() {
				$GLOBALS['qrms_test']['eklenti_temizlik'][] = 'super_cache';
			}
		}
		if ( ! function_exists( 'autoptimize_flush_pagecache' ) ) {
			function autoptimize_flush_pagecache() {
				$GLOBALS['qrms_test']['eklenti_temizlik'][] = 'autoptimize';
			}
		}

		$GLOBALS['qrms_test']['eklenti_temizlik'] = array();

		$temizlenen = qmo_tum_onbellek_temizle();

		qrms_assert_true( in_array( '*', $GLOBALS['qrms_test']['cache_flush'], true ), 'nesne önbelleği boşaltıldı' );
		qrms_assert_true( in_array( 'wp_rocket', $temizlenen, true ), 'WP Rocket temizlendi' );
		qrms_assert_true( in_array( 'wp_super_cache', $temizlenen, true ), 'WP Super Cache temizlendi' );
		qrms_assert_true( in_array( 'autoptimize', $temizlenen, true ), 'Autoptimize temizlendi' );
		qrms_assert_same(
			array( 'rocket', 'super_cache', 'autoptimize' ),
			$GLOBALS['qrms_test']['eklenti_temizlik'],
			'her eklenti bir kez çağrıldı'
		);

		// Kurulu OLMAYAN eklenti sessizce atlanır — ölümcül hata yok.
		qrms_assert_true( ! in_array( 'w3tc', $temizlenen, true ), 'W3TC kurulu değil, atlandı' );
		qrms_assert_true(
			in_array( 'qmo_onbellek_temizlendi', $GLOBALS['qrms_test']['fired_actions'], true ),
			'genişletme kancası tetiklenir'
		);

		// Arka uç grup bazlı temizliği destekliyorsa daha dar kapsam seçilir.
		$GLOBALS['qrms_test']['cache_flush']    = array();
		$GLOBALS['qrms_test']['cache_supports'] = array( 'flush_group' => true );

		$dar = qmo_tum_onbellek_temizle( 'qmo' );

		qrms_assert_true( in_array( 'qmo', $GLOBALS['qrms_test']['cache_flush'], true ), 'yalnızca grup boşaltıldı' );
		qrms_assert_true( in_array( 'wp_cache_flush_group:qmo', $dar, true ), 'grup temizliği raporlanır' );
		qrms_assert_true( ! in_array( '*', $GLOBALS['qrms_test']['cache_flush'], true ), 'genel flush yapılmaz' );

		// Mevcut masa yardımcısı DEĞİŞMEDEN durur.
		qrms_assert_true( function_exists( 'qmo_masa_cache_temizle' ), 'masa yardımcısı yerinde' );
	}
);

qrms_test(
	'HFB kaydı başarılı olduğunda önbellek kendiliğinden temizlenir',
	function () {
		$hfb = qrms_hfb();

		$GLOBALS['qrms_test']['cache_flush']   = array();
		$GLOBALS['qrms_test']['fired_actions'] = array();

		$hfb->save_settings( array( 'hfb_header_brand_line1' => 'Yeni Marka' ) );

		qrms_assert_same( 'Yeni Marka', get_option( 'hfb_header_options' )['brand_line1'], 'ayar kaydedildi' );
		qrms_assert_true(
			! empty( $GLOBALS['qrms_test']['cache_flush'] ),
			'kayıttan hemen sonra önbellek temizlenir'
		);
		qrms_assert_true(
			in_array( 'qmo_onbellek_temizlendi', $GLOBALS['qrms_test']['fired_actions'], true ),
			'ortak temizleyici çağrıldı'
		);

		// Nonce/yetki kontrolü kayıt akışında yerinde durur.
		$admin = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-admin.php' );
		qrms_assert_contains( "current_user_can( QRMS_Admin::CAPABILITY )", $admin, 'yetki kontrolü' );
		qrms_assert_contains( "check_admin_referer( 'hfb_save_settings', 'hfb_nonce' )", $admin, 'nonce kontrolü' );
	}
);

