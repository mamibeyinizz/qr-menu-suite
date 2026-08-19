<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Nav_Design_Trait {

    /**
     * "Kategori Çubuğu" bölümü — Görünüm sayfasının son kartı.
     *
     * Menünün üstünde duran, kaydırınca sabit kalabilen kategori şeridinin
     * tasarımı. Hazır tasarımlar ve canlı önizleme açıkta durur; tek tek
     * ayarlar "elle ayarla" bölümünde toplanmıştır.
     */
    public function render_nav_design_page() {
        $nd = $this->get_nav_design_settings();

        $presets = [
            'premium_gold' => [
                'label'       => 'Premium Altın',
                'desc'        => 'Koyu zemin, altın vurgu',
                'preview_bg'  => '#0a0a0a',
                'preview_acc' => '#c9a84c',
                'preview_txt' => '#888888',
                'values'      => [
                    'bg' => '#0a0a0a', 'text' => '#888888', 'active' => '#c9a84c',
                    'border_color' => '#2a2a2a', 'padding_top' => '12', 'padding_bottom' => '12',
                    'btn_padding_h' => '16', 'btn_padding_v' => '10', 'btn_spacing' => '4',
                    'font_size' => '0.83', 'font_weight' => '600', 'sticky' => '1',
                    'blur' => '1', 'active_indicator' => 'background',
                ],
            ],
            'modern_white' => [
                'label'       => 'Modern Beyaz',
                'desc'        => 'Açık zemin, turuncu vurgu',
                'preview_bg'  => '#ffffff',
                'preview_acc' => '#e67e22',
                'preview_txt' => '#666666',
                'values'      => [
                    'bg' => '#ffffff', 'text' => '#666666', 'active' => '#e67e22',
                    'border_color' => '#eeeeee', 'padding_top' => '12', 'padding_bottom' => '12',
                    'btn_padding_h' => '16', 'btn_padding_v' => '10', 'btn_spacing' => '4',
                    'font_size' => '0.83', 'font_weight' => '600', 'sticky' => '1',
                    'blur' => '0', 'active_indicator' => 'background',
                ],
            ],
            'dark_minimal' => [
                'label'       => 'Koyu Minimal',
                'desc'        => 'Siyah, sade, beyaz vurgu',
                'preview_bg'  => '#0d0d0d',
                'preview_acc' => '#ffffff',
                'preview_txt' => '#555555',
                'values'      => [
                    'bg' => '#0d0d0d', 'text' => '#555555', 'active' => '#ffffff',
                    'border_color' => 'transparent', 'padding_top' => '12', 'padding_bottom' => '12',
                    'btn_padding_h' => '14', 'btn_padding_v' => '9', 'btn_spacing' => '2',
                    'font_size' => '0.81', 'font_weight' => '600', 'sticky' => '1',
                    'blur' => '1', 'active_indicator' => 'dot',
                ],
            ],
        ];

        $current_preset = $nd['preset'] ?? 'premium_gold';

        /* Canlı önizleme — frontend'in gerçek nav markup'ı ve gerçek nav
           stylesheet'i (assets/css/rma-nav.css) kullanılır; görünüm tamamen
           aşağıdaki --rma-nav-* değişkenlerinden sürülür, JS ayarlar
           değiştikçe bu değişkenleri günceller. */
        $preview_vars = [
            '--rma-nav-bg'          => $nd['bg'],
            '--rma-nav-text'        => $nd['text'],
            '--rma-nav-active'      => $nd['active'],
            '--rma-nav-border'      => $nd['border_color'],
            '--rma-nav-pt'          => $nd['padding_top'] . 'px',
            '--rma-nav-pb'          => $nd['padding_bottom'] . 'px',
            '--rma-nav-btn-ph'      => $nd['btn_padding_h'] . 'px',
            '--rma-nav-btn-pv'      => $nd['btn_padding_v'] . 'px',
            '--rma-nav-btn-gap'     => $nd['btn_spacing'] . 'px',
            '--rma-nav-font-size'   => $nd['font_size'] . 'rem',
            '--rma-nav-font-weight' => $nd['font_weight'],
            '--rma-font-body'       => "'" . $this->get_typo_settings()['body_font'] . "',system-ui,sans-serif",
            // Yalnızca .rma-nav-btn:hover kuralı için gerekir
            '--rma-text'            => $this->get_color_settings()['text'],
        ];
        $preview_style = '';
        foreach ( $preview_vars as $k => $v ) {
            $preview_style .= $k . ':' . $v . ';';
        }
        ?>
        <div id="rma-nd-saved" class="rma-toast">✔ Ayarlar kaydedildi.</div>

        <form method="post" action="options.php">
            <?php settings_fields( 'rma_nav_design_group' ); ?>

            <div class="rma-card" id="rma-kategori-cubugu">
                <h2 class="rma-card-title">4. Kategori Çubuğu</h2>
                <p class="rma-card-desc">Menünün üstünde duran, kategoriler arasında geçiş yapılan şerit. Bir hazır tasarıma dokunun; alttaki önizleme anında değişir.</p>

                <div class="rma-choice-grid">
                    <?php foreach ( $presets as $pid => $preset ) :
                        $is_active = $current_preset === $pid;
                    ?>
                    <div class="rma-choice rma-nd-preset<?php echo $is_active ? ' is-selected' : ''; ?>"
                         role="button"
                         tabindex="0"
                         data-preset="<?php echo esc_attr( $pid ); ?>"
                         data-values='<?php echo esc_attr( wp_json_encode( $preset['values'] ) ); ?>'>
                        <div class="rma-preset-preview" style="background:<?php echo esc_attr( $preset['preview_bg'] ); ?>;">
                            <div class="rma-preset-strip">
                                <span class="rma-preset-chip" style="background:rgba(255,255,255,0.06);color:<?php echo esc_attr( $preset['preview_txt'] ); ?>;">Kategori</span>
                                <span class="rma-preset-chip is-active" style="background:<?php echo esc_attr( $preset['preview_acc'] ); ?>;color:#0a0a0a;">Aktif</span>
                                <span class="rma-preset-chip" style="background:rgba(255,255,255,0.06);color:<?php echo esc_attr( $preset['preview_txt'] ); ?>;">Diğer</span>
                            </div>
                        </div>
                        <div class="rma-preset-meta">
                            <span class="rma-choice-name"><?php echo esc_html( $preset['label'] ); ?></span>
                            <span class="rma-choice-sub"><?php echo esc_html( $preset['desc'] ); ?></span>
                        </div>
                        <?php if ( $is_active ) : ?>
                        <div class="rma-badge rma-active-badge">Seçili</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="rma_nd_preset" name="rma_nav_design_settings[preset]" value="<?php echo esc_attr( $current_preset ); ?>">

                <div class="rma-section rma-preview-section">
                    <h3 class="rma-section-title">Önizleme</h3>
                    <p class="rma-section-desc">Ayarları değiştirdikçe burası anında güncellenir — kaydetmenize gerek yok.</p>

                    <div class="rma-nav-preview"
                         data-rma-ind="<?php echo esc_attr( $nd['active_indicator'] ); ?>"
                         style="<?php echo esc_attr( $preview_style ); ?>"
                         aria-hidden="true">
                        <div class="rma-nav-wrapper">
                            <nav class="rma-nav">
                                <button type="button" class="rma-nav-btn" tabindex="-1">Başlangıçlar</button>
                                <button type="button" class="rma-nav-btn active" tabindex="-1">Ana Yemekler</button>
                                <button type="button" class="rma-nav-btn" tabindex="-1">Tatlılar</button>
                                <button type="button" class="rma-nav-btn" tabindex="-1">İçecekler</button>
                            </nav>
                        </div>
                    </div>
                </div>

                <details class="rma-details">
                    <summary>Kategori çubuğunu elle ayarla</summary>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Renkler</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $color_fields_nd = [
                                'bg'           => [ 'Arka Plan',            'Kategori şeridinin arka plan rengi.' ],
                                'text'         => [ 'Yazı Rengi',           'Seçili olmayan kategorilerin yazı rengi.' ],
                                'active'       => [ 'Seçili Kategori Rengi', 'O an açık olan kategorinin vurgu rengi.' ],
                                'border_color' => [ 'Alt Çizgi Rengi',      'Şeridin altındaki ince çizginin rengi.' ],
                            ];
                            foreach ( $color_fields_nd as $key => $row ) :
                                $val = $nd[ $key ] ?? '#000000';
                            ?>
                            <tr>
                                <th><label for="rma_nd_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $row[0] ); ?></label></th>
                                <td>
                                    <input type="text" id="rma_nd_<?php echo esc_attr( $key ); ?>" name="rma_nav_design_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="rma-color-picker" data-default-color="<?php echo esc_attr( $val ); ?>">
                                    <p class="description rma-desc"><?php echo esc_html( $row[1] ); ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Boşluklar ve Boyutlar</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $range_fields_nd = [
                                'padding_top'    => [ 'Üst Boşluk',            '0', '60', '1', 'Şeridin üst iç boşluğu. Varsayılan: 12' ],
                                'padding_bottom' => [ 'Alt Boşluk',            '0', '60', '1', 'Şeridin alt iç boşluğu. Varsayılan: 12' ],
                                'btn_padding_h'  => [ 'Buton Genişliği',       '6', '40', '1', 'Her kategori butonunun sağ/sol iç boşluğu. Varsayılan: 16' ],
                                'btn_padding_v'  => [ 'Buton Yüksekliği',      '4', '30', '1', 'Her kategori butonunun üst/alt iç boşluğu. Varsayılan: 10' ],
                                'btn_spacing'    => [ 'Butonlar Arası Mesafe', '0', '20', '1', 'Kategori butonları arasındaki boşluk. Varsayılan: 4' ],
                            ];
                            foreach ( $range_fields_nd as $key => $row ) :
                                list( $label, $min, $max, $step, $desc ) = $row;
                                $val = $nd[ $key ] ?? '0';
                            ?>
                            <tr>
                                <th><label for="rma_nd_range_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" id="rma_nd_range_<?php echo esc_attr( $key ); ?>" name="rma_nav_design_settings[<?php echo esc_attr( $key ); ?>]"
                                               value="<?php echo esc_attr( $val ); ?>"
                                               min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'px'">
                                        <span class="rma-range-val"><?php echo esc_html( $val ); ?>px</span>
                                    </div>
                                    <p class="description rma-desc"><?php echo esc_html( $desc ); ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Yazı</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label for="rma_nd_font_size">Yazı Boyutu</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" id="rma_nd_font_size" name="rma_nav_design_settings[font_size]"
                                               value="<?php echo esc_attr( $nd['font_size'] ); ?>"
                                               min="0.6" max="1.4" step="0.01"
                                               oninput="this.nextElementSibling.textContent=this.value+'rem'">
                                        <span class="rma-range-val"><?php echo esc_html( $nd['font_size'] ); ?>rem</span>
                                    </div>
                                    <p class="description rma-desc">Kategori adlarının yazı boyutu. Varsayılan: 0.83</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rma_nd_font_weight">Yazı Kalınlığı</label></th>
                                <td>
                                    <select id="rma_nd_font_weight" name="rma_nav_design_settings[font_weight]" class="rma-select-narrow">
                                        <?php
                                        $nd_weights = [ '300' => '300 — İnce', '400' => '400 — Normal', '500' => '500 — Orta', '600' => '600 — Yarı kalın', '700' => '700 — Kalın' ];
                                        foreach ( $nd_weights as $w => $w_label ) : ?>
                                            <option value="<?php echo esc_attr( $w ); ?>" <?php selected( $nd['font_weight'], $w ); ?>><?php echo esc_html( $w_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Seçili Kategori Nasıl Belli Olsun?</h3>
                        <div class="rma-choice-grid">
                            <?php
                            $indicators = [
                                'background'  => [ 'Renkli Kutu', 'Seçili kategori renkli bir kutu içinde görünür (varsayılan)' ],
                                'bottom_line' => [ 'Alt Çizgi', 'Seçili kategorinin altında renkli bir çizgi olur' ],
                                'dot'         => [ 'Nokta', 'Seçili kategorinin altında küçük bir nokta olur' ],
                                'none'        => [ 'Sadece Renk', 'Sadece yazı rengi değişir' ],
                            ];
                            foreach ( $indicators as $ikey => $row ) :
                                $is_active = $nd['active_indicator'] === $ikey;
                            ?>
                            <label class="rma-choice rma-choice-pad<?php echo $is_active ? ' is-selected' : ''; ?>">
                                <input type="radio" name="rma_nav_design_settings[active_indicator]" value="<?php echo esc_attr( $ikey ); ?>" <?php checked( $nd['active_indicator'], $ikey ); ?>>
                                <span class="rma-choice-name"><?php echo esc_html( $row[0] ); ?></span>
                                <span class="rma-choice-sub"><?php echo esc_html( $row[1] ); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Davranış</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th>Sabit Kalsın</th>
                                <td>
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="rma_nav_design_settings[sticky]" value="1" <?php checked( $nd['sticky'], '1' ); ?>>
                                        <span>Sayfa aşağı kaydırılınca kategori şeridi ekranın üstünde sabit kalsın</span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>Buzlu Cam Etkisi</th>
                                <td>
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="rma_nav_design_settings[blur]" value="1" <?php checked( $nd['blur'], '1' ); ?>>
                                        <span>Sabit kalırken arkasındaki içerik hafif bulanık görünsün</span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </details>
            </div>

            <p class="rma-sticky-save">
                <?php submit_button( 'Kategori Çubuğunu Kaydet', 'primary', 'rma_submit_nav_design', false ); ?>
            </p>
        </form>
        <?php
    }
}
