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
?>
<div class="wrap qrmgm-wrap">
	<h1 class="qrmgm-title">Galeri Ayarları</h1>
	<form method="post">
		<?php wp_nonce_field( 'qrmgm_save_settings_action', 'qrmgm_settings_nonce' ); ?>
		<table class="form-table">
			<tr><th>Kart Radius (px)</th><td><input type="number" name="radius" value="<?php echo esc_attr( $s['radius'] ); ?>" min="0" max="60" /></td></tr>
			<tr><th>Kart Gölgesi</th><td>
				<select name="shadow">
					<?php foreach ( [ 'none' => 'Yok', 'soft' => 'Hafif', 'medium' => 'Orta', 'strong' => 'Güçlü' ] as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $shadow, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Boşluk (gap, px)</th><td><input type="number" name="gap" value="<?php echo esc_attr( $s['gap'] ); ?>" min="0" max="60" /></td></tr>
			<tr><th>Kolon Sayısı (Desktop / Tablet / Mobil)</th><td>
				<input type="number" name="columns_desktop" value="<?php echo esc_attr( $s['columns_desktop'] ); ?>" min="1" max="6" style="width:70px;" />
				<input type="number" name="columns_tablet" value="<?php echo esc_attr( $s['columns_tablet'] ); ?>" min="1" max="6" style="width:70px;" />
				<input type="number" name="columns_mobile" value="<?php echo esc_attr( $s['columns_mobile'] ); ?>" min="1" max="6" style="width:70px;" />
			</td></tr>
			<tr><th>Hover Efekti</th><td>
				<select name="hover_effect">
					<?php foreach ( [ 'none' => 'Yok', 'zoom' => 'Zoom', 'glass' => 'Cam Efekti', 'lift' => 'Kaldır' ] as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $hover, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Animasyonlar</th><td><label><input type="checkbox" name="animations" value="1" <?php checked( $s['animations'], 1 ); ?> /> Açık</label></td></tr>
			<tr><th>Lightbox</th><td><label><input type="checkbox" name="lightbox" value="1" <?php checked( $s['lightbox'], 1 ); ?> /> Açık</label></td></tr>
			<tr><th>Filtre Barı</th><td><label><input type="checkbox" name="filter_bar" value="1" <?php checked( $s['filter_bar'], 1 ); ?> /> Açık</label></td></tr>
			<tr><th>Lazy Load</th><td><label><input type="checkbox" name="lazy_load" value="1" <?php checked( $s['lazy_load'], 1 ); ?> /> Açık</label></td></tr>
			<tr><th>WebP Otomatik Dönüşüm</th><td><label><input type="checkbox" name="webp" value="1" <?php checked( $s['webp'], 1 ); ?> /> Açık</label></td></tr>
			<tr><th>Renkler</th><td>
				<label>Koyu <input type="text" name="color_dark" class="qrmgm-color" value="<?php echo esc_attr( $s['color_dark'] ); ?>" /></label>
				<label style="margin-left:12px;">Gold <input type="text" name="color_gold" class="qrmgm-color" value="<?php echo esc_attr( $s['color_gold'] ); ?>" /></label>
				<label style="margin-left:12px;">Açık <input type="text" name="color_light" class="qrmgm-color" value="<?php echo esc_attr( $s['color_light'] ); ?>" /></label>
				<label style="margin-left:12px;">Beyaz <input type="text" name="color_white" class="qrmgm-color" value="<?php echo esc_attr( $s['color_white'] ); ?>" /></label>
			</td></tr>
			<tr><th>Font</th><td>
				<select name="font">
					<?php foreach ( $fonts as $font ) : ?>
						<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $s['font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Overlay Opaklığı (%)</th><td><input type="number" name="overlay_opacity" value="<?php echo esc_attr( $s['overlay_opacity'] ); ?>" min="0" max="100" /></td></tr>
		</table>

		<h2>Bölüm Başlığı</h2>
		<table class="form-table">
			<tr><th>Font</th><td>
				<select name="title_font">
					<?php foreach ( $fonts as $font ) : ?>
						<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $s['title_font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Boyut (px)</th><td><input type="number" name="title_size" value="<?php echo esc_attr( $s['title_size'] ); ?>" min="12" max="72" /></td></tr>
			<tr><th>Renk</th><td><input type="text" name="title_color" class="qrmgm-color" value="<?php echo esc_attr( $s['title_color'] ); ?>" /></td></tr>
			<tr><th>Kalınlık</th><td>
				<select name="title_weight">
					<?php foreach ( $weights as $weight ) : ?>
						<option value="<?php echo esc_attr( (string) $weight ); ?>" <?php selected( (int) $s['title_weight'], $weight ); ?>><?php echo esc_html( (string) $weight ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Hizalama</th><td>
				<select name="title_align">
					<?php foreach ( $aligns as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['title_align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Dönüşüm</th><td>
				<select name="title_transform">
					<?php foreach ( $transforms as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['title_transform'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
		</table>

		<h2>Ayırıcı Çizgi</h2>
		<table class="form-table">
			<tr><th>Göster</th><td><label><input type="checkbox" name="divider_show" value="1" <?php checked( $s['divider_show'], 1 ); ?> /> Açık</label></td></tr>
			<tr><th>Hizalama</th><td>
				<select name="divider_align">
					<?php foreach ( $aligns as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['divider_align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Renk</th><td><input type="text" name="divider_color" class="qrmgm-color" value="<?php echo esc_attr( $s['divider_color'] ); ?>" /></td></tr>
			<tr><th>Genişlik (px)</th><td><input type="number" name="divider_width" value="<?php echo esc_attr( $s['divider_width'] ); ?>" min="0" max="400" /></td></tr>
			<tr><th>Kalınlık (px)</th><td><input type="number" name="divider_thickness" value="<?php echo esc_attr( $s['divider_thickness'] ); ?>" min="1" max="12" /></td></tr>
			<tr><th>Köşe (px)</th><td><input type="number" name="divider_radius" value="<?php echo esc_attr( $s['divider_radius'] ); ?>" min="0" max="20" /></td></tr>
		</table>

		<h2>Bölüm Açıklaması</h2>
		<table class="form-table">
			<tr><th>Font</th><td>
				<select name="desc_font">
					<?php foreach ( $fonts as $font ) : ?>
						<option value="<?php echo esc_attr( $font ); ?>" <?php selected( $s['desc_font'], $font ); ?>><?php echo esc_html( $font ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Boyut (px)</th><td><input type="number" name="desc_size" value="<?php echo esc_attr( $s['desc_size'] ); ?>" min="10" max="36" /></td></tr>
			<tr><th>Renk</th><td><input type="text" name="desc_color" class="qrmgm-color" value="<?php echo esc_attr( $s['desc_color'] ); ?>" /></td></tr>
			<tr><th>Kalınlık</th><td>
				<select name="desc_weight">
					<?php foreach ( $weights as $weight ) : ?>
						<option value="<?php echo esc_attr( (string) $weight ); ?>" <?php selected( (int) $s['desc_weight'], $weight ); ?>><?php echo esc_html( (string) $weight ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Hizalama</th><td>
				<select name="desc_align">
					<?php foreach ( $aligns as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['desc_align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th>Maks. genişlik (ch)</th><td><input type="number" name="desc_max_width" value="<?php echo esc_attr( $s['desc_max_width'] ); ?>" min="0" max="200" /> <span class="description">0 = sınırsız</span></td></tr>
		</table>

		<?php submit_button( 'Ayarları Kaydet' ); ?>
	</form>
</div>
