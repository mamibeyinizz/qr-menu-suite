<?php
/**
 * Galeri ayarları yönetim ekranı şablonu.
 *
 * @package QR_Menu_Suite
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;

$fonts      = $this->gallery_font_choices();
$weights    = [ 400, 500, 600, 700, 800, 900 ];
$aligns     = [ 'left' => 'Sol', 'center' => 'Orta', 'right' => 'Sağ' ];
$transforms = [ 'none' => 'Yok', 'uppercase' => 'Büyük harf', 'capitalize' => 'Kelime başı' ];
$shadow     = ( 'light' === $s['shadow'] ) ? 'soft' : $s['shadow'];
$hover      = $s['hover_effect'];
if ( ! in_array( $hover, [ 'none', 'zoom', 'glass', 'lift' ], true ) ) {
	$hover = 'glass';
}

$shadow_map = [
	'none'   => 'none',
	'soft'   => '0 2px 8px rgba(15,23,42,.08)',
	'medium' => '0 8px 24px rgba(15,23,42,.14)',
	'strong' => '0 16px 40px rgba(15,23,42,.22)',
];
$preview_shadow = $shadow_map[ $shadow ] ?? $shadow_map['medium'];

$overlay = max( 0, min( 100, (int) $s['overlay_opacity'] ) ) / 100;

$divider_margin = '0 auto';
if ( 'center' === $s['divider_align'] ) {
	$divider_margin = 'auto';
} elseif ( 'right' === $s['divider_align'] ) {
	$divider_margin = 'auto 0';
}

$desc_maxw = ( 0 === (int) $s['desc_max_width'] ) ? 'none' : ( (int) $s['desc_max_width'] ) . 'ch';

$preview_vars = sprintf(
	'--qrmgm-radius:%1$dpx;--qrmgm-gap:%2$dpx;--qrmgm-cols-desktop:%3$d;--qrmgm-cols-tablet:%4$d;--qrmgm-cols-mobile:%5$d;--qrmgm-overlay:%6$s;--qrmgm-dark:%7$s;--qrmgm-accent:%8$s;--qrmgm-gold:%8$s;--qrmgm-light:%9$s;--qrmgm-white:%10$s;--qrmgm-font:%11$s;--qrmgm-shadow:%12$s;--qrmgm-title-font:%13$s;--qrmgm-title-size:%14$dpx;--qrmgm-title-color:%15$s;--qrmgm-title-weight:%16$d;--qrmgm-title-align:%17$s;--qrmgm-title-transform:%18$s;--qrmgm-divider-width:%19$dpx;--qrmgm-divider-thickness:%20$dpx;--qrmgm-divider-radius:%21$dpx;--qrmgm-divider-color:%22$s;--qrmgm-divider-margin:%23$s;--qrmgm-desc-font:%24$s;--qrmgm-desc-size:%25$dpx;--qrmgm-desc-color:%26$s;--qrmgm-desc-weight:%27$d;--qrmgm-desc-align:%28$s;--qrmgm-desc-maxw:%29$s',
	(int) $s['radius'],
	(int) $s['gap'],
	(int) $s['columns_desktop'],
	(int) $s['columns_tablet'],
	(int) $s['columns_mobile'],
	$overlay,
	esc_attr( $s['color_dark'] ),
	esc_attr( $s['color_gold'] ),
	esc_attr( $s['color_light'] ),
	esc_attr( $s['color_white'] ),
	esc_attr( $s['font'] ),
	esc_attr( $preview_shadow ),
	esc_attr( $s['title_font'] ),
	(int) $s['title_size'],
	esc_attr( $s['title_color'] ),
	(int) $s['title_weight'],
	esc_attr( $s['title_align'] ),
	esc_attr( $s['title_transform'] ),
	(int) $s['divider_width'],
	(int) $s['divider_thickness'],
	(int) $s['divider_radius'],
	esc_attr( $s['divider_color'] ),
	esc_attr( $divider_margin ),
	esc_attr( $s['desc_font'] ),
	(int) $s['desc_size'],
	esc_attr( $s['desc_color'] ),
	(int) $s['desc_weight'],
	esc_attr( $s['desc_align'] ),
	esc_attr( $desc_maxw )
);

$preview_anim = empty( $s['animations'] ) ? '0' : '1';
?>
<div class="wrap qrmgm-wrap qrmgm-settings-wrap">
	<h1 class="qrmgm-title">Galeri Ayarları</h1>

	<form method="post" id="qrmgm-settings-form" class="qrmgm-settings-form">
		<?php wp_nonce_field( 'qrmgm_save_settings_action', 'qrmgm_settings_nonce' ); ?>

		<div class="qrmgm-settings-layout">
			<div class="qrmgm-settings-main">
				<h2 class="nav-tab-wrapper qrmgm-settings-tabs">
					<a href="#" class="nav-tab nav-tab-active" data-tab="layout">Düzen</a>
					<a href="#" class="nav-tab" data-tab="appearance">Görünüm</a>
					<a href="#" class="nav-tab" data-tab="title">Başlık</a>
					<a href="#" class="nav-tab" data-tab="divider">Ayırıcı</a>
					<a href="#" class="nav-tab" data-tab="desc">Açıklama</a>
					<a href="#" class="nav-tab" data-tab="advanced">Gelişmiş</a>
				</h2>

				<div class="qrmgm-tab-panel is-active" data-tab-panel="layout">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="qrmgm-radius">Kart Radius (px)</label></th>
							<td>
								<input type="number" id="qrmgm-radius" name="radius" value="<?php echo esc_attr( $s['radius'] ); ?>" min="0" max="60" data-qrmgm-var="--qrmgm-radius" data-qrmgm-suffix="px" />
								<p class="description">Görsel kartlarının köşe yuvarlaklığı.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-shadow">Kart Gölgesi</label></th>
							<td>
								<select id="qrmgm-shadow" name="shadow" data-qrmgm-shadow>
									<?php foreach ( [ 'none' => 'Yok', 'soft' => 'Hafif', 'medium' => 'Orta', 'strong' => 'Güçlü' ] as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $shadow, $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Kartlara uygulanan gölge yoğunluğu.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-gap">Boşluk (gap, px)</label></th>
							<td>
								<input type="number" id="qrmgm-gap" name="gap" value="<?php echo esc_attr( $s['gap'] ); ?>" min="0" max="60" data-qrmgm-var="--qrmgm-gap" data-qrmgm-suffix="px" />
								<p class="description">Kartlar arasındaki boşluk mesafesi.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Kolon Sayısı</th>
							<td>
								<label for="qrmgm-cols-desktop">Desktop</label>
								<input type="number" id="qrmgm-cols-desktop" name="columns_desktop" value="<?php echo esc_attr( $s['columns_desktop'] ); ?>" min="1" max="6" style="width:70px;" data-qrmgm-var="--qrmgm-cols-desktop" />
								<label for="qrmgm-cols-tablet" style="margin-left:12px;">Tablet</label>
								<input type="number" id="qrmgm-cols-tablet" name="columns_tablet" value="<?php echo esc_attr( $s['columns_tablet'] ); ?>" min="1" max="6" style="width:70px;" data-qrmgm-var="--qrmgm-cols-tablet" />
								<label for="qrmgm-cols-mobile" style="margin-left:12px;">Mobil</label>
								<input type="number" id="qrmgm-cols-mobile" name="columns_mobile" value="<?php echo esc_attr( $s['columns_mobile'] ); ?>" min="1" max="6" style="width:70px;" data-qrmgm-var="--qrmgm-cols-mobile" />
								<p class="description">Geniş ekran, tablet ve mobilde yan yana görünecek kart sayısı.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qrmgm-tab-panel" data-tab-panel="appearance">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="qrmgm-hover">Hover Efekti</label></th>
							<td>
								<select id="qrmgm-hover" name="hover_effect" data-qrmgm-attr="data-hover">
									<?php foreach ( [ 'none' => 'Yok', 'zoom' => 'Zoom', 'glass' => 'Cam Efekti', 'lift' => 'Kaldır' ] as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $hover, $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Fareyle üzerine gelindiğinde uygulanan efekt.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Animasyonlar</th>
							<td>
								<label><input type="checkbox" name="animations" value="1" <?php checked( $s['animations'], 1 ); ?> data-qrmgm-attr="data-anim" data-qrmgm-attr-on="1" data-qrmgm-attr-off="0" /> Açık</label>
								<p class="description">Giriş ve geçiş animasyonlarını açar veya kapatır.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-overlay">Overlay Opaklığı (%)</label></th>
							<td>
								<input type="number" id="qrmgm-overlay" name="overlay_opacity" value="<?php echo esc_attr( $s['overlay_opacity'] ); ?>" min="0" max="100" data-qrmgm-overlay />
								<p class="description">Görsel üzerindeki karartma yoğunluğu; başlık okunurluğunu artırır.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Renkler</th>
							<td>
								<p>
									<label for="qrmgm-color-dark">Koyu</label>
									<input type="text" id="qrmgm-color-dark" name="color_dark" class="qrmgm-color" value="<?php echo esc_attr( $s['color_dark'] ); ?>" data-qrmgm-var="--qrmgm-dark" />
								</p>
								<p>
									<label for="qrmgm-color-gold">Gold</label>
									<input type="text" id="qrmgm-color-gold" name="color_gold" class="qrmgm-color" value="<?php echo esc_attr( $s['color_gold'] ); ?>" data-qrmgm-var="--qrmgm-accent" data-qrmgm-var-alt="--qrmgm-gold" />
								</p>
								<p>
									<label for="qrmgm-color-light">Açık</label>
									<input type="text" id="qrmgm-color-light" name="color_light" class="qrmgm-color" value="<?php echo esc_attr( $s['color_light'] ); ?>" data-qrmgm-var="--qrmgm-light" />
								</p>
								<p>
									<label for="qrmgm-color-white">Beyaz</label>
									<input type="text" id="qrmgm-color-white" name="color_white" class="qrmgm-color" value="<?php echo esc_attr( $s['color_white'] ); ?>" data-qrmgm-var="--qrmgm-white" />
								</p>
								<p class="description">Galeri arka planı, vurgu ve metin renkleri.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-font">Font</label></th>
							<td>
								<select id="qrmgm-font" name="font" data-qrmgm-var="--qrmgm-font">
									<?php foreach ( $fonts as $font ) : ?>
										<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $s['font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Galeri genelinde kullanılan yazı tipi.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qrmgm-tab-panel" data-tab-panel="title">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="qrmgm-title-font">Font</label></th>
							<td>
								<select id="qrmgm-title-font" name="title_font" data-qrmgm-var="--qrmgm-title-font">
									<?php foreach ( $fonts as $font ) : ?>
										<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $s['title_font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm başlıklarında kullanılan yazı tipi.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-title-size">Boyut (px)</label></th>
							<td>
								<input type="number" id="qrmgm-title-size" name="title_size" value="<?php echo esc_attr( $s['title_size'] ); ?>" min="12" max="72" data-qrmgm-var="--qrmgm-title-size" data-qrmgm-suffix="px" />
								<p class="description">Bölüm başlığının yazı boyutu.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-title-color">Renk</label></th>
							<td>
								<input type="text" id="qrmgm-title-color" name="title_color" class="qrmgm-color" value="<?php echo esc_attr( $s['title_color'] ); ?>" data-qrmgm-var="--qrmgm-title-color" />
								<p class="description">Bölüm başlığının metin rengi.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-title-weight">Kalınlık</label></th>
							<td>
								<select id="qrmgm-title-weight" name="title_weight" data-qrmgm-var="--qrmgm-title-weight">
									<?php foreach ( $weights as $weight ) : ?>
										<option value="<?php echo esc_attr( (string) $weight ); ?>" <?php selected( (int) $s['title_weight'], $weight ); ?>><?php echo esc_html( (string) $weight ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm başlığının font kalınlığı.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-title-align">Hizalama</label></th>
							<td>
								<select id="qrmgm-title-align" name="title_align" data-qrmgm-var="--qrmgm-title-align">
									<?php foreach ( $aligns as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['title_align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm başlığının yatay hizalaması.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-title-transform">Dönüşüm</label></th>
							<td>
								<select id="qrmgm-title-transform" name="title_transform" data-qrmgm-var="--qrmgm-title-transform">
									<?php foreach ( $transforms as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['title_transform'], $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm başlığında büyük/küçük harf dönüşümü.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qrmgm-tab-panel" data-tab-panel="divider">
					<table class="form-table">
						<tr>
							<th scope="row">Göster</th>
							<td>
								<label><input type="checkbox" name="divider_show" value="1" <?php checked( $s['divider_show'], 1 ); ?> data-qrmgm-toggle=".qrmgm-preview-divider" /> Açık</label>
								<p class="description">Bölüm başlığının altında ayırıcı çizgi gösterir.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-divider-align">Hizalama</label></th>
							<td>
								<select id="qrmgm-divider-align" name="divider_align" data-qrmgm-divider-margin>
									<?php foreach ( $aligns as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['divider_align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Ayırıcı çizginin yatay hizalaması.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-divider-color">Renk</label></th>
							<td>
								<input type="text" id="qrmgm-divider-color" name="divider_color" class="qrmgm-color" value="<?php echo esc_attr( $s['divider_color'] ); ?>" data-qrmgm-var="--qrmgm-divider-color" />
								<p class="description">Ayırıcı çizginin rengi.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-divider-width">Genişlik (px)</label></th>
							<td>
								<input type="number" id="qrmgm-divider-width" name="divider_width" value="<?php echo esc_attr( $s['divider_width'] ); ?>" min="0" max="400" data-qrmgm-var="--qrmgm-divider-width" data-qrmgm-suffix="px" />
								<p class="description">Ayırıcı çizginin uzunluğu.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-divider-thickness">Kalınlık (px)</label></th>
							<td>
								<input type="number" id="qrmgm-divider-thickness" name="divider_thickness" value="<?php echo esc_attr( $s['divider_thickness'] ); ?>" min="1" max="12" data-qrmgm-var="--qrmgm-divider-thickness" data-qrmgm-suffix="px" />
								<p class="description">Ayırıcı çizginin kalınlığı.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-divider-radius">Köşe (px)</label></th>
							<td>
								<input type="number" id="qrmgm-divider-radius" name="divider_radius" value="<?php echo esc_attr( $s['divider_radius'] ); ?>" min="0" max="20" data-qrmgm-var="--qrmgm-divider-radius" data-qrmgm-suffix="px" />
								<p class="description">Ayırıcı çizginin köşe yuvarlaklığı.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qrmgm-tab-panel" data-tab-panel="desc">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="qrmgm-desc-font">Font</label></th>
							<td>
								<select id="qrmgm-desc-font" name="desc_font" data-qrmgm-var="--qrmgm-desc-font">
									<?php foreach ( $fonts as $font ) : ?>
										<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $s['desc_font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm açıklamasında kullanılan yazı tipi.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-desc-size">Boyut (px)</label></th>
							<td>
								<input type="number" id="qrmgm-desc-size" name="desc_size" value="<?php echo esc_attr( $s['desc_size'] ); ?>" min="10" max="36" data-qrmgm-var="--qrmgm-desc-size" data-qrmgm-suffix="px" />
								<p class="description">Bölüm açıklamasının yazı boyutu.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-desc-color">Renk</label></th>
							<td>
								<input type="text" id="qrmgm-desc-color" name="desc_color" class="qrmgm-color" value="<?php echo esc_attr( $s['desc_color'] ); ?>" data-qrmgm-var="--qrmgm-desc-color" />
								<p class="description">Bölüm açıklamasının metin rengi.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-desc-weight">Kalınlık</label></th>
							<td>
								<select id="qrmgm-desc-weight" name="desc_weight" data-qrmgm-var="--qrmgm-desc-weight">
									<?php foreach ( $weights as $weight ) : ?>
										<option value="<?php echo esc_attr( (string) $weight ); ?>" <?php selected( (int) $s['desc_weight'], $weight ); ?>><?php echo esc_html( (string) $weight ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm açıklamasının font kalınlığı.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-desc-align">Hizalama</label></th>
							<td>
								<select id="qrmgm-desc-align" name="desc_align" data-qrmgm-var="--qrmgm-desc-align">
									<?php foreach ( $aligns as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['desc_align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Bölüm açıklamasının yatay hizalaması.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qrmgm-desc-max-width">Maks. genişlik (ch)</label></th>
							<td>
								<input type="number" id="qrmgm-desc-max-width" name="desc_max_width" value="<?php echo esc_attr( $s['desc_max_width'] ); ?>" min="0" max="200" data-qrmgm-desc-maxw />
								<p class="description">Bölüm açıklamasının satır genişliği; 0 sınırsız demektir.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="qrmgm-tab-panel" data-tab-panel="advanced">
					<table class="form-table">
						<tr>
							<th scope="row">Lightbox</th>
							<td>
								<label><input type="checkbox" name="lightbox" value="1" <?php checked( $s['lightbox'], 1 ); ?> /> Açık</label>
								<p class="description">Görsellere tıklandığında tam ekran lightbox açar.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Filtre Barı</th>
							<td>
								<label><input type="checkbox" name="filter_bar" value="1" <?php checked( $s['filter_bar'], 1 ); ?> /> Açık</label>
								<p class="description">Birden fazla bölüm varken üstte filtre menüsü gösterir.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Lazy Load</th>
							<td>
								<label><input type="checkbox" name="lazy_load" value="1" <?php checked( $s['lazy_load'], 1 ); ?> /> Açık</label>
								<p class="description">Görselleri sayfa kaydırıldıkça yükleyerek performansı artırır.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">WebP Otomatik Dönüşüm</th>
							<td>
								<label><input type="checkbox" name="webp" value="1" <?php checked( $s['webp'], 1 ); ?> /> Açık</label>
								<p class="description">Yüklenen görselleri otomatik olarak WebP formatına dönüştürür.</p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( 'Ayarları Kaydet' ); ?>
			</div>

			<aside class="qrmgm-settings-preview-col">
				<div class="qrmgm-settings-preview-sticky">
					<h2>Canlı Önizleme</h2>
					<div
						id="qrmgm-live-preview"
						class="qrmgm-gallery qrmgm-settings-preview-gallery"
						data-hover="<?php echo esc_attr( $hover ); ?>"
						data-anim="<?php echo esc_attr( $preview_anim ); ?>"
						style="<?php echo esc_attr( $preview_vars ); ?>"
					>
						<section class="qrmgm-section">
							<header class="qrmgm-section-head">
								<h2 class="qrmgm-section-title">Örnek Bölüm</h2>
								<span class="qrmgm-section-divider qrmgm-preview-divider" aria-hidden="true"<?php echo empty( $s['divider_show'] ) ? ' style="display:none;"' : ''; ?>></span>
								<p class="qrmgm-section-desc">Örnek bölüm açıklaması burada görünür.</p>
							</header>
							<div class="qrmgm-grid qrmgm-preview-grid">
								<?php for ( $i = 0; $i < 6; $i++ ) : ?>
									<figure class="qrmgm-item qrmgm-preview-item">
										<div class="qrmgm-preview-placeholder" aria-hidden="true"></div>
									</figure>
								<?php endfor; ?>
							</div>
						</section>
					</div>
				</div>
			</aside>
		</div>
	</form>
</div>
