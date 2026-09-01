<?php
/**
 * Çalışma Saatleri renk ve önizleme testleri.
 *
 * Yükleyen: tests/test-suite.php — doğrudan çalıştırmayın.
 *
 * @package QR_Menu_Suite
 */

echo "\nQR Çalışma Saatleri (renk + önizleme)\n";

qrms_test(
	'hiç renk seçilmemişken çıktı eskisiyle BİREBİR aynıdır',
	function () {
		// Bu modülün görünümü bugüne kadar stylesheet'teki sabit renklerden
		// geliyordu. Renk ayarı eklemek kimsenin sitesini değiştirmemeli:
		// seçilmemiş renk CSS değişkeni olarak hiç basılmaz, geri düşüş
		// devrede kalır.
		qrms_assert_same( '', qrms_cs_color_declarations(), 'bildirim yok' );
		qrms_assert_same( '', qrms_cs_inline_style_attr(), 'satır içi stil yok' );

		$html = qrms_cs_shortcode( array() );

		qrms_assert_contains( '<ul class="qrms-cs-list">', $html, 'kapsayıcı çıplak' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/css/frontend.css' );

		qrms_assert_contains( 'var(--qrms-cs-today, #c9a84c)', $css, 'vurgu rengi geri düşüş' );
		qrms_assert_contains( 'var(--qrms-cs-divider, rgba(255, 255, 255, 0.14))', $css, 'ayraç rengi geri düşüş' );
	}
);

qrms_test(
	'geçersiz renk ayarı yok sayılır, geçerli olan değişkene iner',
	function () {
		update_option(
			QRMS_CS_COLORS_OPTION,
			qrms_cs_sanitize_colors(
				array(
					'today'   => '#ff0000',
					'divider' => 'kırmızı',   // geçersiz -> devral
					'hours'   => '#0f0',      // kısa hex geçerli
					'uydurma' => '#123456',   // bilinmeyen anahtar
				)
			)
		);

		$colors = qrms_cs_get_colors();

		qrms_assert_same( '#ff0000', $colors['today'], 'geçerli hex korunur' );
		qrms_assert_same( '', $colors['divider'], 'geçersiz değer devralmaya düşer' );
		qrms_assert_same( '#0f0', $colors['hours'], 'kısa hex kabul' );
		qrms_assert_false( isset( $colors['uydurma'] ), 'bilinmeyen anahtar düşer' );

		$decl = qrms_cs_color_declarations();

		qrms_assert_contains( '--qrms-cs-today: #ff0000', $decl, 'seçilen renk basılır' );
		qrms_assert_false( strpos( $decl, '--qrms-cs-divider' ), 'seçilmeyen renk basılmaz' );

		qrms_assert_contains( '--qrms-cs-today: #ff0000', qrms_cs_shortcode( array() ), 'kısa kod değişkeni taşır' );
	}
);

qrms_test(
	'renkler saatlerden AYRI option\'da durur, biri diğerini bozmaz',
	function () {
		// qrms_cs_sanitize() diziyi gün anahtarlarına indirger; renkler aynı
		// option'da olsaydı ilk kayıtta sessizce silinirdi.
		update_option( QRMS_CS_OPTION, qrms_cs_sanitize( array( 'monday' => array( 'open' => '11:00', 'close' => '23:30' ) ) ) );
		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'today' => '#123456' ) ) );

		$hours  = qrms_cs_get();
		$colors = qrms_cs_get_colors();

		qrms_assert_same( '11:00', $hours['monday']['open'], 'saat korundu' );
		qrms_assert_same( 7, count( $hours ), 'tam hafta' );
		qrms_assert_same( '#123456', $colors['today'], 'renk korundu' );
		qrms_assert_false( isset( $hours['today'] ), 'renk anahtarı saatlere sızmaz' );
	}
);

