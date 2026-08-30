<?php
/**
 * Kampanya Banner — yönetim sihirbazı ("Menü Görünümü" sayfasının bölümü).
 *
 * KAVRAM AYRIMI (ÖNEMLİ):
 *   "Kampanya"        = sayfa başındaki banner GÖRSELLERİ (qmo_banner_slide).
 *   "Fiyat Kampanyası" = menüdeki fiyatlara toplu zam/indirim (RMA_Kampanya_DB).
 * İkisi ayrı ekranlardır ve birbirine karışmaz; bu dosya YALNIZCA birincisiyle
 * ilgilenir. Fiyat tarafı trait-kampanya-admin.php'de, dokunulmadan durur.
 *
 * NEDEN AYRI DOSYA: banner yönetimi önceden iki yere dağılmıştı — görsel CRUD
 * bölümü trait-kampanya-admin.php içinde (fiyat kampanyası ekranının altında),
 * görünüm ayarları ise ayrı bir sayfada (qrms-rm-banner-ayar). Her ikisi de
 * buraya toplandı. Modülün mevcut deseni "konuya özel trait" olduğu için
 * (trait-vitrin-admin.php, trait-kampanya-admin.php, urunum-yok/trait-admin.php)
 * bu iş de kendi trait'ine alındı: trait-admin-pages.php zaten ~1000 satır ve
 * modülün TÜM sayfa iskeletini taşıyor, banner'ın ~500 satırı orayı okunmaz
 * hâle getirirdi. Sayfaya tek bir giriş noktasından bağlanır:
 * render_appearance_page() -> render_banner_wizard_section().
 *
 * VERİ KATMANI DEĞİŞMEDİ: CPT slug'ı (qmo_banner_slide), meta anahtarları
 * (_qmo_banner_gorsel_id, _qmo_banner_link), option adı
 * (qmo_banner_slider_settings) ve admin_post eylemi (qmo_banner_ayar_kaydet)
 * aynen korunur. Değişen tek şey admin arayüzünün NEREDE render edildiğidir.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Kampanya_Banner_Admin_Trait {

    /** Görünüm formunun nonce eylemi (= admin_post eylemi). */
    private $banner_nonce_action = 'qmo_banner_ayar_kaydet';

    /** Toplu görsel oluşturma AJAX ucunun nonce eylemi (= wp_ajax eylemi). */
    private $banner_olustur_nonce_action = 'qmo_banner_gorsel_olustur';

    /*
     * Sabitler `const` değil metottur: trait sabitleri PHP 8.2 ile geldi,
     * eklentinin alt sınırı ise PHP 7.4 (bkz. qr-menu-suite.php başlığı).
     */

    /**
     * Sihirbaz bölümünün sayfa içi çapası.
     *
     * @return string
     */
    public static function banner_anchor() {
        return 'rma-kampanya-banner';
    }

    /**
     * Üretilen görselin uzun kenarı (px); oranla birlikte yüksekliği belirler.
     *
     * @return int
     */
    private static function banner_uretim_genislik() {
        return 1600;
    }

    /**
     * Medya kütüphanesine yazılacak azami ham veri (byte).
     *
     * @return int
     */
    private static function banner_uretim_max_byte() {
        return 4194304;
    }

    /* -----------------------------------------------------------------
       SİHİRBAZ İSKELETİ
    ----------------------------------------------------------------- */

    /**
     * Sihirbazın adımları — TEK KAYNAK (şerit, başlıklar ve URL doğrulaması).
     *
     * @return array<string,array{no:int,etiket:string,baslik:string}>
     */
    private function banner_adimlari() {
        return array(
            'ozet'        => array( 'no' => 1, 'etiket' => 'Kampanya Banner', 'baslik' => 'Genel Bakış' ),
            'kampanyalar' => array( 'no' => 2, 'etiket' => 'Kampanyalar',     'baslik' => 'Kampanyalar ve Banner Ayarları' ),
            'olustur'     => array( 'no' => 3, 'etiket' => 'Görsel Oluştur',  'baslik' => 'Toplu Kampanya Görseli Oluştur' ),
        );
    }

    /**
     * Adres çubuğundaki geçerli adım (yoksa 1. adım).
     *
     * @return string
     */
    private function banner_adim() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $adim = isset( $_GET['banner_adim'] ) ? sanitize_key( wp_unslash( $_GET['banner_adim'] ) ) : '';

        return array_key_exists( $adim, $this->banner_adimlari() ) ? $adim : 'ozet';
    }

    /**
     * Bir sihirbaz adımının adresi.
     *
     * Adımlar JS ile gizlenen kartlar değil, gerçek sayfa yüklemeleridir:
     * 2. adımda admin_post'a giden bir ayar formu, 3. adımda AJAX ile
     * çalışan bir araç var. Böylece her adım yer imlenebilir ve kaydetme
     * sonrası doğru adıma dönülebilir.
     *
     * @param string $adim Adım anahtarı.
     * @param array  $args Ek query arg'ları.
     * @return string
     */
    private function banner_wizard_url( $adim = 'ozet', array $args = array() ) {
        return $this->admin_page_url(
            'qrms-rm-gorunum',
            array_merge( array( 'banner_adim' => $adim ), $args ),
            self::banner_anchor()
        );
    }

    /**
     * "Menü Görünümü" sayfasının Kampanya Banner bölümü.
     *
     * render_appearance_page() bunu renk/yazı tipi/kategori çubuğu
     * bölümlerinin ALTINDA çağırır; o bölümlerin hiçbirine dokunmaz.
     *
     * @return void
     */
    public function render_banner_wizard_section() {
        if ( ! post_type_exists( QMO_Banner_CPT::POST_TYPE ) || ! class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            return;
        }

        $adim     = $this->banner_adim();
        $adimlar  = $this->banner_adimlari();
        $toplam   = count( $adimlar );
        ?>
        <div class="rma-kb-wizard" id="<?php echo esc_attr( self::banner_anchor() ); ?>">
            <h2 class="rma-kb-heading">Kampanya Banner</h2>
            <p class="rma-card-desc">Sayfanın en üstünde tam genişlikte dönen kampanya görselleri. Görsellerin kendisi, görünüm ayarları ve hazır görsel üretme aracı bu üç adımda toplandı.</p>

            <?php $this->banner_notice(); ?>

            <nav class="rma-vitrin-steps" aria-label="Kampanya Banner adımları">
                <?php foreach ( $adimlar as $anahtar => $bilgi ) : ?>
                    <a class="rma-vitrin-step-btn<?php echo $anahtar === $adim ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url( $this->banner_wizard_url( $anahtar ) ); ?>"
                       <?php echo $anahtar === $adim ? 'aria-current="page"' : ''; ?>>
                        <span class="rma-vitrin-step-num"><?php echo (int) $bilgi['no']; ?></span>
                        <span class="rma-vitrin-step-label"><?php echo esc_html( $bilgi['etiket'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <p class="rma-vitrin-step-compact">Adım <?php echo (int) $adimlar[ $adim ]['no'] . '/' . (int) $toplam; ?>: <?php echo esc_html( $adimlar[ $adim ]['baslik'] ); ?></p>

            <?php
            if ( 'kampanyalar' === $adim ) {
                $this->render_banner_adim_kampanyalar();
            } elseif ( 'olustur' === $adim ) {
                $this->render_banner_adim_olustur();
            } else {
                $this->render_banner_adim_ozet();
            }
            ?>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------
       ADIM 1 — GENEL BAKIŞ
    ----------------------------------------------------------------- */

    /**
     * 1. adım: yayındaki banner'ın özeti, önizlemesi ve diğer adımlara
     * giden kartlar.
     *
     * @return void
     */
    private function render_banner_adim_ozet() {
        $banners  = QMO_Banner_CPT::get_published_banners();
        $toplam   = count( $banners );
        $gorselli = 0;

        foreach ( $banners as $banner ) {
            if ( (int) get_post_meta( $banner->ID, QMO_Banner_CPT::META_IMAGE, true ) ) {
                $gorselli++;
            }
        }

        $ayar      = QMO_Banner_Slider_Settings::get();
        $oranlar   = QMO_Banner_Slider_Settings::oranlar();
        $oran_adi  = isset( $oranlar[ $ayar['oran'] ] ) ? $oranlar[ $ayar['oran'] ]['etiket'] : $ayar['oran'];
        $onizleme  = $this->banner_onizleme_gorseli();
        $shortcode = '[qmo_banner_slider]';
        ?>
        <div class="rma-card">
            <h3 class="rma-card-title">Banner şu an ne durumda?</h3>

            <?php if ( 0 === $gorselli ) : ?>
                <p class="rma-empty">Görseli olan yayında kampanya yok — <code><?php echo esc_html( $shortcode ); ?></code> kısa kodu şu an hiçbir şey basmıyor.</p>
            <?php else : ?>
                <p class="rma-card-desc"><strong><?php echo (int) $gorselli; ?></strong> kampanya yayında<?php echo $toplam > $gorselli ? ' (' . (int) ( $toplam - $gorselli ) . ' kampanyanın görseli seçilmemiş, onlar basılmaz)' : ''; ?>.</p>
            <?php endif; ?>

            <ul class="rma-kb-ozet">
                <li><span class="rma-kb-ozet-etiket">Toplam kampanya</span><span class="rma-kb-ozet-deger"><?php echo (int) $toplam; ?></span></li>
                <li><span class="rma-kb-ozet-etiket">Gösterimde</span><span class="rma-kb-ozet-deger"><?php echo (int) $gorselli; ?></span></li>
                <li><span class="rma-kb-ozet-etiket">En-boy oranı</span><span class="rma-kb-ozet-deger"><?php echo esc_html( $oran_adi ); ?></span></li>
                <li><span class="rma-kb-ozet-etiket">Otomatik geçiş</span><span class="rma-kb-ozet-deger"><?php echo $ayar['autoplay'] ? esc_html( number_format_i18n( $ayar['autoplay'] / 1000, 1 ) . ' sn' ) : 'Kapalı'; ?></span></li>
            </ul>

            <?php if ( '' !== $onizleme ) : ?>
                <div class="rma-kb-onizleme" style="aspect-ratio:<?php echo esc_attr( isset( $oranlar[ $ayar['oran'] ] ) ? $oranlar[ $ayar['oran'] ]['css'] : '16 / 9' ); ?>;">
                    <img src="<?php echo esc_url( $onizleme ); ?>" alt="">
                </div>
            <?php endif; ?>

            <p class="rma-card-desc">Banner'ı göstermek istediğiniz sayfaya bu kısa kodu ekleyin:</p>
            <div class="rma-shortcode-row">
                <input type="text" class="rma-shortcode-input" readonly value="<?php echo esc_attr( $shortcode ); ?>">
                <button type="button" class="button rma-copy-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>">Kopyala</button>
            </div>
            <p class="description rma-desc">Kısa koda <code>autoplay="0"</code> yazılırsa o sayfada otomatik geçiş kapanır.</p>
        </div>

        <div class="rma-kb-nav-grid">
            <a class="rma-kb-nav-card" href="<?php echo esc_url( $this->banner_wizard_url( 'kampanyalar' ) ); ?>">
                <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                <span class="rma-kb-nav-title">Kampanyalar</span>
                <span class="rma-kb-nav-desc">Kampanya görsellerini ekleyin, sırasını ve bağlantısını düzenleyin; banner'ın oranını, geçişini, oklarını ve başlığını ayarlayın.</span>
                <span class="rma-kb-nav-git">2. adıma git &rarr;</span>
            </a>
            <a class="rma-kb-nav-card" href="<?php echo esc_url( $this->banner_wizard_url( 'olustur' ) ); ?>">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                <span class="rma-kb-nav-title">Toplu Kampanya Görseli Oluştur</span>
                <span class="rma-kb-nav-desc">Hazır şablonla, tarayıcıda tek tıkla kampanya görseli üretin; üretilen görsel doğrudan yeni bir kampanya olarak kaydedilir.</span>
                <span class="rma-kb-nav-git">3. adıma git &rarr;</span>
            </a>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------
       ADIM 2 — KAMPANYALAR (LİSTE + GÖRÜNÜM AYARLARI)
    ----------------------------------------------------------------- */

    /**
     * 2. adım: kampanya (banner) listesi + görünüm ayarları formu.
     *
     * İki parça da eskiden ayrı yerlerdeydi (liste Fiyat Kampanyaları
     * sayfasında, ayarlar qrms-rm-banner-ayar sayfasında); ikisi de olduğu
     * gibi buraya taşındı. Hiçbir alan düşmedi.
     *
     * @return void
     */
    private function render_banner_adim_kampanyalar() {
        $this->render_banner_kampanya_listesi();
        $this->render_banner_ayar_formu();
    }

    /**
     * Aktif kampanyalar listesi — eski render_banner_section()'ın aynısı.
     *
     * CPT'nin menü kaydı üst menü olmayan bir slug'a bağlı olduğu için sol
     * menüde görünmez; erişim bu bölümden verilir. Metinlerde "banner"
     * yerine "kampanya" denir (yeni isimlendirme), ama CPT slug'ı, meta
     * anahtarları ve bağlantı adresleri BİREBİR aynıdır.
     *
     * @return void
     */
    private function render_banner_kampanya_listesi() {
        $banners = QMO_Banner_CPT::get_published_banners();
        ?>
        <div class="rma-card" id="rma-banner">
            <div class="rma-vitrin-list-head">
                <h3 class="rma-card-title">Aktif Kampanyalar</h3>
                <a class="button" href="<?php echo esc_url( $this->banner_wizard_url( 'olustur' ) ); ?>">Hazır şablonla görsel üret</a>
            </div>
            <p class="rma-card-desc">Sayfanın en üstünde tam genişlikte dönen kampanya görselleri. Sayfaya <code>[qmo_banner_slider]</code> kısa koduyla eklenir. Görseli seçilmemiş kayıtlar gösterilmez.</p>

            <?php if ( empty( $banners ) ) : ?>
                <p class="rma-empty">Henüz kampanya eklenmemiş.</p>
            <?php else : ?>
                <ul class="rma-simple-list">
                    <?php foreach ( $banners as $banner ) :
                        $gorsel_id = (int) get_post_meta( $banner->ID, QMO_Banner_CPT::META_IMAGE, true );
                        $link      = (string) get_post_meta( $banner->ID, QMO_Banner_CPT::META_LINK, true );
                        $edit_link = get_edit_post_link( $banner->ID );
                        ?>
                        <li class="rma-simple-item">
                            <span class="rma-simple-main">
                                <?php if ( $edit_link ) : ?>
                                    <a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $banner->post_title ?: 'Başlıksız kampanya' ); ?></a>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $banner->post_title ?: 'Başlıksız kampanya' ); ?></strong>
                                <?php endif; ?>
                                <span class="rma-simple-sub">
                                    <?php
                                    echo esc_html( $gorsel_id ? 'Görsel seçili' : 'Görsel seçilmemiş — bu kampanya gösterilmez' );
                                    echo $link ? ' · ' . esc_html( $link ) : '';
                                    ?>
                                </span>
                            </span>
                            <span class="rma-simple-meta">Sıra: <?php echo (int) $banner->menu_order; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="rma-actions">
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . QMO_Banner_CPT::POST_TYPE ) ); ?>">Yeni Kampanya Ekle</a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . QMO_Banner_CPT::POST_TYPE ) ); ?>">Tüm Kampanyaları Yönet</a>
            </p>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------
       KAMPANYA BANNER — GÖRÜNÜM AYARLARI

       Oran, geçiş biçimi, otomatik geçiş, oklar/noktalar ve banner
       başlığı. wp_options'ta saklanır (bkz. QMO_Banner_Slider_Settings).
       Form, Öne Çıkan Slider görünüm formuyla aynı yapıyı kullanır; üç
       alt sekmesi (Biçim / Gezinme / Başlık) admin-ui.js'teki ortak
       initFormStepper() ile sürülür, canlı önizleme ön yüzün GERÇEK
       frontend-banner-slider.css'ini kullanır.
    ----------------------------------------------------------------- */

    /**
     * `admin_post_qmo_banner_ayar_kaydet` — banner görünümünü kaydeder.
     *
     * @return void
     */
    public function handle_banner_settings_save() {
        check_admin_referer( $this->banner_nonce_action );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
        }

        $ham = array();

        if ( isset( $_POST['qmo_banner_slider_settings'] ) && is_array( $_POST['qmo_banner_slider_settings'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- QMO_Banner_Slider_Settings::sanitize temizler.
            $ham = wp_unslash( $_POST['qmo_banner_slider_settings'] );
        }

        QMO_Banner_Slider_Settings::kaydet( $ham );

        // Ayar formu artık Menü Görünümü sayfasının 2. adımındadır; kaydeden
        // kullanıcı geldiği yere döner.
        wp_safe_redirect( $this->banner_wizard_url( 'kampanyalar', array( 'banner_msg' => 'kaydedildi' ) ) );
        exit;
    }

    /**
     * Banner işlemleri sonrası bildirimi basar.
     *
     * @return void
     */
    private function banner_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $durum = isset( $_GET['banner_msg'] ) ? sanitize_key( wp_unslash( $_GET['banner_msg'] ) ) : '';

        $mesajlar = array(
            'kaydedildi' => 'Banner görünümü kaydedildi.',
        );

        if ( ! isset( $mesajlar[ $durum ] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html( $mesajlar[ $durum ] )
        );
    }

    /**
     * Önizlemede kullanılacak gerçek banner görseli (varsa).
     *
     * Yayınlanmış ilk banner'ın görseli kullanılır; hiç yoksa boş dize
     * döner ve önizleme yer tutucuya düşer.
     *
     * @return string
     */
    private function banner_onizleme_gorseli() {
        if ( ! class_exists( 'QMO_Banner_CPT' ) ) {
            return '';
        }

        foreach ( QMO_Banner_CPT::get_published_banners() as $banner ) {
            $gorsel_id = (int) get_post_meta( $banner->ID, QMO_Banner_CPT::META_IMAGE, true );

            if ( ! $gorsel_id ) {
                continue;
            }

            $url = wp_get_attachment_image_url( $gorsel_id, 'large' );

            if ( $url ) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Kampanya Banner görünüm formu — oran, geçiş, oklar ve başlık.
     *
     * Eski render_banner_settings_page() gövdesinin aynısı; yalnızca sayfa
     * başlığı/iskeleti (page_header/page_footer) düştü, çünkü artık ayrı
     * bir sayfa değil Menü Görünümü'nün bir bölümü.
     *
     * @return void
     */
    private function render_banner_ayar_formu() {
        $ayar = QMO_Banner_Slider_Settings::get();
        $stil = QMO_Banner_Slider_Settings::css_degiskenleri( $ayar );

        $onizleme_gorsel = $this->banner_onizleme_gorseli();
        $ornek_baslik    = 'Yaz Kampanyası';

        $adimlar = array(
            1 => array( 'Biçim', 'Oran ve Geçiş' ),
            2 => array( 'Gezinme', 'Oklar, Noktalar ve Otomatik Geçiş' ),
            3 => array( 'Başlık', 'Banner Başlığı' ),
        );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="qmo-banner-form">
            <?php wp_nonce_field( $this->banner_nonce_action ); ?>
            <input type="hidden" name="action" value="qmo_banner_ayar_kaydet">

            <h3 class="rma-kb-subheading">Banner Görünümü</h3>
            <p class="rma-card-desc">Kaydettikten sonra <code>[qmo_banner_slider]</code> kısa kodunun bulunduğu her sayfaya yansır.</p>

            <div class="rma-vitrin-steps" id="qmo-banner-steps" role="tablist" aria-label="Banner görünüm ayarları">
                <?php foreach ( $adimlar as $adim_no => $adim ) : ?>
                    <button type="button" class="rma-vitrin-step-btn<?php echo 1 === $adim_no ? ' is-active' : ''; ?>"
                            data-step-target="<?php echo (int) $adim_no; ?>"
                            role="tab" aria-selected="<?php echo 1 === $adim_no ? 'true' : 'false'; ?>">
                        <span class="rma-vitrin-step-num"><?php echo (int) $adim_no; ?></span>
                        <span class="rma-vitrin-step-label"><?php echo esc_html( $adim[0] ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="rma-vitrin-step-compact" id="qmo-banner-step-compact">Adım 1/<?php echo (int) count( $adimlar ); ?>: <?php echo esc_html( $adimlar[1][1] ); ?></p>

            <div class="rma-vitrin-layout-wrap">
                <div class="rma-vitrin-layout-fields">
                    <div class="rma-card rma-vitrin-step" data-step="1" data-step-title="Oran ve Geçiş">
                        <h2 class="rma-card-title">1. Oran ve Geçiş</h2>
                        <p class="rma-card-desc">Banner alanının yüksekliği en-boy oranıyla belirlenir; görseller bu alana kırpılarak sığdırılır. Önerilen görsel boyutu 16:9 için 1600x900px'dir.</p>

                        <table class="form-table rma-form-table">
                            <tr>
                                <th><label for="qmo-banner-oran">En-boy oranı</label></th>
                                <td>
                                    <select name="qmo_banner_slider_settings[oran]" id="qmo-banner-oran" class="rma-select-wide">
                                        <?php foreach ( QMO_Banner_Slider_Settings::oranlar() as $oran_anahtar => $oran_bilgi ) : ?>
                                            <option value="<?php echo esc_attr( $oran_anahtar ); ?>"
                                                    data-oran-css="<?php echo esc_attr( $oran_bilgi['css'] ); ?>"
                                                    <?php selected( $ayar['oran'], $oran_anahtar ); ?>><?php echo esc_html( $oran_bilgi['etiket'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description rma-desc">Dar bir şerit istiyorsanız 21:9 ya da 3:1 seçin; görselleriniz o orana göre kırpılacaktır.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qmo-banner-gecis">Geçiş biçimi</label></th>
                                <td>
                                    <select name="qmo_banner_slider_settings[gecis]" id="qmo-banner-gecis" class="rma-select-wide">
                                        <?php foreach ( QMO_Banner_Slider_Settings::gecisler() as $gecis_anahtar => $gecis_etiket ) : ?>
                                            <option value="<?php echo esc_attr( $gecis_anahtar ); ?>" <?php selected( $ayar['gecis'], $gecis_anahtar ); ?>><?php echo esc_html( $gecis_etiket ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description rma-desc">Kaydırma klasik karusel hissi verir; solma daha sakin bir geçiştir.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-card rma-vitrin-step" data-step="2" data-step-title="Oklar, Noktalar ve Otomatik Geçiş" style="display:none;">
                        <h2 class="rma-card-title">2. Oklar, Noktalar ve Otomatik Geçiş</h2>
                        <p class="rma-card-desc">Ziyaretçinin kampanyalar arasında nasıl geçeceğini belirleyin. Tek kampanya varken ok ve noktalar zaten basılmaz.</p>

                        <table class="form-table rma-form-table">
                            <tr>
                                <th>Oklar</th>
                                <td>
                                    <input type="hidden" name="qmo_banner_slider_settings[show_nav]" value="0">
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="qmo_banner_slider_settings[show_nav]" id="qmo-banner-show-nav" value="1" <?php checked( 1, $ayar['show_nav'] ); ?>>
                                        <span>Önceki/sonraki oklarını göster</span>
                                    </label>
                                    <p class="description rma-desc">Kapalıysa oklar banner'da hiç yer almaz. Varsayılan: açık.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Noktalar</th>
                                <td>
                                    <input type="hidden" name="qmo_banner_slider_settings[show_dots]" value="0">
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="qmo_banner_slider_settings[show_dots]" id="qmo-banner-show-dots" value="1" <?php checked( 1, $ayar['show_dots'] ); ?>>
                                        <span>Alt taraftaki nokta göstergesini göster</span>
                                    </label>
                                    <p class="description rma-desc">Kaç kampanya olduğunu ve hangisinde olunduğunu gösterir.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qmo-banner-autoplay">Otomatik geçiş</label></th>
                                <td>
                                    <select name="qmo_banner_slider_settings[autoplay]" id="qmo-banner-autoplay" class="rma-select-wide">
                                        <?php foreach ( QMO_Banner_Slider_Settings::autoplay_secenekleri() as $ms => $etiket ) : ?>
                                            <option value="<?php echo (int) $ms; ?>" <?php selected( (int) $ayar['autoplay'], (int) $ms ); ?>><?php echo esc_html( $etiket ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description rma-desc">Bir kampanya ekranda ne kadar bekleyecek. Kısa koda <code>autoplay="0"</code> yazılırsa o sayfada bu ayar ezilir.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="rma-card rma-vitrin-step" data-step="3" data-step-title="Banner Başlığı" style="display:none;">
                        <h2 class="rma-card-title">3. Banner Başlığı</h2>
                        <p class="rma-card-desc">Görselin üstüne binen başlık, kampanya kaydının adıdır. Yazı tipi ve renk tüm cihazlarda ortaktır; punto masaüstü ve mobil için ayrı ayarlanır.</p>

                        <table class="form-table rma-form-table">
                            <tr>
                                <th>Başlık</th>
                                <td>
                                    <input type="hidden" name="qmo_banner_slider_settings[show_title]" value="0">
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="qmo_banner_slider_settings[show_title]" id="qmo-banner-show-title" value="1" <?php checked( 1, $ayar['show_title'] ); ?>>
                                        <span>Kampanya başlığını görselin üstünde göster</span>
                                    </label>
                                    <p class="description rma-desc">Kapalıysa yalnızca görsel görünür. Görselin içinde zaten yazı varsa kapalı bırakın. Varsayılan: kapalı.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qmo-banner-title-font">Font ailesi</label></th>
                                <td>
                                    <select name="qmo_banner_slider_settings[title_font]" id="qmo-banner-title-font" class="rma-select-wide">
                                        <?php foreach ( QMO_Banner_Slider_Settings::yazi_tipleri() as $font_anahtar => $font_bilgi ) : ?>
                                            <option value="<?php echo esc_attr( $font_anahtar ); ?>" <?php selected( $ayar['title_font'], $font_anahtar ); ?>><?php echo esc_html( $font_bilgi['etiket'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qmo-banner-title-color">Font rengi</label></th>
                                <td>
                                    <input type="text" name="qmo_banner_slider_settings[title_color]" id="qmo-banner-title-color"
                                           value="<?php echo esc_attr( $ayar['title_color'] ); ?>"
                                           class="qmo-banner-color-picker"
                                           data-default-color="<?php echo esc_attr( QMO_Banner_Slider_Settings::varsayilanlar()['title_color'] ); ?>">
                                    <p class="description rma-desc">Başlık koyu bir degradenin üstünde durur; açık tonlar daha okunaklıdır.</p>
                                </td>
                            </tr>
                        </table>

                        <h3 class="rma-section-title">Masaüstü</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_font_size_row(
                                'qmo-banner-title-size',
                                'qmo_banner_slider_settings[title_size]',
                                (int) $ayar['title_size'],
                                QMO_Banner_Slider_Settings::MIN_TITLE_SIZE,
                                QMO_Banner_Slider_Settings::MAX_TITLE_SIZE,
                                'Font boyutu',
                                'Masaüstünde banner başlığının punto değeri.'
                            );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Mobil</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_font_size_row(
                                'qmo-banner-title-size-mobile',
                                'qmo_banner_slider_settings[title_size_mobile]',
                                (int) $ayar['title_size_mobile'],
                                QMO_Banner_Slider_Settings::MIN_TITLE_SIZE_MOBILE,
                                QMO_Banner_Slider_Settings::MAX_TITLE_SIZE_MOBILE,
                                'Font boyutu',
                                'Dar ekranda başlık bu puntoya düşer.'
                            );
                            ?>
                        </table>

                        <h3 class="rma-section-title">Kalınlık ve hizalama</h3>
                        <table class="form-table rma-form-table">
                            <?php
                            $this->vitrin_weight_row(
                                'qmo-banner-title-weight',
                                'qmo_banner_slider_settings[title_weight]',
                                (int) $ayar['title_weight'],
                                'Font kalınlığı',
                                '400 sakin, 600 varsayılan, 700 daha vurgulu.'
                            );
                            $this->vitrin_align_row(
                                'qmo-banner-title-align',
                                'qmo_banner_slider_settings[title_align]',
                                (string) $ayar['title_align'],
                                'Hizalama',
                                'Banner başlığının yatay yaslanması.'
                            );
                            ?>
                        </table>
                    </div>

                    <div class="rma-vitrin-step-nav" id="qmo-banner-step-nav">
                        <button type="button" class="button rma-vitrin-step-prev" disabled>&larr; Geri Dön</button>
                        <button type="button" class="button button-primary rma-vitrin-step-next">Devam Et &rarr;</button>
                        <button type="submit" class="button button-primary rma-vitrin-step-submit" style="display:none;">Ayarları Kaydet</button>
                    </div>
                </div>

                <div class="rma-vitrin-layout-preview">
                    <div class="rma-card rma-vitrin-preview-card">
                        <h2 class="rma-card-title">Canlı Önizleme</h2>
                        <p class="rma-card-desc">Soldaki her değişiklik anında yansır. <?php echo '' === $onizleme_gorsel ? 'Henüz görselli bir kampanya yok; yer tutucu gösteriliyor.' : 'Yayındaki ilk kampanya görseliniz kullanılıyor.'; ?></p>

                        <div class="rma-vitrin-preview-toggle">
                            <button type="button" class="button rma-vitrin-preview-btn is-active" data-preview-mode="desktop">Masaüstü Önizleme</button>
                            <button type="button" class="button rma-vitrin-preview-btn" data-preview-mode="mobile">Mobil Önizleme</button>
                        </div>

                        <div class="rma-vitrin-preview-stage" id="qmo-banner-preview-stage">
                            <div class="qmo-banner-root<?php echo 'fade' === $ayar['gecis'] ? ' is-fade' : ''; ?>"
                                 id="qmo-banner-preview"
                                 style="<?php echo esc_attr( $stil ); ?>">
                                <div class="qmo-banner-viewport">
                                    <div class="qmo-banner-track">
                                        <div class="qmo-banner-slide is-active">
                                            <?php if ( '' !== $onizleme_gorsel ) : ?>
                                                <img src="<?php echo esc_url( $onizleme_gorsel ); ?>" alt="" class="qmo-banner-img">
                                            <?php else : ?>
                                                <span class="qmo-banner-img qmo-banner-preview-empty" aria-hidden="true">1600 &times; 900</span>
                                            <?php endif; ?>
                                            <span class="qmo-banner-caption"<?php echo $ayar['show_title'] ? '' : ' style="display:none;"'; ?> data-qmo-banner-caption>
                                                <span class="qmo-banner-title"><?php echo esc_html( $ornek_baslik ); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="qmo-banner-nav"<?php echo $ayar['show_nav'] ? '' : ' style="display:none;"'; ?> data-qmo-banner-nav aria-hidden="true">
                                    <button type="button" class="qmo-banner-nav-btn qmo-banner-nav-prev" tabindex="-1">&#8249;</button>
                                    <button type="button" class="qmo-banner-nav-btn qmo-banner-nav-next" tabindex="-1">&#8250;</button>
                                </div>
                                <div class="qmo-banner-dots"<?php echo $ayar['show_dots'] ? '' : ' style="display:none;"'; ?> data-qmo-banner-dots aria-hidden="true">
                                    <span class="qmo-banner-dot is-active"></span>
                                    <span class="qmo-banner-dot"></span>
                                    <span class="qmo-banner-dot"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }

    /* -----------------------------------------------------------------
       ADIM 3 — TOPLU KAMPANYA GÖRSELİ OLUŞTURMA

       NEDEN TARAYICI (Canvas) TARAFI, SUNUCU (GD/Imagick) TARAFI DEĞİL:
       Kod tabanında hiçbir yerde GD/Imagick çizim çağrısı yok (tek görüntü
       işleme qr-galeri'deki wp_get_image_editor ile webp dönüştürmesi, o da
       çizim değil). Sunucuda metin basmak imagettftext + paketlenmiş bir TTF
       + Türkçe glif/metrik yönetimi demek olurdu ve paylaşımlı hostinglerde
       GD'nin FreeType desteği garanti değil. Canvas ise tarayıcının kendi
       font motorunu kullanır, kullanıcı sonucu birebir WYSIWYG görür ve
       sunucuda yapılacak tek iş data URI'yi doğrulayıp medya kütüphanesine
       yazmaktır — CPT'nin zaten okuduğu yol.
    ----------------------------------------------------------------- */

    /**
     * Hazır görsel şablonları — TEK KAYNAK (form kartları + JS çizimi).
     *
     * Değerler doğrudan data-* olarak markup'a basılır; JS başka bir yerden
     * renk okumaz. Şablon eklemek için buraya bir satır yetmesi kasıtlıdır.
     *
     * @return array<string,array{etiket:string,bg_bas:string,bg_son:string,baslik:string,alt_yazi:string,cizgi:string}>
     */
    public static function banner_sablonlari() {
        return array(
            'altin' => array(
                'etiket'   => 'Altın Gece',
                'bg_bas'   => '#0d0d10',
                'bg_son'   => '#2a2417',
                'baslik'   => '#f5f0e8',
                'alt_yazi' => '#c9a84c',
                'cizgi'    => '#c9a84c',
            ),
            'kiraz' => array(
                'etiket'   => 'Kiraz',
                'bg_bas'   => '#3a0d18',
                'bg_son'   => '#7d1a2f',
                'baslik'   => '#fff6ef',
                'alt_yazi' => '#f0b98b',
                'cizgi'    => '#f5c7a1',
            ),
            'zeytin' => array(
                'etiket'   => 'Zeytin',
                'bg_bas'   => '#14261c',
                'bg_son'   => '#2f5140',
                'baslik'   => '#f2f7ea',
                'alt_yazi' => '#c2d69a',
                'cizgi'    => '#d8e6b8',
            ),
        );
    }

    /**
     * Oran anahtarından ("16:9") üretilecek görselin piksel boyutu.
     *
     * QMO_Banner_Slider_Settings::oranlar() TEK KAYNAK olarak kalır; burada
     * yalnızca anahtarı sayıya çeviririz, o sınıfa hiç dokunulmaz.
     *
     * @param string $oran Oran anahtarı.
     * @return array{0:int,1:int} Genişlik ve yükseklik (px).
     */
    private function banner_oran_boyutu( $oran ) {
        $parca = explode( ':', (string) $oran );
        $en    = isset( $parca[0] ) ? (float) $parca[0] : 16.0;
        $boy   = isset( $parca[1] ) ? (float) $parca[1] : 9.0;

        if ( $en <= 0 || $boy <= 0 ) {
            $en  = 16.0;
            $boy = 9.0;
        }

        $genislik = (int) self::banner_uretim_genislik();

        return array( $genislik, (int) round( $genislik * $boy / $en ) );
    }

    /**
     * 3. adım: hazır şablonla kampanya görseli üretme aracı.
     *
     * @return void
     */
    private function render_banner_adim_olustur() {
        $ayar      = QMO_Banner_Slider_Settings::get();
        $sablonlar = self::banner_sablonlari();
        $ilk       = array_key_first( $sablonlar );
        ?>
        <div class="rma-card rma-kb-olustur"
             id="qmo-banner-olustur"
             data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
             data-ajax-action="<?php echo esc_attr( $this->banner_olustur_nonce_action ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( $this->banner_olustur_nonce_action ) ); ?>">

            <h3 class="rma-card-title">Toplu Kampanya Görseli Oluştur</h3>
            <p class="rma-card-desc">Elinizde hazır bir görsel yoksa buradan üretin: bir başlık yazın, oranı ve şablonu seçin — görsel tarayıcınızda çizilir, <strong>Kampanyayı Oluştur</strong> dediğinizde medya kütüphanesine yüklenir ve yeni bir kampanya kaydı olarak yayına alınır.</p>

            <div class="rma-vitrin-layout-wrap">
                <div class="rma-vitrin-layout-fields">
                    <table class="form-table rma-form-table">
                        <tr>
                            <th><label for="qmo-banner-uret-baslik">Başlık</label></th>
                            <td>
                                <input type="text" id="qmo-banner-uret-baslik" class="regular-text" maxlength="60" value="Yaz Kampanyası">
                                <p class="description rma-desc">Tek satır. Kampanya kaydının adı da bu olur.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="qmo-banner-uret-alt">Alt yazı (opsiyonel)</label></th>
                            <td>
                                <input type="text" id="qmo-banner-uret-alt" class="regular-text" maxlength="80" value="Tüm tatlılarda %20 indirim">
                                <p class="description rma-desc">Boş bırakılırsa yalnızca başlık basılır.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="qmo-banner-uret-oran">En-boy oranı</label></th>
                            <td>
                                <select id="qmo-banner-uret-oran" class="rma-select-wide">
                                    <?php
                                    foreach ( QMO_Banner_Slider_Settings::oranlar() as $oran_anahtar => $oran_bilgi ) :
                                        list( $uret_en, $uret_boy ) = $this->banner_oran_boyutu( $oran_anahtar );
                                        ?>
                                        <option value="<?php echo esc_attr( $oran_anahtar ); ?>"
                                                data-genislik="<?php echo (int) $uret_en; ?>"
                                                data-yukseklik="<?php echo (int) $uret_boy; ?>"
                                                <?php selected( $ayar['oran'], $oran_anahtar ); ?>><?php echo esc_html( $oran_bilgi['etiket'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description rma-desc">Varsayılan olarak banner'ınızın 2. adımdaki oranı seçilidir; aynı oranı seçmek kırpılmayı önler.</p>
                            </td>
                        </tr>
                    </table>

                    <h4 class="rma-section-title">Şablon</h4>
                    <div class="rma-kb-sablon-grid">
                        <?php foreach ( $sablonlar as $anahtar => $sablon ) : ?>
                            <label class="rma-kb-sablon">
                                <input type="radio" name="qmo_banner_sablon" value="<?php echo esc_attr( $anahtar ); ?>"
                                       data-bg-bas="<?php echo esc_attr( $sablon['bg_bas'] ); ?>"
                                       data-bg-son="<?php echo esc_attr( $sablon['bg_son'] ); ?>"
                                       data-baslik-renk="<?php echo esc_attr( $sablon['baslik'] ); ?>"
                                       data-alt-renk="<?php echo esc_attr( $sablon['alt_yazi'] ); ?>"
                                       data-cizgi-renk="<?php echo esc_attr( $sablon['cizgi'] ); ?>"
                                       <?php checked( $anahtar, $ilk ); ?>>
                                <span class="rma-kb-sablon-onizleme" style="background:linear-gradient(135deg,<?php echo esc_attr( $sablon['bg_bas'] ); ?>,<?php echo esc_attr( $sablon['bg_son'] ); ?>);">
                                    <span class="rma-kb-sablon-cizgi" style="background:<?php echo esc_attr( $sablon['cizgi'] ); ?>;"></span>
                                    <span class="rma-kb-sablon-metin" style="color:<?php echo esc_attr( $sablon['baslik'] ); ?>;">Aa</span>
                                </span>
                                <span class="rma-kb-sablon-ad"><?php echo esc_html( $sablon['etiket'] ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="rma-actions">
                        <button type="button" class="button button-primary" id="qmo-banner-uret-btn">Kampanyayı Oluştur</button>
                        <a class="button" href="<?php echo esc_url( $this->banner_wizard_url( 'kampanyalar' ) ); ?>">2. adıma dön</a>
                    </p>
                    <div class="rma-kb-uret-sonuc" id="qmo-banner-uret-sonuc" role="status" aria-live="polite"></div>
                </div>

                <div class="rma-vitrin-layout-preview">
                    <div class="rma-card rma-vitrin-preview-card">
                        <h2 class="rma-card-title">Önizleme</h2>
                        <p class="rma-card-desc">Aşağıda gördüğünüz görselin aynısı üretilir.</p>
                        <div class="rma-kb-canvas-wrap">
                            <canvas id="qmo-banner-uret-canvas" width="1600" height="900"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * `wp_ajax_qmo_banner_gorsel_olustur` — tarayıcıda çizilen PNG'yi medya
     * kütüphanesine yükler ve yeni bir kampanya (banner) kaydı oluşturur.
     *
     * Gelen veri bir data URI olduğu için üç kademede doğrulanır: önek,
     * base64 çözümü + boyut sınırı, PNG imzası; dosyaya yazıldıktan sonra
     * getimagesize ile tekrar. Herhangi biri tutmazsa dosya silinir ve
     * hiçbir kayıt oluşturulmaz.
     *
     * @return void
     */
    public function ajax_banner_gorsel_olustur() {
        check_ajax_referer( $this->banner_olustur_nonce_action, 'nonce' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
        }

        if ( ! post_type_exists( QMO_Banner_CPT::POST_TYPE ) ) {
            wp_send_json_error( array( 'message' => 'Kampanya banner içerik türü kayıtlı değil.' ), 400 );
        }

        $baslik = isset( $_POST['baslik'] ) ? sanitize_text_field( wp_unslash( $_POST['baslik'] ) ) : '';

        if ( '' === $baslik ) {
            $baslik = 'Kampanya';
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- aşağıda önek/base64/PNG imzası ile doğrulanır.
        $veri = isset( $_POST['gorsel'] ) ? (string) wp_unslash( $_POST['gorsel'] ) : '';
        $onek = 'data:image/png;base64,';

        if ( 0 !== strpos( $veri, $onek ) ) {
            wp_send_json_error( array( 'message' => 'Görsel verisi beklenen biçimde değil.' ), 400 );
        }

        $ham = base64_decode( substr( $veri, strlen( $onek ) ), true );

        if ( false === $ham || '' === $ham ) {
            wp_send_json_error( array( 'message' => 'Görsel verisi çözülemedi.' ), 400 );
        }

        if ( strlen( $ham ) > self::banner_uretim_max_byte() ) {
            wp_send_json_error( array( 'message' => 'Üretilen görsel çok büyük.' ), 400 );
        }

        // PNG dosya imzası — data URI'de yazan MIME'a güvenilmez.
        if ( "\x89PNG\r\n\x1a\n" !== substr( $ham, 0, 8 ) ) {
            wp_send_json_error( array( 'message' => 'Görsel verisi bir PNG değil.' ), 400 );
        }

        $dosya_adi = 'qmo-kampanya-' . sanitize_title( $baslik ) . '-' . time() . '.png';
        $yuklenen  = wp_upload_bits( $dosya_adi, null, $ham );

        if ( ! empty( $yuklenen['error'] ) ) {
            wp_send_json_error( array( 'message' => $yuklenen['error'] ), 500 );
        }

        // Dosyaya yazıldıktan sonraki son doğrulama: gerçekten PNG mi?
        $olcu = @getimagesize( $yuklenen['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if ( empty( $olcu ) || IMAGETYPE_PNG !== (int) ( $olcu[2] ?? 0 ) ) {
            wp_delete_file( $yuklenen['file'] );
            wp_send_json_error( array( 'message' => 'Yüklenen dosya geçerli bir PNG değil.' ), 400 );
        }

        $ek_id = wp_insert_attachment(
            array(
                'post_mime_type' => 'image/png',
                'post_title'     => $baslik,
                'post_content'   => '',
                'post_status'    => 'inherit',
            ),
            $yuklenen['file']
        );

        if ( ! $ek_id || is_wp_error( $ek_id ) ) {
            wp_delete_file( $yuklenen['file'] );
            wp_send_json_error( array( 'message' => 'Görsel medya kütüphanesine eklenemedi.' ), 500 );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata( $ek_id, wp_generate_attachment_metadata( $ek_id, $yuklenen['file'] ) );

        // Yeni kampanya en sona düşsün: mevcut en büyük sıra + 1.
        $sira = 0;

        foreach ( QMO_Banner_CPT::get_published_banners() as $mevcut ) {
            $sira = max( $sira, (int) $mevcut->menu_order + 1 );
        }

        $kayit_id = wp_insert_post(
            wp_slash(
                array(
                    'post_type'   => QMO_Banner_CPT::POST_TYPE,
                    'post_title'  => $baslik,
                    'post_status' => 'publish',
                    'menu_order'  => $sira,
                )
            ),
            true
        );

        if ( is_wp_error( $kayit_id ) ) {
            wp_send_json_error( array( 'message' => $kayit_id->get_error_message() ), 500 );
        }

        update_post_meta( $kayit_id, QMO_Banner_CPT::META_IMAGE, (int) $ek_id );

        wp_send_json_success(
            array(
                'id'       => (int) $kayit_id,
                'baslik'   => $baslik,
                'duzenle'  => (string) get_edit_post_link( $kayit_id, 'raw' ),
                'liste'    => $this->banner_wizard_url( 'kampanyalar' ),
                'message'  => sprintf( '"%s" kampanyası oluşturuldu ve yayına alındı.', $baslik ),
            )
        );
    }
}
