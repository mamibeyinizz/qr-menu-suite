<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Nav_Design_Trait {

    public function render_nav_design_page() {
        $nd = $this->get_nav_design_settings();

        $presets = [
            'premium_gold' => [
                'label'       => '◆ Premium Altın',
                'desc'        => 'Koyu arka plan, altın vurgu, pill göstergesi (Getir-style)',
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
                'label'       => '☀ Modern Beyaz',
                'desc'        => 'Açık arka plan, turuncu vurgu, pill buton göstergesi',
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
                'label'       => '▪ Koyu Minimal',
                'desc'        => 'Siyah, kompakt, beyaz vurgu, nokta göstergesi',
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
        ?>
        <div id="rma-nd-saved" class="rma-toast">✔ Ayarlar kaydedildi.</div>

            <form method="post" action="options.php">
                <?php settings_fields( 'rma_nav_design_group' ); ?>

                <div class="rma-card">
                    <h2 class="rma-card-title">Hazır Tasarımlar</h2>
                    <p class="rma-card-desc">Bir tasarıma tıklayın — ayarlar otomatik dolacak. Önizlemelerdeki renkler menünün kendi renkleridir.</p>

                    <div class="rma-choice-grid">
                        <?php foreach ( $presets as $pid => $preset ) :
                            $is_active = $current_preset === $pid;
                        ?>
                        <div class="rma-choice rma-nd-preset<?php echo $is_active ? ' is-selected' : ''; ?>" data-preset="<?php echo $pid; ?>" data-values='<?php echo json_encode( $preset['values'] ); ?>'>
                            <div class="rma-preset-preview" style="background:<?php echo $preset['preview_bg']; ?>;">
                                <div class="rma-preset-strip">
                                    <span class="rma-preset-chip" style="background:rgba(255,255,255,0.06);color:<?php echo $preset['preview_txt']; ?>;">Kategori</span>
                                    <span class="rma-preset-chip is-active" style="background:<?php echo $preset['preview_acc']; ?>;color:#0a0a0a;">Aktif</span>
                                    <span class="rma-preset-chip" style="background:rgba(255,255,255,0.06);color:<?php echo $preset['preview_txt']; ?>;">Diğer</span>
                                </div>
                            </div>
                            <div class="rma-preset-meta">
                                <span class="rma-choice-name"><?php echo $preset['label']; ?></span>
                                <span class="rma-choice-sub"><?php echo $preset['desc']; ?></span>
                            </div>
                            <?php if ( $is_active ) : ?>
                            <div class="rma-badge rma-active-badge">Aktif</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="rma_nd_preset" name="rma_nav_design_settings[preset]" value="<?php echo esc_attr( $current_preset ); ?>">
                </div>

                <?php
                /* Canlı önizleme — frontend'in gerçek nav markup'ı ve gerçek
                   nav stylesheet'i (assets/css/rma-nav.css) kullanılır; görünüm
                   tamamen aşağıdaki --rma-nav-* değişkenlerinden sürülür, JS
                   ayarlar değiştikçe bu değişkenleri günceller. */
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
                <div class="rma-card rma-preview-card">
                    <h2 class="rma-card-title">Önizleme</h2>
                    <p class="rma-card-desc">Ayarları değiştirdikçe kategori çubuğu burada anında güncellenir — kaydetmenize gerek yok. Yapışkan ve blur ayarları kaydırma davranışıyla ilgili olduğundan burada gösterilmez.</p>

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

                <div class="rma-card">
                    <h2 class="rma-card-title">Manuel Ayarlar</h2>
                    <p class="rma-card-desc">Hazır tasarım seçtikten sonra istediğiniz değerleri değiştirebilirsiniz.</p>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Renkler</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $color_fields_nd = [
                                'bg'           => [ 'Arka Plan',          'Navigasyon çubuğunun arka plan rengi.' ],
                                'text'         => [ 'Yazı Rengi',         'Pasif kategori butonlarının yazı rengi.' ],
                                'active'       => [ 'Aktif Buton Rengi',  'Seçili kategorinin vurgu rengi.' ],
                                'border_color' => [ 'Alt Kenarlık Rengi', 'Navigasyon çubuğunun alt border rengi.' ],
                            ];
                            foreach ( $color_fields_nd as $key => [$label, $desc] ) :
                                $val = $nd[$key] ?? '#000000';
                            ?>
                            <tr>
                                <th><label><?php echo $label; ?></label></th>
                                <td>
                                    <input type="text" id="rma_nd_<?php echo $key; ?>" name="rma_nav_design_settings[<?php echo $key; ?>]" value="<?php echo esc_attr( $val ); ?>" class="rma-color-picker" data-default-color="<?php echo esc_attr( $val ); ?>">
                                    <p class="description rma-desc"><?php echo $desc; ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Boşluklar & Boyutlar</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $range_fields_nd = [
                                'padding_top'    => [ 'Üst Boşluk (px)',           '0', '60', '1', 'Navigasyon üst iç boşluğu. Varsayılan: 12px' ],
                                'padding_bottom' => [ 'Alt Boşluk (px)',           '0', '60', '1', 'Navigasyon alt iç boşluğu. Varsayılan: 12px' ],
                                'btn_padding_h'  => [ 'Buton Yatay Padding (px)',  '6', '40', '1', 'Her butonun sağ/sol iç boşluğu. Varsayılan: 16px' ],
                                'btn_padding_v'  => [ 'Buton Dikey Padding (px)',  '4', '30', '1', 'Her butonun üst/alt iç boşluğu. Varsayılan: 10px' ],
                                'btn_spacing'    => [ 'Butonlar Arası Mesafe (px)','0', '20', '1', 'Kategori butonları arasındaki boşluk. Varsayılan: 4px' ],
                            ];
                            foreach ( $range_fields_nd as $key => [$label, $min, $max, $step, $desc] ) :
                                $val = $nd[$key] ?? '0';
                            ?>
                            <tr>
                                <th><label><?php echo $label; ?></label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="rma_nav_design_settings[<?php echo $key; ?>]"
                                               value="<?php echo esc_attr( $val ); ?>"
                                               min="<?php echo $min; ?>" max="<?php echo $max; ?>" step="<?php echo $step; ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'px'">
                                        <span class="rma-range-val"><?php echo $val; ?>px</span>
                                    </div>
                                    <p class="description rma-desc"><?php echo $desc; ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Tipografi</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label>Font Boyutu (rem)</label></th>
                                <td>
                                    <div class="rma-range-row">
                                        <input type="range" name="rma_nav_design_settings[font_size]"
                                               value="<?php echo esc_attr( $nd['font_size'] ); ?>"
                                               min="0.6" max="1.4" step="0.01"
                                               oninput="this.nextElementSibling.textContent=this.value+'rem'">
                                        <span class="rma-range-val"><?php echo $nd['font_size']; ?>rem</span>
                                    </div>
                                    <p class="description rma-desc">Kategori buton yazı boyutu. Varsayılan: 0.83rem</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Font Kalınlığı</label></th>
                                <td>
                                    <select name="rma_nav_design_settings[font_weight]" class="rma-select-narrow">
                                        <?php foreach ( ['300','400','500','600','700'] as $w ) : ?>
                                            <option value="<?php echo $w; ?>" <?php selected( $nd['font_weight'], $w ); ?>><?php echo $w; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Aktif Kategori Göstergesi</h3>
                        <div class="rma-choice-grid">
                            <?php
                            $indicators = [
                                'background'  => [ '● Pill (Getir)', 'Aktif buton renkli arka plan (pill) — varsayılan' ],
                                'bottom_line' => [ '― Alt Çizgi', 'Aktif butonun altında renkli çizgi' ],
                                'dot'         => [ '· Nokta', 'Butonun altında küçük yuvarlak nokta' ],
                                'none'        => [ '✕ Yok', 'Sadece yazı rengi değişir' ],
                            ];
                            foreach ( $indicators as $ikey => [$ilabel, $idesc] ) :
                                $is_active = $nd['active_indicator'] === $ikey;
                            ?>
                            <label class="rma-choice rma-choice-pad<?php echo $is_active ? ' is-selected' : ''; ?>">
                                <input type="radio" name="rma_nav_design_settings[active_indicator]" value="<?php echo $ikey; ?>" <?php checked( $nd['active_indicator'], $ikey ); ?>>
                                <span class="rma-choice-name"><?php echo $ilabel; ?></span>
                                <span class="rma-choice-sub"><?php echo $idesc; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="rma-section">
                        <h3 class="rma-section-title">Davranış</h3>
                        <table class="form-table rma-form-table">
                            <tr>
                                <th>Yapışkan (Sticky)</th>
                                <td>
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="rma_nav_design_settings[sticky]" value="1" <?php checked( $nd['sticky'], '1' ); ?>>
                                        <span>Sayfa kaydırılınca menü üstte sabit kalsın</span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>Blur (Bulanıklaştırma)</th>
                                <td>
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="rma_nav_design_settings[blur]" value="1" <?php checked( $nd['blur'], '1' ); ?>>
                                        <span>Sticky modda arka planı bulanıklaştır (frosted glass efekti)</span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p>
                    <?php submit_button( 'Kaydet', 'primary', 'rma_submit_nav_design', false ); ?>
                </p>
            </form>
        <?php
    }
}