qrms_test(
	'form tek Kaydet ile hem saatleri hem renkleri yazar',
	function () {
		$_POST = array(
			'qrms_cs_kaydet' => '1',
			'qrms_cs'        => array( 'friday' => array( 'open' => '10:00', 'close' => '02:00' ) ),
			'qrms_cs_renk'   => array( 'today' => '#abcdef', 'day' => 'bozuk' ),
		);

		ob_start();
		qrms_cs_admin_sayfasi();
		$html = ob_get_clean();

		qrms_assert_same( '10:00', qrms_cs_get()['friday']['open'], 'saat kaydedildi' );
		qrms_assert_same( '#abcdef', qrms_cs_get_colors()['today'], 'renk kaydedildi' );
		qrms_assert_same( '', qrms_cs_get_colors()['day'], 'bozuk renk devralmaya düştü' );
		qrms_assert_contains( 'Çalışma saatleri kaydedildi.', $html, 'kayıt bildirimi' );
	}
);

qrms_test(
	'yönetim ekranı renk alanlarını ve kısa kodun GERÇEK çıktısını basar',
	function () {
		ob_start();
		qrms_cs_admin_sayfasi();
		$html = ob_get_clean();

		foreach ( array_keys( qrms_cs_color_fields() ) as $key ) {
			qrms_assert_contains( 'name="qrms_cs_renk[' . $key . ']"', $html, $key . ' alanı' );
		}

		qrms_assert_contains( 'id="qrms-cs-preview"', $html, 'önizleme kutusu' );
		// Önizleme ayrı bir şablon değil, kısa kodun kendisi: aynı sınıflar.
		qrms_assert_contains( 'qrms-cs-list', $html, 'kısa kod listesi' );
		qrms_assert_contains( 'data-day="monday"', $html, 'satırlar gün anahtarı taşır' );
	}
);

qrms_test(
	'yeni görünüm alanları (zemin, kenar, yazı rengi) değişkene iner',
	function () {
		update_option(
			QRMS_CS_COLORS_OPTION,
			qrms_cs_sanitize_colors(
				array(
					'bg'     => '#ffffff',
					'border' => '#dddddd',
					'text'   => '#222222',
				)
			)
		);

		$decl = qrms_cs_color_declarations();

		qrms_assert_contains( '--qrms-cs-bg: #ffffff', $decl, 'arka plan' );
		qrms_assert_contains( '--qrms-cs-border: #dddddd', $decl, 'kenar rengi' );
		qrms_assert_contains( '--qrms-cs-text: #222222', $decl, 'yazı rengi' );

		// Kutu ölçüleri renklerle BİRLİKTE basılır: "1px solid transparent"
		// bile satırları kaydırırdı (bkz. frontend.css başlığı).
		qrms_assert_contains( '--qrms-cs-border-width: 1px', $decl, 'çerçeve kalınlığı' );
		qrms_assert_contains( '--qrms-cs-pad: 12px 16px', $decl, 'iç boşluk' );

		// Yalnızca zemin seçiliyken çerçeve kalınlığı basılmaz.
		$sadece_zemin = qrms_cs_color_declarations( qrms_cs_sanitize_colors( array( 'bg' => '#000000' ) ) );

		qrms_assert_contains( '--qrms-cs-pad', $sadece_zemin, 'zemin için boşluk' );
		qrms_assert_false( strpos( $sadece_zemin, '--qrms-cs-border-width' ), 'çerçeve istenmedi' );

		// Hiçbiri seçilmemişken tek bir ölçü bile basılmaz.
		qrms_assert_same( '', qrms_cs_color_declarations( qrms_cs_sanitize_colors( array() ) ), 'çıplak liste' );
	}
);

qrms_test(
	'yazı tipi beyaz listeyle doğrulanır, uydurma değer devralmaya düşer',
	function () {
		// Değer doğrudan CSS'e iniyor: serbest metin kabul edilemez.
		$temiz = qrms_cs_sanitize_colors( array( 'font' => 'Poppins' ) );

		qrms_assert_same( 'Poppins', $temiz['font'], 'listedeki font' );
		qrms_assert_same(
			'',
			qrms_cs_sanitize_colors( array( 'font' => 'Comic Sans; color:red' ) )['font'],
			'liste dışı değer düşer'
		);

		update_option( QRMS_CS_COLORS_OPTION, $temiz );

		qrms_assert_same( 'Poppins', qrms_cs_get_font(), 'kayıtlı font' );
		qrms_assert_contains(
			"--qrms-cs-font: 'Poppins', system-ui, sans-serif",
			qrms_cs_color_declarations(),
			'font değişkeni sistem geri düşüşüyle basılır'
		);

		// Jenerik aile tırnaklanmaz; sistem fontu için dış istek yapılmaz.
		qrms_assert_same( 'serif', qrms_cs_font_family( 'serif' ), 'jenerik aile' );
		qrms_assert_same( '', qrms_cs_google_font_url( 'Georgia' ), 'sistem fontu' );
		qrms_assert_same( '', qrms_cs_google_font_url( '' ), 'seçim yok' );
		qrms_assert_contains( 'family=Poppins', qrms_cs_google_font_url( 'Poppins' ), 'Google adresi' );
	}
);

