<?php
/**
 * Galeri ayarları yönetim ekranı şablonu.
 *
 * @package QR_Menu_Suite
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap qrmgm-wrap">
	<h1 class="qrmgm-title">Galeri Ayarları</h1>
	<form method="post">
		<?php wp_nonce_field( 'qrmgm_save_settings_action', 'qrmgm_settings_nonce' ); ?>
		<table class="form-table">
			<tr><th>Kart Radius (px)</th><td><input type="number" name="radius" value="<?php echo esc_attr( $s['radius'] ); ?>" min="0" max="60" /></td></tr>
			<tr><th>Kart Gölgesi</th><td>
				<select name="shadow">
					<?php foreach ( [ 'none' => 'Yok', 'light' => 'Hafif', 'medium' => 'Orta', 'strong' => 'Güçlü' ] as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['shadow'], $val ); ?>><?php echo esc_html( $label ); ?></option>
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
					<?php foreach ( [ 'glass' => 'Cam Efekti', 'zoom' => 'Zoom', 'gold' => 'Gold Shine', 'tilt' => '3D Tilt', 'gradient' => 'Gradient Overlay' ] as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['hover_effect'], $val ); ?>><?php echo esc_html( $label ); ?></option>
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
					<option value="Poppins" <?php selected( $s['font'], 'Poppins' ); ?>>Poppins</option>
					<option value="Inter" <?php selected( $s['font'], 'Inter' ); ?>>Inter</option>
				</select>
			</td></tr>
			<tr><th>Overlay Opaklığı (%)</th><td><input type="number" name="overlay_opacity" value="<?php echo esc_attr( $s['overlay_opacity'] ); ?>" min="0" max="100" /></td></tr>
		</table>
		<?php submit_button( 'Ayarları Kaydet' ); ?>
	</form>
</div>
