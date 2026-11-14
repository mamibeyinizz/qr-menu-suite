<?php
/**
 * QRMS_Tasima saf yardımcılarının testi (WordPress gerekmez).
 *
 *   php tests/tasima-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../includes/class-tasima.php';

$hata = 0;
$say  = 0;
function esit( $beklenen, $gelen, $ad ) {
	global $hata, $say;
	$say++;
	if ( $beklenen !== $gelen ) {
		$hata++;
		echo "BAŞARISIZ: $ad\n  beklenen: " . var_export( $beklenen, true ) . "\n  gelen:    " . var_export( $gelen, true ) . "\n";
	}
}

// 1) Ayar izin listesi.
foreach ( array( 'hfb_header_options', 'rma_color_settings', 'rma_nav_design_settings', 'rma_typo_settings', 'splash_screen_options', 'qrms_marka_kimligi', 'qrms_login_ayarlar', 'qrmenu_active_langs', 'qmo_chatbot_banned_words', 'gemini_system_prompt', 'blogname' ) as $ad ) {
	esit( true, QRMS_Tasima::secenek_izinli( $ad ), "izinli: $ad" );
}
foreach ( array( 'qrms_api_key', 'qrms_server_url', 'qrms_active_modules', 'qrms_revoke_since', 'qrms_acil_durum', 'qrms_acil_durum_pin_hash', 'gemini_api_key', 'qmo_firebase_sa', 'gemini_menu_json_data', 'qrm_db_version', 'qr_masa_hmac_key', 'rma_cache_version', 'siteurl', 'home', 'admin_email', 'active_plugins', 'users_can_register', 'default_role', 'qmo_branch_id' ) as $ad ) {
	esit( false, QRMS_Tasima::secenek_izinli( $ad ), "yasak: $ad" );
}

// 2) Meta anahtarı.
foreach ( array( '_thumbnail_id', 'rma_price', '_elementor_data', '_menu_item_type', '_wp_page_template', '_qmo_banner_gorsel_id' ) as $k ) {
	esit( true, QRMS_Tasima::meta_izinli( $k ), "meta izinli: $k" );
}
foreach ( array( '_edit_lock', '_wp_attached_file', '_wp_attachment_metadata', '_qrmgm_webp_path', '_qrmgm_webp_url', '_elementor_css', 'rma_views', '_qrms_kaynak_id', 'bad key', "x\ny", '' ) as $k ) {
	esit( false, QRMS_Tasima::meta_izinli( $k ), "meta yasak: $k" );
}

// 3) Özgün görsel adresi.
esit( 'https://a.test/wp-content/uploads/2026/08/foto-scaled.webp', QRMS_Tasima::ozgun_url( 'https://a.test/wp-content/uploads/2026/08/foto-scaled-768x330.webp' ), 'boyut eki atılır' );
esit( 'https://a.test/wp-content/uploads/2026/08/foto.webp', QRMS_Tasima::ozgun_url( 'https://a.test/wp-content/uploads/2026/08/foto.webp' ), 'boyut eki yoksa aynı' );
esit( 'https://a.test/wp-content/uploads/2026/08/2026x.webp', QRMS_Tasima::ozgun_url( 'https://a.test/wp-content/uploads/2026/08/2026x.webp' ), 'yıl gibi sayılar dokunulmaz' );

// 4) Uploads adreslerini bulma (düz ve JSON kaçışlı).
$up = 'https://a.test/wp-content/uploads';
$bul = QRMS_Tasima::medya_url_cikar( '<img src="https://a.test/wp-content/uploads/2026/08/a-300x200.webp" srcset="https://a.test/wp-content/uploads/2026/08/a-768x330.webp 768w, https://a.test/wp-content/uploads/2026/08/a.webp 1x">', $up );
esit( 3, count( $bul ), 'düz metinde 3 adres' );
$bul = QRMS_Tasima::medya_url_cikar( '{"url":"https:\/\/a.test\/wp-content\/uploads\/2026\/08\/b.png","id":12}', $up );
esit( array( 'https://a.test/wp-content/uploads/2026/08/b.png' ), $bul, 'JSON kaçışlı adres' );
esit( array(), QRMS_Tasima::medya_url_cikar( 'https://baska.test/wp-content/uploads/x.png', $up ), 'başka alan adı bulunmaz' );

// 5) Adres çevirme.
$medya = array(
	'https://a.test/wp-content/uploads/2026/08/foto.webp' => array(
		'url'      => 'https://b.test/wp-content/uploads/2026/11/foto.webp',
		'boyutlar' => array( '300x200' => 'https://b.test/wp-content/uploads/2026/11/foto-300x200.webp' ),
	),
);
esit(
	'<a href="https://b.test/menu"><img src="https://b.test/wp-content/uploads/2026/11/foto-300x200.webp"></a>',
	QRMS_Tasima::url_cevir( '<a href="https://a.test/menu"><img src="https://a.test/wp-content/uploads/2026/08/foto-300x200.webp"></a>', 'https://a.test', 'https://b.test', $medya ),
	'düz: boyutlu varyant kendi boyutuna çevrilir'
);
esit(
	'https://b.test/wp-content/uploads/2026/11/foto.webp',
	QRMS_Tasima::url_cevir( 'https://a.test/wp-content/uploads/2026/08/foto-999x999.webp', 'https://a.test', 'https://b.test', $medya ),
	'olmayan boyut özgüne düşer'
);
esit(
	'{"image":{"url":"https:\/\/b.test\/wp-content\/uploads\/2026\/11\/foto.webp","id":7},"link":"https:\/\/b.test\/x"}',
	QRMS_Tasima::url_cevir( '{"image":{"url":"https:\/\/a.test\/wp-content\/uploads\/2026\/08\/foto.webp","id":7},"link":"https:\/\/a.test\/x"}', 'https://a.test', 'https://b.test', $medya ),
	'JSON kaçışlı metin kaçışlı kalır'
);
esit( 'merhaba', QRMS_Tasima::url_cevir( 'merhaba', 'https://a.test', 'https://b.test', $medya ), 'ilgisiz metin aynı' );
esit( '', QRMS_Tasima::url_cevir( '', 'https://a.test', 'https://b.test', $medya ), 'boş metin' );

// 6) Kimlik çevirme.
$harita = array( 'ek' => array( 245 => 901, 244 => 902 ), 'menu' => array( 38 => 54, 40 => 53 ), 'yazi' => array( 138 => 700, 139 => 701 ) );
$girdi  = array(
	'logo'          => '245',
	'bg_image'      => 244,
	'bg_image_wide' => 0,
	'divider_text'  => 'Bizi takip edin',
	'logo_height'   => 96,
	'alt'           => array( 'menu_id' => 38, 'logo' => 245, 'olmayan' => 999 ),
	'ikinci'        => array( 'menu_id' => 40, 'logo' => 777 ),
	'manual_ids'    => array( 138, 5555, 139 ),
	'liste'         => array( 245, 38 ),
);
$cikti = QRMS_Tasima::kimlik_cevir( $girdi, $harita );
esit( 901, $cikti['logo'], 'logo eşlendi' );
esit( 902, $cikti['bg_image'], 'bg_image eşlendi' );
esit( 0, $cikti['bg_image_wide'], 'sıfır sıfır kalır' );
esit( 96, $cikti['logo_height'], 'ilgisiz sayı dokunulmaz' );
esit( 'Bizi takip edin', $cikti['divider_text'], 'metin dokunulmaz' );
esit( 54, $cikti['alt']['menu_id'], 'iç içe menu_id eşlendi' );
esit( 901, $cikti['alt']['logo'], 'iç içe logo eşlendi' );
esit( 999, $cikti['alt']['olmayan'], 'bilinmeyen alan dokunulmaz' );
esit( 0, $cikti['ikinci']['logo'], 'eşleşmeyen görsel kimliği 0 olur (yanlış görsele bağlanmaz)' );
esit( array( 700, 701 ), $cikti['manual_ids'], 'manual_ids: eşleşmeyen düşer, kalanlar çevrilir' );
esit( array( 245, 38 ), $cikti['liste'], 'anahtarsız liste dokunulmaz' );

// 7) Gizli alan ayıklama.
$a = QRMS_Tasima::gizli_ayikla(
	array(
		'google_review_url' => 'https://x',
		'api_key'           => 'GIZLI',
		'ic'                => array( 'client_secret' => 'GIZLI2', 'Token' => 'GIZLI3', 'renk' => '#fff' ),
		'wifi_password'     => 'misafir123',
		'social_media'      => array( 'pinterest' => '' ),
		0                   => 'sira',
	)
);
esit( false, isset( $a['api_key'] ), 'api_key ayıklandı' );
esit( false, isset( $a['ic']['client_secret'] ), 'iç client_secret ayıklandı' );
esit( false, isset( $a['ic']['Token'] ), 'iç Token ayıklandı' );
esit( '#fff', $a['ic']['renk'], 'renk kaldı' );
esit( 'misafir123', $a['wifi_password'], 'müşteriye gösterilen Wi-Fi şifresi kaldı' );
esit( '', $a['social_media']['pinterest'], 'pinterest kaldı' );
esit( 'sira', $a[0], 'sayısal anahtar kaldı' );

// 8) Dizgileri dolaşma.
esit( array( 'A', array( 'B', 5 ), 'k' => 'C' ), QRMS_Tasima::dizgileri_dolas( array( 'a', array( 'b', 5 ), 'k' => 'c' ), 'strtoupper' ), 'dizgiler dönüşür, sayılar kalır' );

// 9) Görsel adresi güvenliği.
$ku = 'https://a.test/wp-content/uploads';
foreach ( array( 'https://a.test/wp-content/uploads/2026/08/a.webp', 'http://a.test/wp-content/uploads/b-300x200.png', 'https://A.TEST/wp-content/uploads/x/y.JPG' ) as $u ) {
	esit( true, QRMS_Tasima::medya_url_guvenli( $u, $ku ), "güvenli: $u" );
}
foreach ( array(
	'https://a.test/wp-content/uploads/../../wp-config.php',
	'https://a.test/wp-content/uploads/2026/../../../wp-config.php.png',
	'https://a.test/wp-content/uploads/%2e%2e/%2e%2e/x.png',
	'https://a.test/wp-content/uploads/..%2f..%2fx.png',
	'https://a.test/wp-content/uploads/..\\x.png',
	'https://a.test/wp-content/uploads/a.php',
	'https://a.test/wp-content/uploads/a.svg',
	'https://a.test/wp-content/uploads/a.png?x=1',
	'https://a.test/wp-content/uploads/a.png#x',
	'https://a.test/wp-content/uploadsX/a.png',
	'https://a.test/wp-config.php',
	'https://evil.test/wp-content/uploads/a.png',
	'https://a.test:8080/wp-content/uploads/a.png',
	'https://u:p@a.test/wp-content/uploads/a.png',
	'ftp://a.test/wp-content/uploads/a.png',
	'http://169.254.169.254/latest/meta-data/',
	'',
	'not a url',
) as $u ) {
	esit( false, QRMS_Tasima::medya_url_guvenli( $u, $ku ), "güvensiz: $u" );
}

// 10) Çeviri kimlikleri.
$h = array( 'yazi' => array( 40 => 900, 313 => 901, 17 => 902 ), 'terim' => array( 1 => 50, 17 => 51 ) );
esit( 900, QRMS_Tasima::ceviri_kimlik_cevir( 'product', 40, $h ), 'ürün kimliği eşlenir' );
esit( 901, QRMS_Tasima::ceviri_kimlik_cevir( 'nav_menu', 313, $h ), 'menü öğesi kimliği eşlenir' );
esit( 902, QRMS_Tasima::ceviri_kimlik_cevir( 'elementor', 17, $h ), 'sayfa kimliği eşlenir' );
esit( 51, QRMS_Tasima::ceviri_kimlik_cevir( 'category', 17, $h ), 'kategori terim kimliği eşlenir' );
esit( 0, QRMS_Tasima::ceviri_kimlik_cevir( 'ui_string', 0, $h ), 'sabit metin 0 kalır' );
esit( null, QRMS_Tasima::ceviri_kimlik_cevir( 'product', 99999, $h ), 'eşlenmeyen ürün atlanır (yanlış ürüne yazılmaz)' );
esit( null, QRMS_Tasima::ceviri_kimlik_cevir( 'form_field', 3, $h ), 'başka modül tablosu taşınmaz' );
esit( null, QRMS_Tasima::ceviri_kimlik_cevir( 'category', 40, $h ), 'ürün kimliği terim tablosuna karışmaz' );
$iyi = array( 'id' => 1, 'tip' => 'product', 'alan' => 'title', 'dil' => 'en', 'orijinal' => 'a', 'ceviri' => 'b' );
esit( true, QRMS_Tasima::ceviri_satiri_gecerli( $iyi ), 'geçerli satır' );
esit( true, QRMS_Tasima::ceviri_satiri_gecerli( array_merge( $iyi, array( 'dil' => 'zh-CN' ) ) ), 'zh-CN geçerli' );
foreach ( array(
	array_merge( $iyi, array( 'dil' => 'tr' ) ),
	array_merge( $iyi, array( 'dil' => "en'; DROP" ) ),
	array_merge( $iyi, array( 'tip' => 'Bad Type!' ) ),
	array_merge( $iyi, array( 'tip' => str_repeat( 'a', 21 ) ) ),
	array_merge( $iyi, array( 'alan' => '' ) ),
	array_merge( $iyi, array( 'alan' => str_repeat( 'x', 192 ) ) ),
	array_merge( $iyi, array( 'ceviri' => array( 'x' ) ) ),
	array( 'tip' => 'product' ),
	'metin',
) as $i => $bozuk ) {
	esit( false, QRMS_Tasima::ceviri_satiri_gecerli( $bozuk ), "geçersiz satır #$i" );
}

// 11) Masa satırı.
foreach ( array( array( 'ad' => 'Masa 1', 'slug' => 'masa-1' ), array( 'ad' => 'VIP Salon', 'slug' => 'vip-salon' ), array( 'ad' => 'Ak\u0131ll\u0131', 'slug' => 'ak%c4%b1ll%c4%b1' ), array( 'ad' => '1', 'slug' => '1' ) ) as $m ) {
	esit( true, QRMS_Tasima::masa_satiri_gecerli( $m ), 'masa geçerli: ' . $m['slug'] );
}
foreach ( array( array( 'ad' => '', 'slug' => 'x' ), array( 'ad' => 'a', 'slug' => '' ), array( 'ad' => 'a', 'slug' => 'Büyük Harf' ), array( 'ad' => 'a', 'slug' => "x';--" ), array( 'ad' => 'a', 'slug' => '../x' ), array( 'ad' => str_repeat( 'a', 101 ), 'slug' => 'x' ), array( 'ad' => 'a', 'slug' => str_repeat( 'a', 101 ) ), array( 'ad' => array(), 'slug' => 'x' ), array( 'ad' => 'a' ), 'metin' ) as $i => $m ) {
	esit( false, QRMS_Tasima::masa_satiri_gecerli( $m ), "masa geçersiz #$i" );
}

echo $hata ? "\n$hata / $say BAŞARISIZ\n" : "$say / $say geçti\n";
exit( $hata ? 1 : 0 );