qrms_test(
	'font listesi Restoran Menü\'nün Görünüm sayfasıyla BİREBİR aynıdır',
	function () {
		// İki ekranda farklı listeler olsaydı restoran sahibi hangisini
		// seçtiğini karıştırırdı. Liste orada private bir metotta durduğu ve
		// modüller bağımsız lisanslandığı için kopya bilinçli — bu test
		// kopyanın ayrışmasını yakalar.
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/restoran-menu/includes/trait-admin-pages.php' );

		preg_match( '/private function get_font_options\(\) \{\s*return \[(.*?)\];/s', $kaynak, $m );
		preg_match_all( "/'([^']+)'/", $m[1], $isimler );

		qrms_assert_same( $isimler[1], qrms_cs_font_options(), 'liste aynı' );
	}
);

qrms_test(
	'yönetim ekranı font seçicisini basar, önizleme anında yansır',
	function () {
		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'font' => 'Lato' ) ) );

		ob_start();
		qrms_cs_admin_sayfasi();
		$html = ob_get_clean();

		qrms_assert_contains( 'name="qrms_cs_renk[font]"', $html, 'font alanı' );
		qrms_assert_contains( 'data-css-var="--qrms-cs-font"', $html, 'değişken adı' );
		qrms_assert_contains( 'value="Lato"', $html, 'seçenekler listelenir' );
		qrms_assert_contains( 'data-google="https://fonts.googleapis.com/css2?family=Lato', $html, 'Google adresi' );

		// Önizlemeyi JS besler: değişken önizleme listesine yazılır, Google
		// stylesheet'i seçim değişince eklenir.
		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/js/admin.js' );

		qrms_assert_contains( "getElementById('qrms-cs-renk-font')", $js, 'JS seçiciyi bulur' );
		qrms_assert_contains( 'function loadFont(url)', $js, 'JS fontu yükler' );
		qrms_assert_contains( 'function syncBox()', $js, 'JS kutu kuralını PHP ile eşler' );
	}
);

qrms_test(
	'seçilen font ön yüzde yalnızca gerektiğinde yüklenir',
	function () {
		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'font' => 'Georgia' ) ) );
		qrms_cs_shortcode( array() );
		qrms_assert_same( null, qrms_ae_style( 'qrms-cs-font' ), 'sistem fontu için dış istek yok' );

		update_option( QRMS_CS_COLORS_OPTION, qrms_cs_sanitize_colors( array( 'font' => 'Inter' ) ) );
		qrms_cs_shortcode( array() );

		$stil = qrms_ae_style( 'qrms-cs-font' );

		qrms_assert_true( null !== $stil, 'adlandırılmış font yüklenir' );
		qrms_assert_contains( 'family=Inter', $stil['src'], 'doğru aile' );
	}
);

qrms_test(
	'önizlemenin saat metni PHP ile aynı üç dala sahiptir',
	function () {
		// Metin iki yerde üretiliyor: sayfa açılışında PHP, değişiklikte JS.
		// Dallanma ayrışırsa önizleme yalan söyler; her iki taraf da burada
		// doğrulanıyor.
		qrms_assert_same( 'Kapalı', qrms_cs_format_day( array( 'closed' => true ) ), 'kapalı' );
		qrms_assert_same(
			'24 saat açık',
			qrms_cs_format_day( array( 'closed' => false, 'open' => '00:00', 'close' => '00:00' ) ),
			'eşit saat = 24 saat'
		);
		qrms_assert_same(
			'09:00 – 22:00',
			qrms_cs_format_day( array( 'closed' => false, 'open' => '09:00', 'close' => '22:00' ) ),
			'aralık'
		);

		$js = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/js/admin.js' );

		qrms_assert_contains( 'if (closed) {', $js, 'JS: kapalı dalı' );
		qrms_assert_contains( 'if (open === close) {', $js, 'JS: 24 saat dalı' );
		qrms_assert_contains( 'L.kapali', $js, 'JS metni PHP\'den alır' );
		qrms_assert_contains( 'L.yirmiDort', $js, 'JS metni PHP\'den alır' );
		qrms_assert_contains( 'L.aralik', $js, 'JS metni PHP\'den alır' );
	}
);

