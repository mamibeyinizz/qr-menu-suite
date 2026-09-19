<?php
/**
 * Öne Çıkan Slider — yönetim ekranı ve paylaşılan form satırı yardımcıları.
 *
 * Kaydetme AJAX değil, `admin_post_` + nonce + redirect ile yapılır
 * (modüldeki `admin_post_rma_export_menu` deseninin aynısı).
 *
 * Buradaki `rma_font_size_row()` / `rma_weight_row()` / `rma_align_row()`
 * satırları yalnızca slider'a ait DEĞİLDİR: Kampanya Görselleri sihirbazı da
 * aynı satırları kullanır (bkz. trait-kampanya-banner-admin.php).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Slider_Admin_Trait {

    /** Öne çıkan slider görünüm ayarlarının nonce eylemi. */
    private $slider_nonce_action = 'qmo_slider_kaydet';

    /**
     * Px slider satırı.
     *
     * Slider + değer kutusu ikilisi .rma-range-row desenidir (oninput ile
     * anlık px yazımı); canlı önizleme ayrıca admin-ui.js tarafından
     * bağlanır.
     *
     * @param string $id      Alan id'si (önizleme JS'i bunu okur).
     * @param string $name    Form alanı adı = veritabanı sütunu.
     * @param int    $deger   Mevcut değer.
     * @param int    $min     Alt sınır (px).
     * @param int    $max     Üst sınır (px).
     * @param string $etiket  Satır başlığı.
     * @param string $aciklama Slider altındaki açıklama.
     * @return void
     */
    private function rma_font_size_row( $id, $name, $deger, $min, $max, $etiket, $aciklama ) {
        ?>
        <tr>
            <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label></th>
            <td>
                <div class="rma-range-row">
                    <input type="range" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
                           min="<?php echo (int) $min; ?>"
                           max="<?php echo (int) $max; ?>"
                           step="1"
                           value="<?php echo (int) $deger; ?>"
                           oninput="this.nextElementSibling.textContent=this.value+'px'">
                    <span class="rma-range-val"><?php echo (int) $deger; ?>px</span>
                </div>
                <p class="description rma-desc"><?php echo esc_html( $aciklama ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Font kalınlığı açılır listesi.
     *
     * @param string $id       Alan id'si.
     * @param string $name     Form alanı adı = veritabanı sütunu.
     * @param int    $deger    Mevcut değer.
     * @param string $etiket   Satır başlığı.
     * @param string $aciklama Alan altındaki açıklama.
     * @return void
     */
    private function rma_weight_row( $id, $name, $deger, $etiket, $aciklama ) {
        $secenekler = array(
            400 => '400 — Normal',
            500 => '500 — Medium',
            600 => '600 — Semibold',
            700 => '700 — Bold',
        );
        ?>
        <tr>
            <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiket ); ?></label></th>
            <td>
                <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="rma-select-narrow">
                    <?php foreach ( $secenekler as $kalinlik => $kalinlik_etiket ) : ?>
                        <option value="<?php echo (int) $kalinlik; ?>" <?php selected( (int) $deger, $kalinlik ); ?>><?php echo esc_html( $kalinlik_etiket ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description rma-desc"><?php echo esc_html( $aciklama ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Sol/Orta/Sağ hizalama buton grubu.
     *
     * Görsel dil "Renk seçin" düğmeleriyle aynı (.rma-align-btn seçili
     * hâlde altın vurgu alır); erişilebilirlik için altta gerçek radio
     * girdileri durur, böylece klavye ve ekran okuyucu doğal çalışır ve
     * form gönderimi JS'siz de doğru değeri taşır.
     *
     * @param string $id       Grubun id ön eki (önizleme JS'i bunu okur).
     * @param string $name     Form alanı adı = veritabanı sütunu.
     * @param string $deger    Mevcut değer (left|center|right).
     * @param string $etiket   Satır başlığı.
     * @param string $aciklama Grup altındaki açıklama.
     * @return void
     */
    private function rma_align_row( $id, $name, $deger, $etiket, $aciklama ) {
        $deger = strtolower( trim( (string) $deger ) );
        $deger = in_array( $deger, array( 'left', 'center', 'right' ), true ) ? $deger : 'left';

        // İkonlar: üç çizgi, hizaya göre kaydırılmış (WordPress'in kendi
        // hizalama düğmelerinin sadeleştirilmiş hâli).
        $secenekler = array(
            'left'   => array( 'Sol', array( 0, 0, 0 ) ),
            'center' => array( 'Orta', array( 0, 3, 1.5 ) ),
            'right'  => array( 'Sağ', array( 0, 6, 3 ) ),
        );
        ?>
        <tr>
            <th><span class="rma-align-label"><?php echo esc_html( $etiket ); ?></span></th>
            <td>
                <div class="rma-align-group" role="radiogroup" aria-label="<?php echo esc_attr( $etiket ); ?>">
                    <?php foreach ( $secenekler as $hiza => $secenek ) :
                        list( $hiza_etiket, $ofset ) = $secenek;
                        $secili = $hiza === $deger;
                        ?>
                        <label class="rma-align-btn<?php echo $secili ? ' is-selected' : ''; ?>">
                            <input type="radio" name="<?php echo esc_attr( $name ); ?>"
                                   id="<?php echo esc_attr( $id . '-' . $hiza ); ?>"
                                   class="rma-align-input"
                                   value="<?php echo esc_attr( $hiza ); ?>"
                                   data-align-field="<?php echo esc_attr( $id ); ?>"
                                   <?php checked( $secili ); ?>>
                            <svg class="rma-align-ic" viewBox="0 0 16 12" width="16" height="12" aria-hidden="true" focusable="false">
                                <rect x="<?php echo esc_attr( (string) $ofset[0] ); ?>" y="1" width="16" height="2" rx="1"></rect>
                                <rect x="<?php echo esc_attr( (string) $ofset[1] ); ?>" y="5" width="10" height="2" rx="1"></rect>
                                <rect x="<?php echo esc_attr( (string) $ofset[2] ); ?>" y="9" width="13" height="2" rx="1"></rect>
                            </svg>
                            <span><?php echo esc_html( $hiza_etiket ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description rma-desc"><?php echo esc_html( $aciklama ); ?></p>
            </td>
        </tr>
        <?php
    }

    /* -----------------------------------------------------------------
       ÖNE ÇIKAN SLIDER — GÖRÜNÜM AYARLARI
       Ok navigasyonu ve slide başlığı. wp_options'ta saklanır
       (bkz. QMO_Slider_Settings).
    ----------------------------------------------------------------- */

    /**
     * `admin_post_qmo_slider_kaydet` — slider görünümünü kaydeder.
     *
     * @return void
     */
    public function handle_slider_settings_save() {
        check_admin_referer( $this->slider_nonce_action );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
        }

        $ham = array();

        if ( isset( $_POST['qmo_slider_settings'] ) && is_array( $_POST['qmo_slider_settings'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- QMO_Slider_Settings::sanitize temizler.
            $ham = wp_unslash( $_POST['qmo_slider_settings'] );
        }

        QMO_Slider_Settings::kaydet( $ham );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'       => 'qrms-rm-one-cikanlar',
                    'slider_msg' => 'kaydedildi',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Slider görünüm kaydı sonrası bildirimi basar.
     *
     * @return void
     */
    private function slider_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $durum = isset( $_GET['slider_msg'] ) ? sanitize_key( wp_unslash( $_GET['slider_msg'] ) ) : '';

        if ( 'kaydedildi' !== $durum ) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>Slider görünümü kaydedildi.</p></div>';
    }

    /**
     * Öne Çıkan Slider görünüm sihirbazı — oklar ve slide başlığı.
     *
     * Canlı önizleme frontend-slider.css'in kendisini kullanır; JS
     * (--qmo-slider-title-*) değişkenlerini anında yazar.
     *
     * @return void
     */
    public function render_slider_appearance_form() {
        if ( ! class_exists( 'QMO_Slider_Settings' ) ) {
            return;
        }

        $this->slider_notice();

        $ayar = QMO_Slider_Settings::get();
        $stil = QMO_Slider_Settings::css_degiskenleri( $ayar );

        $adimlar = array(
            1 => array( 'Oklar', 'Ok Navigasyonu' ),
            2 => array( 'Başlık', 'Slide Başlığı' ),
        );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="qmo-slider-form">
            <?php wp_nonce_field( $this->slider_nonce_action ); ?>
            <input type="hidden" name="action" value="qmo_slider_kaydet">

            <h2 class="rma-card-title" id="qmo-slider-gorunum">Slider Görünümü</h2>
            <p class="rma-card-desc">Öne çıkan slaytın okları ve başlığı. Kaydettikten sonra <code>[qmo_one_cikan_slider]</code> kısa koduna yansır.</p>

            <div class="rma-vitrin-steps" id="qmo-slider-steps" role="tablist" aria-label="Slider görünüm adımları">
                <?php foreach ( $adimlar as $adim_no => $adim ) : ?>
                    <button type="button" class="rma-vitrin-step-btn<?php echo 1 === $adim_no ? ' is-active' : ''; ?>"
                            data-step-target="<?php echo (int) $adim_no; ?>"
                            role="tab" aria-selected="<?php echo 1 === $adim_no ? 'true' : 'false'; ?>">
                        <span class="rma-vitrin-step-num"><?php echo (int) $adim_no; ?></span>
                        <span class="rma-vitrin-step-label"><?php echo esc_html( $adim[0] ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="rma-vitrin-step-compact" id="qmo-slider-step-compact">Adım 1/<?php echo (int) count( $adimlar ); ?>: <?php echo esc_html( $adimlar[1][1] ); ?></p>

            <div class="rma-vitrin-layout-wrap">
                <div class="rma-vitrin-layout-fields">
                    <div class="rma-card rma-vitrin-step" data-step="1" data-step-title="Ok Navigasyonu">
                        <h2 class="rma-card-title">1. Ok Navigasyonu</h2>
                        <p class="rma-card-desc">Slaytlar arasında geçiş yapan önceki/sonraki okları gösterin veya gizleyin. Tek slayt varken oklar zaten basılmaz.</p>

                        <table class="form-table rma-form-table">
                            <tr>
                                <th>Oklar</th>
                                <td>
                                    <input type="hidden" name="qmo_slider_settings[show_nav]" value="0">
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="qmo_slider_settings[show_nav]" id="qmo-slider-show-nav" value="1" <?php checked( 1, $ayar['show_nav'] ); ?>>
                                        <span>Ok navigasyonunu göster</span>
                                    </label>
                                    <p class="description rma-desc">Kapalıysa önceki/sonraki butonları slaytta hiç yer almaz. Varsayılan: açık.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-card rma-vitrin-step" data-step="2" data-step-title="Slide Başlığı" style="display:none;">
                        <h2 class="rma-card-title">2. Slide Başlığı</h2>
                        <p class="rma-card-desc">Her slaytın üstündeki başlık (slide adı). Yazı tipi ve renk tüm cihazlarda ortaktır; punto masaüstü ve mobil için ayrı ayarlanır.</p>

                        <table class="form-table rma-form-table">
                            <tr>
                                <th>Başlık</th>
                                <td>
                                    <input type="hidden" name="qmo_slider_settings[show_title]" value="0">
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="qmo_slider_settings[show_title]" id="qmo-slider-show-title" value="1" <?php checked( 1, $ayar['show_title'] ); ?>>
                                        <span>Slide başlığını göster</span>
                                    </label>
                                    <p class="description rma-desc">Kapalıysa slayt başlığı basılmaz. Kısa kod niteliği <code>show_title="no"</code> hâlâ ezer.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qmo-slider-title-font">Font ailesi</label></th>
                                <td>
                                    <select name="qmo_slider_settings[title_font]" id="qmo-slider-title-font" class="rma-select-wide">
                                        <?php foreach ( QMO_Slider_Settings::yazi_tipleri() as $font_anahtar => $font_bilgi ) : ?>
                                            <option value="<?php echo esc_attr( $font_anahtar ); ?>" <?php selected( $ayar['title_font'], $font_anahtar ); ?>><?php echo esc_html( $font_bilgi['etiket'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qmo-slider-title-color">Font rengi</label></th>
                                <td>
                                    <input type="text" name="qmo_slider_settings[title_color]" id="qmo-slider-title-color"
                                           value="<?php echo esc_attr( $ayar['title_color'] ); ?>"
                                           class="qmo-slider-color-picker"
                                           data-default-color="<?php echo esc_attr( QMO_Slider_Settings::varsayilanlar()['title_color'] ); ?>">
                                </td>
                            </tr>
                        </table>

                        <h3 class="rma-section-title">Masaüstü</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->rma_font_size_row(
                                'qmo-slider-title-size',
                                'qmo_slider_settings[title_size]',
                                (int) $ayar['title_size'],
                                QMO_Slider_Settings::MIN_TITLE_SIZE,
                                QMO_Slider_Settings::MAX_TITLE_SIZE,
                                'Font boyutu',
                                'Masaüstünde slayt başlığının punto değeri.'
                            );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Mobil</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->rma_font_size_row(
                                'qmo-slider-title-size-mobile',
                                'qmo_slider_settings[title_size_mobile]',
                                (int) $ayar['title_size_mobile'],
                                QMO_Slider_Settings::MIN_TITLE_SIZE_MOBILE,
                                QMO_Slider_Settings::MAX_TITLE_SIZE_MOBILE,
                                'Font boyutu',
                                'Dar ekranda başlık bu puntoya düşer.'
                            );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Kalınlık ve hizalama</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->rma_weight_row(
                                'qmo-slider-title-weight',
                                'qmo_slider_settings[title_weight]',
                                (int) $ayar['title_weight'],
                                'Font kalınlığı',
                                '400 sakin, 600 mevcut varsayılan, 700 daha vurgulu.'
                            );
                            $this->rma_align_row(
                                'qmo-slider-title-align',
                                'qmo_slider_settings[title_align]',
                                (string) $ayar['title_align'],
                                'Hizalama',
                                'Slayt başlığının yatay yaslanması.'
                            );
                            ?>
                        </table>
                    </div>

                    <div class="rma-vitrin-step-nav" id="qmo-slider-step-nav">
                        <button type="button" class="button rma-vitrin-step-prev" disabled>&larr; Geri Dön</button>
                        <button type="button" class="button button-primary rma-vitrin-step-next">Devam Et &rarr;</button>
                        <button type="submit" class="button button-primary rma-vitrin-step-submit" style="display:none;">Ayarları Kaydet</button>
                    </div>
                </div>

                <div class="rma-vitrin-layout-preview">
                    <div class="rma-card rma-vitrin-preview-card">
                        <h2 class="rma-card-title">Canlı Önizleme</h2>
                        <p class="rma-card-desc">Soldaki her değişiklik anında yansır. Kaydetmeden önce okları ve başlığı kontrol edin.</p>

                        <div class="rma-vitrin-preview-toggle">
                            <button type="button" class="button rma-vitrin-preview-btn is-active" data-preview-mode="desktop">Masaüstü Önizleme</button>
                            <button type="button" class="button rma-vitrin-preview-btn" data-preview-mode="mobile">Mobil Önizleme</button>
                        </div>

                        <div class="rma-vitrin-preview-stage" id="qmo-slider-preview-stage">
                            <div class="qmo-slider-root<?php echo $ayar['show_title'] ? '' : ' is-title-hidden'; ?><?php echo $ayar['show_nav'] ? '' : ' is-nav-hidden'; ?>"
                                 id="qmo-slider-preview"
                                 data-qmo-slider
                                 style="<?php echo esc_attr( $stil ); ?>">
                                <div class="qmo-slider-viewport">
                                    <div class="qmo-slider-track">
                                        <div class="qmo-slider-slide is-active">
                                            <h2 class="qmo-slider-title">Şefin Önerileri</h2>
                                            <div class="qmo-slider-grid">
                                                <?php for ( $i = 0; $i < 4; $i++ ) : ?>
                                                    <article class="qmo-slider-product">
                                                        <div class="qmo-slider-product-img-wrap">
                                                            <span class="qmo-slider-product-img qmo-slider-preview-empty" aria-hidden="true">◆</span>
                                                        </div>
                                                        <div class="qmo-slider-product-body">
                                                            <h3 class="qmo-slider-product-title">Ürün Adı</h3>
                                                            <p class="qmo-slider-product-price"><span class="qmo-kombin-new-price">₺0,00</span></p>
                                                        </div>
                                                    </article>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="qmo-slider-nav" aria-hidden="true">
                                    <button type="button" class="qmo-slider-nav-btn qmo-slider-nav-prev" tabindex="-1">&#8249;</button>
                                    <button type="button" class="qmo-slider-nav-btn qmo-slider-nav-next" tabindex="-1">&#8250;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }
}
