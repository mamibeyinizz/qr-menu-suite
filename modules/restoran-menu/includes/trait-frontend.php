<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Frontend_Trait {

    private $assets_loaded = false;

    /**
     * Eklenti varlıkları bu istekte gerekli mi?
     *
     * PERF: Karar istek başına bir kez verilir. Elementor verisi (çok büyük
     * bir JSON meta alanı olabilir) yalnızca Elementor gerçekten yüklüyse
     * okunur — Elementor kullanmayan sitelerde bu meta hiç çekilmez.
     */
    private function should_load_assets() {
        if ( isset( $this->rma_memo['should_load_assets'] ) ) {
            return $this->rma_memo['should_load_assets'];
        }

        $result = false;
        $post   = get_post();

        if ( $post instanceof WP_Post ) {
            if ( has_shortcode( $post->post_content, 'restaurant_menu' )
                || has_shortcode( $post->post_content, 'rma_qr_notice' ) ) {
                $result = true;
            } elseif ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
                $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
                if ( $elementor_data && strpos( $elementor_data, 'rma_menu_widget' ) !== false ) {
                    $result = true;
                }
            }
        }

        $this->rma_memo['should_load_assets'] = $result;
        return $result;
    }

    public function frontend_scripts_styles() {
        if ( ! $this->should_load_assets() ) return;
        $this->enqueue_frontend_assets();
    }

    public function enqueue_frontend_assets() {
        if ( $this->assets_loaded ) return;
        $this->assets_loaded = true;

        wp_register_style( 'rma-style', RMA_PLUGIN_URL . 'assets/css/rma-frontend.css', [], $this->asset_version( 'assets/css/rma-frontend.css' ) );
        wp_enqueue_style( 'rma-style' );

        // Kayar kategori navigasyonu ayrı bir dosyada: admin'deki canlı
        // önizleme de aynı dosyayı yükler, böylece iki taraf tek kaynaktan
        // beslenir. Görünüm --rma-nav-* değişkenleriyle sürüldüğü ve bu
        // değişkenler :root'ta tanımlandığı için yükleme sırası fark etmez.
        wp_enqueue_style(
            'rma-nav',
            RMA_PLUGIN_URL . 'assets/css/rma-nav.css',
            [ 'rma-style' ],
            $this->asset_version( 'assets/css/rma-nav.css' )
        );

        $c  = $this->get_color_settings();
        $t  = $this->get_typo_settings();
        $nd = $this->get_nav_design_settings();

        $active_indicator_css = $this->get_nav_indicator_css( $nd['active_indicator'] );

        $sticky_css = '';
        if ( $nd['sticky'] === '1' ) {
            $blur_rule  = $nd['blur'] === '1'
                ? 'backdrop-filter: blur(20px) saturate(200%); -webkit-backdrop-filter: blur(20px) saturate(200%);'
                : '';
            $sticky_css = "
.rma-nav.is-sticky {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    background: color-mix(in srgb, var(--rma-nav-bg) 92%, transparent) !important;
    {$blur_rule}
    border-bottom: 1px solid var(--rma-nav-border);
    box-shadow: 0 4px 28px rgba(0,0,0,0.55), 0 2px 0 var(--rma-nav-active) !important;
    z-index: 90 !important;
}";
        }

        $fonts_needed    = array_unique( [ $t['heading_font'], $t['body_font'], $t['price_font'] ] );
        $google_font_map = [
            'Plus Jakarta Sans'  => 'Plus+Jakarta+Sans:wght@300;400;500;600;700;800',
            'Nunito'             => 'Nunito:wght@300;400;600;700;800',
            'Nunito Sans'        => 'Nunito+Sans:wght@300;400;600;700;800',
            'Inter'              => 'Inter:wght@300;400;500;600;700',
            'Cormorant Garamond' => 'Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400',
            'DM Sans'            => 'DM+Sans:wght@300;400;500;600',
            'Playfair Display'   => 'Playfair+Display:ital,wght@0,400;0,600;1,400',
            'Lato'               => 'Lato:wght@300;400;700',
            'Montserrat'         => 'Montserrat:wght@300;400;600;700',
            'Raleway'            => 'Raleway:wght@300;400;600;700',
            'Open Sans'          => 'Open+Sans:wght@300;400;600',
            'Roboto'             => 'Roboto:wght@300;400;500;700',
            'Merriweather'       => 'Merriweather:ital,wght@0,400;0,700;1,400',
            'Poppins'            => 'Poppins:wght@300;400;500;600;700',
        ];
        $families = [];
        foreach ( $fonts_needed as $fn ) {
            if ( isset( $google_font_map[ $fn ] ) ) $families[] = $google_font_map[ $fn ];
        }
        if ( ! empty( $families ) ) {
            wp_enqueue_style( 'rma-fonts', 'https://fonts.googleapis.com/css2?' . implode( '&', array_map( fn( $f ) => 'family=' . $f, array_unique( $families ) ) ) . '&display=swap', [], null );

            // Google Fonts, render'ı bloklayan ayrı bir host'a bağlanır.
            // preconnect ile DNS + TLS el sıkışması stil dosyası istenmeden
            // önce başlar; ilk yazı gösterimi belirgin biçimde erkene alınır.
            add_filter( 'wp_resource_hints', [ $this, 'font_resource_hints' ], 10, 2 );
        }

        $css_dynamic = "
:root {
    --rma-bg:              {$c['bg']};
    --rma-card:            {$c['card']};
    --rma-text:            {$c['text']};
    --rma-border:          {$c['border']};
    --rma-accent:          {$c['accent']};
    --rma-accent-dim:      color-mix(in srgb,{$c['accent']} 40%,transparent);
    --rma-desc:            {$c['desc']};
    --rma-toolbar-bg:      {$c['toolbar_bg']};
    --rma-modal-bg:        {$c['modal_bg']};
    --rma-filter-btn-border: {$c['filter_btn_border']};
    --rma-filter-btn-text:   {$c['filter_btn_text']};
    --rma-section-title:   {$c['section_title']};
    --rma-radius:          14px;
    --rma-gold-glow:       0 0 18px color-mix(in srgb,{$c['accent']} 35%,transparent);
    --rma-shadow:          0 8px 32px rgba(0,0,0,0.45);
    --rma-font-display:    '{$t['heading_font']}',system-ui,sans-serif;
    --rma-font-body:       '{$t['body_font']}',system-ui,sans-serif;
    --rma-font-price:      '{$t['price_font']}',system-ui,sans-serif;

    --rma-nav-bg:          {$nd['bg']};
    --rma-nav-text:        {$nd['text']};
    --rma-nav-active:      {$nd['active']};
    --rma-nav-border:      {$nd['border_color']};
    --rma-nav-pt:          {$nd['padding_top']}px;
    --rma-nav-pb:          {$nd['padding_bottom']}px;
    --rma-nav-btn-ph:      {$nd['btn_padding_h']}px;
    --rma-nav-btn-pv:      {$nd['btn_padding_v']}px;
    --rma-nav-btn-gap:     {$nd['btn_spacing']}px;
    --rma-nav-font-size:   {$nd['font_size']}rem;
    --rma-nav-font-weight: {$nd['font_weight']};
    --rma-nav-h:           52px;

    --rma-heading-size:    {$t['heading_size']}rem;
    --rma-heading-weight:  {$t['heading_weight']};
    --rma-heading-style:   {$t['heading_style']};
    --rma-heading-color:   {$t['heading_color']};
    --rma-body-size:       {$t['body_size']}rem;
    --rma-body-weight:     {$t['body_weight']};
    --rma-body-color:      {$t['body_color']};
    --rma-desc-size:       {$t['desc_size']}rem;
    --rma-desc-color:      {$t['desc_color']};
    --rma-price-size:      {$t['price_size']}rem;
    --rma-price-color:     {$t['price_color']};
    --rma-modal-title-size:{$t['modal_title_size']}rem;
}
{$sticky_css}
{$active_indicator_css}
";

        wp_add_inline_style( 'rma-style', $css_dynamic );

        // PERF: Frontend script'i artık saf vanilla JS — jQuery bağımlılığı
        // kaldırıldı. Menü sayfasında jQuery'yi başka hiçbir bileşen
        // istemiyorsa ~90 KB'lık indirme + ayrıştırma maliyeti tamamen düşer.
        // 'defer' stratejisi ile script HTML ayrıştırmasını da bloklamaz
        // (WP 6.3+; eski sürümlerde dizi "footer" olarak yorumlanır ve
        // davranış eskisiyle aynı kalır).
        wp_register_script(
            'rma-script',
            RMA_PLUGIN_URL . 'assets/js/rma-frontend.js',
            [],
            $this->asset_version( 'assets/js/rma-frontend.js' ),
            [
                'strategy'  => 'defer',
                'in_footer' => true,
            ]
        );
        wp_enqueue_script( 'rma-script' );

        $ajax_url       = admin_url( 'admin-ajax.php' );
        $nonce          = wp_create_nonce( 'rma_ajax_nonce' );
        $suggest_config = $this->get_suggestion_config();
        $suggest_json   = wp_json_encode( $suggest_config );
        $sticky_enabled = ( $nd['sticky'] === '1' ) ? 'true' : 'false';

        // Yalnızca JS'te basılan metinler ve aktif dil. Sunucudan geçmedikleri
        // için çeviri tablosuna buradan köprüleniyor; çeviri eklentisi yoksa
        // t() orijinal Türkçeyi döndürür ve davranış değişmez.
        $i18n_json = wp_json_encode( [
            'suggestions' => $this->t( 'Öneriler' ),
            'empty'       => $this->t( 'Ürün bulunamadı.' ),
            'loadError'   => $this->t( 'Yükleme hatası oluştu. Lütfen sayfayı yenileyin.' ),
            'reloading'   => $this->t( 'Sayfa yenileniyor…' ),
            'lang'        => $this->current_lang(),
        ] );

        // Değişken adları geriye dönük uyumluluk için korunuyor: özel
        // temalar/parçacıklar bu global'lere dokunuyor olabilir.
        $js_dynamic = <<<JSCODE
var AJAX_URL    = '{$ajax_url}';
var NONCE       = '{$nonce}';
var SUGGEST_CFG = {$suggest_json};
var STICKY_ON   = {$sticky_enabled};
var RMA_I18N    = {$i18n_json};
JSCODE;

        wp_add_inline_script( 'rma-script', $js_dynamic, 'before' );
    }

    /**
     * Google Fonts için preconnect ipuçları.
     *
     * @param array  $urls
     * @param string $relation_type
     * @return array
     */
    public function font_resource_hints( $urls, $relation_type ) {
        if ( 'preconnect' !== $relation_type ) return $urls;

        $urls[] = [ 'href' => 'https://fonts.googleapis.com' ];
        $urls[] = [ 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' ];

        return $urls;
    }

    public function shortcode_qr_notice( $atts ) {
        $this->enqueue_frontend_assets();
        $a = shortcode_atts( [ 'style' => 'inline' ], $atts );
        $text = $this->t( 'Bu bilgilere karekod ile ulaşabilirsiniz. Karekod kullanamayan tüketiciler talep ederse bilgi kendilerine ayrıca sunulur.' );
        if ( $a['style'] === 'banner' ) {
            return '<div class="rma-qr-notice rma-qr-notice-banner"><span class="rma-qr-notice-icon">📱</span><span>' . esc_html( $text ) . '</span></div>';
        }
        return '<p class="rma-qr-notice">' . esc_html( $text ) . '</p>';
    }

    public function shortcode_menu( $atts ) {
        // Yedek enqueue: wp_enqueue_scripts anında tespit edilemeyen
        // durumlar için (tema builder, arşiv, dinamik render).
        $this->enqueue_frontend_assets();

        $a           = shortcode_atts( [ 'show_search' => 'yes' ], $atts );
        $show_search = $a['show_search'] !== 'no';

        ob_start(); ?>
<div class="rma-wrap">

    <div class="rma-toolbar"<?php echo $show_search ? '' : ' style="justify-content:flex-end;"'; ?>>
        <?php if ( $show_search ) : ?>
        <div class="rma-search-wrap">
            <svg class="rma-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="rma-search" class="rma-search-input" placeholder="<?php echo esc_attr( $this->t( 'Ürün ara…' ) ); ?>" autocomplete="off" aria-label="<?php echo esc_attr( $this->t( 'Menüde ara' ) ); ?>">
        </div>
        <?php endif; ?>
        <button id="rma-filter-trigger" class="rma-filter-trigger" type="button" aria-haspopup="dialog" aria-label="<?php echo esc_attr( $this->t( 'Filtrele ve Sırala' ) ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            <?php echo esc_html( $this->t( 'Filtrele' ) ); ?>
            <span id="rma-filter-badge" class="rma-filter-badge" aria-live="polite">0</span>
        </button>
    </div>

    <div class="rma-nav-wrapper">
        <nav class="rma-nav" id="rma-nav" aria-label="<?php echo esc_attr( $this->t( 'Menü kategorileri' ) ); ?>" role="tablist"></nav>
    </div>

    <div class="rma-content" id="rma-content" role="main"></div>
    <div class="rma-loader" id="rma-loader" aria-live="polite" aria-label="<?php echo esc_attr( $this->t( 'Yükleniyor' ) ); ?>"><?php echo esc_html( $this->t( 'Menü yükleniyor' ) ); ?></div>

</div>

<div id="rma-panel-overlay" class="rma-panel-overlay" aria-hidden="true"></div>

<div id="rma-panel-sheet" class="rma-panel-sheet" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $this->t( 'Filtrele ve Sırala' ) ); ?>">
    <div class="rma-panel-header">
        <div class="rma-panel-title">
            <span class="rma-panel-title-dot"></span>
            <?php echo esc_html( $this->t( 'Filtrele & Sırala' ) ); ?>
        </div>
        <button id="rma-panel-close" class="rma-panel-close" aria-label="<?php echo esc_attr( $this->t( 'Kapat' ) ); ?>" type="button">&#x2715;</button>
    </div>

    <div class="rma-panel-body">
        <div class="rma-panel-section">
            <div class="rma-panel-section-label"><?php echo esc_html( $this->t( 'Sıralama' ) ); ?></div>
            <div class="rma-sort-pills">
                <?php
                // Sıralama seçenekleri tek dizide: metinler çeviriden tek noktadan geçsin.
                $sort_pills = [
                    ''           => [ '✦',  'Varsayılan',       'Önerilen sıra' ],
                    'az'         => [ '🔤', 'A → Z',            'Alfabetik' ],
                    'price_asc'  => [ '↑',  'Ucuzdan Pahalıya', 'Fiyat artan' ],
                    'price_desc' => [ '↓',  'Pahalıdan Ucuya',  'Fiyat azalan' ],
                    'protein'    => [ '💪', 'En Proteinli',     'Protein yüksek' ],
                    'carbs'      => [ '🌾', 'En Az Karb',       'Karbonhidrat azalan' ],
                ];
                foreach ( $sort_pills as $value => $pill ) :
                    list( $icon, $name, $sub ) = $pill;
                ?>
                <label class="rma-sort-pill<?php echo '' === $value ? ' selected' : ''; ?>" data-value="<?php echo esc_attr( $value ); ?>">
                    <input type="radio" name="rma_sort_panel" value="<?php echo esc_attr( $value ); ?>"<?php echo '' === $value ? ' checked' : ''; ?>>
                    <span class="rma-sort-pill-check">✓</span>
                    <span class="rma-sort-pill-icon"><?php echo $icon; ?></span>
                    <span class="rma-sort-pill-name"><?php echo esc_html( $this->t( $name ) ); ?></span>
                    <span class="rma-sort-pill-sub"><?php echo esc_html( $this->t( $sub ) ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rma-panel-section">
            <div class="rma-panel-section-label"><?php echo esc_html( $this->t( 'Diyet & Alerjen' ) ); ?></div>
            <div class="rma-filter-cards">
                <?php
                $diet_cards = [
                    'gluten_free' => [ '🌾', 'Glütensiz' ],
                    'vegetarian'  => [ '🥦', 'Vejetaryen' ],
                    'vegan'       => [ '🌿', 'Vegan' ],
                ];
                foreach ( $diet_cards as $value => $card ) :
                    list( $icon, $name ) = $card;
                ?>
                <label class="rma-filter-card" data-value="<?php echo esc_attr( $value ); ?>">
                    <input type="checkbox" value="<?php echo esc_attr( $value ); ?>">
                    <span class="rma-filter-card-tick">✓</span>
                    <span class="rma-filter-card-icon"><?php echo $icon; ?></span>
                    <span class="rma-filter-card-name"><?php echo esc_html( $this->t( $name ) ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rma-panel-section">
            <div class="rma-panel-section-label"><?php echo esc_html( $this->t( 'Alerjen Hariç Tut' ) ); ?></div>
            <div class="rma-filter-cards">
                <?php foreach ( $this->get_allergen_definitions() as $slug => $def ) : ?>
                <label class="rma-filter-card" data-value="allergen_<?php echo esc_attr( $slug ); ?>">
                    <input type="checkbox" value="allergen_<?php echo esc_attr( $slug ); ?>">
                    <span class="rma-filter-card-tick">✓</span>
                    <span class="rma-filter-card-icon"><?php echo $def['icon']; ?></span>
                    <span class="rma-filter-card-name"><?php echo esc_html( $this->t_allergen_label( $slug, $def['label'] ) ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="rma-panel-footer">
        <button id="rma-panel-reset" class="rma-panel-btn-reset" type="button"><?php echo esc_html( $this->t( 'Sıfırla' ) ); ?></button>
        <button id="rma-panel-apply" class="rma-panel-btn-apply" type="button"><?php echo esc_html( $this->t( 'Uygula' ) ); ?></button>
    </div>
</div>

<div id="rma-modal" class="rma-modal-overlay" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $this->t( 'Ürün detayı' ) ); ?>">
    <div class="rma-modal-box">
        <div class="rma-modal-handle"></div>
        <button class="rma-modal-close" aria-label="<?php echo esc_attr( $this->t( 'Kapat' ) ); ?>">&#x2715;</button>
        <div class="rma-modal-inner"></div>
    </div>
</div>
        <?php
        return ob_get_clean();
    }

    private function render_card( $id ) {
        $img   = get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: 'https://placehold.co/220x220/111111/c9a84c?text=%E2%97%86';
        $price = get_post_meta( $id, 'rma_price', true );

        // Ham post alanları kullanılıyor: CSV'deki title/excerpt/content
        // sütunlarıyla birebir aynı metin, dolayısıyla çeviri eşleşmesi kesin.
        $title = $this->t_field( $id, 'product', 'title', get_post_field( 'post_title', $id ) );

        $excerpt_raw = (string) get_post_field( 'post_excerpt', $id );
        if ( trim( $excerpt_raw ) !== '' ) {
            $desc = $this->t_field( $id, 'product', 'excerpt', $excerpt_raw );
        } else {
            // Özet boşken eski kod get_the_excerpt() ile içerikten otomatik
            // özet üretiyordu (varsayılan 55 kelime). Kart görünümü değişmesin
            // diye aynı uzunluk korunuyor.
            $content = $this->t_field( $id, 'product', 'content', (string) get_post_field( 'post_content', $id ) );
            $desc    = trim( $content ) !== ''
                ? wp_trim_words( strip_shortcodes( $content ), 55, '…' )
                : '';
        }

        $badges = '';
        if ( get_post_meta( $id, 'rma_badge_recommended', true ) === '1' ) $badges .= '<span class="rma-badge recommended">' . esc_html( $this->t( 'Önerilen' ) ) . '</span>';
        if ( get_post_meta( $id, 'rma_badge_popular',     true ) === '1' ) $badges .= '<span class="rma-badge popular">' . esc_html( $this->t( 'Popüler' ) ) . '</span>';
        if ( get_post_meta( $id, 'rma_badge_new',         true ) === '1' ) $badges .= '<span class="rma-badge new">' . esc_html( $this->t( 'Yeni' ) ) . '</span>';
        if ( get_post_meta( $id, 'rma_badge_discount',    true ) === '1' ) $badges .= '<span class="rma-badge discount">%</span>';

        $prep_time = get_post_meta( $id, 'rma_prep_time', true );
        $prep_html = '';
        if ( $prep_time !== '' && (int) $prep_time > 0 ) {
            $prep_html = '<span class="rma-card-prep" aria-label="' . esc_attr( $this->t( 'Hazırlanış süresi' ) ) . '"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>' . esc_html( (int) $prep_time ) . ' ' . esc_html( $this->t( 'dk' ) ) . '</span>';
        }

        $price_inner = '<span class="rma-card-price">' . esc_html( $price ) . ' ₺</span>';
        if ( function_exists( 'qmo_get_kombin_price_html' ) ) {
            $kombin_html = qmo_get_kombin_price_html( $id );
            if ( $kombin_html !== '' ) {
                $price_inner = $kombin_html;
            }
        }

        return sprintf(
            '<div class="rma-card" data-id="%d" tabindex="0" role="button" aria-label="%s">
                <div class="rma-card-img-wrap">
                    <div class="rma-card-badges">%s</div>
                    <img src="%s" class="rma-card-img" alt="%s" loading="lazy" decoding="async" width="220" height="220">
                </div>
                <div class="rma-card-body">
                    <h3 class="rma-card-title">%s</h3>
                    <p class="rma-card-desc">%s</p>
                    <div class="rma-card-footer">
                        <div class="rma-card-price-group">
                            %s
                            %s
                        </div>
                        <span class="rma-card-arrow" aria-hidden="true">&#8250;</span>
                    </div>
                </div>
            </div>',
            $id,
            esc_attr( $title ),
            $badges,
            esc_url( $img ),
            esc_attr( $title ),
            esc_html( $title ),
            esc_html( $desc ),
            $price_inner,
            $prep_html
        );
    }
}