qrms_test(
	'ön yüzde kısa kod Elementor boxed kapsayıcıyı 100vw ile ezer',
	function () {
		$html = qrms_cs_shortcode( array() );

		qrms_assert_contains( 'qrms-cs--full', $html, 'full width sınıfı' );
		qrms_assert_contains( 'qrms-cs-inner', $html, 'içerik ortalanır' );
		qrms_assert_contains( 'qrms-cs-card', $html, 'kart durur' );
		qrms_assert_contains( 'qrms-cs-today-tag', $html, 'Bugün etiketi' );

		$css = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/assets/css/frontend.css' );

		qrms_assert_contains( 'width: 100vw', $css, 'viewport genişliği' );
		qrms_assert_contains( 'calc(50% - 50vw)', $css, 'kırılım hesabı' );
		qrms_assert_contains( 'overflow-x: clip', $css, 'yatay kaydırma yok' );
		qrms_assert_contains( '.elementor-widget:has(.qrms-cs--full)', $css, 'Elementor widget padding ezmesi' );
		qrms_assert_contains( '.e-con:has(.qrms-cs--full)', $css, 'Elementor container overflow ezmesi' );
		qrms_assert_contains( 'flex-wrap: nowrap', $css, 'gün ve saat tek satır' );
		qrms_assert_contains( 'max-width: 1100px', $css, 'içerik ortada kaplanır' );
	}
);

qrms_test(
	'fullwidth=0 dar sütunda kırılımı kapatır, kart aynı kalır',
	function () {
		$html = qrms_cs_shortcode( array( 'fullwidth' => '0' ) );

		qrms_assert_false( false !== strpos( $html, 'qrms-cs--full' ), 'sınıf yok' );
		qrms_assert_contains( 'class="qrms-cs"', $html, 'sarmalayıcı durur' );
		qrms_assert_contains( 'qrms-cs-card', $html, 'kart durur' );
		qrms_assert_contains( 'qrms-cs-inner', $html, 'iç sarmalayıcı durur' );
	}
);

qrms_test(
	'yönetim önizlemesi full-bleed kırılımı almaz',
	function () {
		$GLOBALS['qrms_test']['is_admin'] = true;

		$html = qrms_cs_shortcode( array() );

		qrms_assert_false( false !== strpos( $html, 'qrms-cs--full' ), 'admin kırılmaz' );
		qrms_assert_contains( 'qrms-cs-card', $html, 'önizleme kartı' );
	}
);


/* ---------------------------------------------------------------------------
 * 12. Toplu Fiyat Kampanyası
 *
 * Fiyat verisiyle DOĞRUDAN oynayan bir özellik olduğu için hesap katmanı
 * WordPress'ten tamamen bağımsız tutuldu: aşağıdaki fonksiyonların hepsi saf.
 * Yönetimdeki canlı önizleme, ön yüz render'ı ve bu testler aynı fonksiyonları
 * çağırır — dolayısıyla önizlemede görülen fiyat ile menüde çıkan fiyat tanım
 * gereği aynıdır.
 * ------------------------------------------------------------------------ */



/* P2 çeviri testleri (birleşme sonrası taşındı) */

echo "\nQR Çeviri (P0 köprü / çalışma saatleri)\n";

require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/ui-stringler.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/fiyat.php';
require_once QRMS_PLUGIN_DIR . 'modules/yorum-feedback/includes/settings.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/veri-kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-ceviri/includes/kaynaklar.php';
require_once QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/includes/hours.php';

qrms_test(
	'saat kaynak metinleri katalogda ve anahtarları kararlı',
	function () {
		$metinler = rma_ceviri_modul_stringleri( 'hours' );
		$beklenen = array(
			'Pazartesi',
			'Salı',
			'Çarşamba',
			'Perşembe',
			'Cuma',
			'Cumartesi',
			'Pazar',
			'Kapalı',
			'24 saat açık',
			'%1$s – %2$s',
			'Çalışma Saatleri',
			'Şu an açığız',
			'Şu an kapalıyız',
			'Sipariş ve rezervasyon için bizi arayın',
			'Bugün',
		);

		foreach ( $beklenen as $metin ) {
			qrms_assert_same(
				$metin,
				$metinler[ rma_ceviri_ui_anahtari( $metin ) ],
				$metin
			);
		}

		qrms_assert_same(
			'Pazartesi',
			rma_ceviri_guncel_orijinal( 0, 'hours', rma_ceviri_ui_anahtari( 'Pazartesi' ) ),
			'guncel orijinal'
		);
	}
);

qrms_test(
	'saat ön yüzü rma_ceviri_modul köprüsünü kullanır; çeviri yoksa Türkçe',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/includes/hours.php' );

		qrms_assert_contains( "rma_ceviri_modul( 'hours'", $kaynak, 'köprü çağrısı' );
		qrms_assert_contains( "__( 'Pazartesi', 'qrms' )", $kaynak, 'textdomain gün adı' );
		qrms_assert_contains( "__( 'Kapalı', 'qrms' )", $kaynak, 'textdomain Kapalı' );
		qrms_assert_contains( "__( '24 saat açık', 'qrms' )", $kaynak, 'textdomain 24 saat' );
		qrms_assert_contains( "__( '%1\$s – %2\$s', 'qrms' )", $kaynak, 'textdomain biçim dizesi' );

		$sc = file_get_contents( QRMS_PLUGIN_DIR . 'modules/qr-calisma-saatleri/includes/shortcode.php' );
		qrms_assert_contains( "qrms_cs_cevir( __( 'Çalışma Saatleri', 'qrms' ) )", $sc, 'başlık hours' );
		qrms_assert_contains( "qrms_cs_cevir( \$is_open ? __( 'Şu an açığız', 'qrms' )", $sc, 'durum hours' );
		qrms_assert_contains( "qrms_cs_cevir( __( 'Sipariş ve rezervasyon için bizi arayın', 'qrms' ) )", $sc, 'not hours' );
		qrms_assert_contains( "qrms_cs_cevir( __( 'Bugün', 'qrms' ) )", $sc, 'bugün hours' );

		$etiket = qrms_cs_day_labels();
		qrms_assert_same( 'Pazartesi', $etiket['monday'], 'Pazartesi' );
		qrms_assert_same( 'Pazar', $etiket['sunday'], 'Pazar' );
		qrms_assert_same( 'Kapalı', qrms_cs_format_day( array( 'closed' => true ) ), 'Kapalı' );
		qrms_assert_same(
			'24 saat açık',
			qrms_cs_format_day( array( 'closed' => false, 'open' => '00:00', 'close' => '00:00' ) ),
			'24 saat'
		);
		qrms_assert_same(
			'09:00 – 22:00',
			qrms_cs_format_day( array( 'closed' => false, 'open' => '09:00', 'close' => '22:00' ) ),
			'aralık biçimi'
		);
	}
);

qrms_test(
	'footer saat sütunu aynı gün adı ve biçim fonksiyonlarını kullanır',
	function () {
		$kaynak = file_get_contents( QRMS_PLUGIN_DIR . 'modules/header-footer-builder/includes/trait-frontend.php' );

		qrms_assert_contains( 'function render_hours_column', $kaynak, 'sütun fonksiyonu' );
		qrms_assert_contains( 'qrms_cs_day_labels()', $kaynak, 'gün adları ortak' );
		qrms_assert_contains( 'qrms_cs_format_day(', $kaynak, 'saat biçimi ortak' );
		qrms_assert_false(
			(bool) preg_match( "/__\(\s*'Pazartesi'/", $kaynak ),
			'footer kendi gün adını basmaz'
		);
		qrms_assert_false(
			(bool) preg_match( "/__\(\s*'Kapalı'/", $kaynak ),
			'footer kendi Kapalı metnini basmaz'
		);
	}
);

echo "\nQR Çeviri (P0 köprü / kilit ekranı)\n";
