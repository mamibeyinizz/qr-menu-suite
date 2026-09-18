<?php
/**
 * Kampanya Banner — yönetim sihirbazı (kendi başına ayrı bir admin sayfası).
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
 * modülün TÜM sayfa iskeletini taşıyor, banner'ın ~900 satırı orayı okunmaz
 * hâle getirirdi.
 *
 * KENDİ SAYFASI: ekran `qrms-rm-kampanya-banner` slug'ıyla get_subpages()
 * içinde kayıtlıdır ve hub'da kendi kartı vardır — başka bir sayfanın alt
 * bölümü DEĞİLDİR. Giriş noktası render_kampanya_banner_page().
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

    /** Görselleri yeniden kırpma ucunun nonce eylemi (= admin_post eylemi). */
    private $banner_kirp_nonce_action = 'qmo_banner_kirp';

    /** Liste satırından görsel/odak güncelleme ucunun nonce eylemi. */
    private $banner_satir_nonce_action = 'qmo_banner_satir_kaydet';

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
     * Sayfanın bölümleri — TEK KAYNAK (sekme şeridi, başlıklar ve URL
     * doğrulaması).
     *
     * ADIM DEĞİL SEKME: burası sırayla tamamlanan bir sihirbaz değil; üç
     * bölüm birbirinden bağımsızdır ve 3. bölüm (görsel üretme) tamamen
     * opsiyoneldir. Eskiden "Adım 1/3 … 3/3" deniyordu; aynı ekranda ayar
     * formunun KENDİ gerçek stepper'ı da "Adım 1/3" bastığı için ekranda
     * iki ayrı adım sayacı görünüyordu. Numaralar buradan kaldırıldı,
     * gerçek stepper (Biçim / Gezinme / Başlık) olduğu gibi durur.
     *
     * `no` alanı korunur: URL'ler, sıralama ve testler ona bakar.
     *
     * @return array<string,array{no:int,etiket:string,baslik:string}>
     */
    private function banner_adimlari() {
        return array(
            'ozet'        => array( 'no' => 1, 'etiket' => 'Genel Bakış', 'baslik' => 'Genel Bakış' ),
            'kampanyalar' => array( 'no' => 2, 'etiket' => 'Kampanyalar', 'baslik' => 'Kampanyalar ve Banner Ayarları' ),
            'olustur'     => array( 'no' => 3, 'etiket' => 'Görsel Üret', 'baslik' => 'Toplu Kampanya Görseli Oluştur' ),
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
            'qrms-rm-kampanya-banner',
            array_merge( array( 'banner_adim' => $adim ), $args )
        );
    }

    /**
     * "Kampanya Banner" sayfası — get_subpages()'teki qrms-rm-kampanya-banner
     * kaydının render'ı.
     *
     * Başka bir sayfanın bölümü değil, kendi başına bir ekrandır; hub'da da
     * kendi kartı vardır.
     *
     * @return void
     */
    public function render_kampanya_banner_page() {
        $this->page_header(
            'Kampanya Banner',
            'Sayfanın en üstünde tam genişlikte dönen kampanya görselleri. Görsellerin kendisi, görünüm ayarları ve hazır görsel üretme aracı bu üç adımda toplandı.'
        );

        $this->render_banner_wizard_section();

        $this->page_footer();
    }

    /**
     * Üç adımlı Kampanya Banner sihirbazı.
     *
     * Sayfa iskeletinden (page_header/page_footer) bilerek ayrı durur:
     * sihirbazın kendisi bu metottadır, sayfaya bağlanması
     * render_kampanya_banner_page()'in işidir.
     *
     * @return void
     */
    public function render_banner_wizard_section() {
        if ( ! post_type_exists( QMO_Banner_CPT::POST_TYPE ) || ! class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            echo '<div class="rma-card"><p class="rma-empty">Kampanya Banner bileşeni yüklü değil.</p></div>';
            return;
        }

        $adim    = $this->banner_adim();
        $adimlar = $this->banner_adimlari();
        ?>
        <div class="rma-kb-wizard" id="<?php echo esc_attr( self::banner_anchor() ); ?>">
            <?php $this->banner_notice(); ?>

            <?php
            /*
             * Sekme şeridi .rma-vitrin-steps DEĞİL .rma-kb-tabs sınıfını
             * kullanır. İki sebebi var:
             *   1. Ayar formunun gerçek stepper'ı .rma-vitrin-step* ile
             *      çizilir; aynı sınıfı paylaştıkları için 2. bölümde
             *      birbirinin aynısı iki şerit üst üste görünüyordu.
             *   2. .rma-vitrin-steps ≤480px'de display:none oluyor. O kural
             *      form stepper'ı için doğru (onun prev/next düğmeleri var),
             *      ama bu şerit sayfanın TEK gezinme aracı: gizlenince
             *      telefonda 1. bölüme dönmenin tarayıcı geri tuşu dışında
             *      yolu kalmıyordu.
             * Adresler ve `banner_adim` query arg'ı birebir aynı.
             */
            ?>
            <nav class="rma-kb-tabs" aria-label="Kampanya Banner bölümleri">
                <?php foreach ( $adimlar as $anahtar => $bilgi ) : ?>
                    <a class="rma-kb-tab<?php echo $anahtar === $adim ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url( $this->banner_wizard_url( $anahtar ) ); ?>"
                       <?php echo $anahtar === $adim ? 'aria-current="page"' : ''; ?>>
                        <span class="rma-kb-tab-label"><?php echo esc_html( $bilgi['etiket'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

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
        $mobil_farkli = QMO_Banner_Slider_Settings::mobil_oran_farkli( $ayar );
        $mobil_oran   = QMO_Banner_Slider_Settings::oran_mobil( $ayar );
        $onizleme  = $this->banner_onizleme_gorseli();
        ?>
        <div class="rma-card">
            <h3 class="rma-card-title">Banner şu an ne durumda?</h3>

            <?php if ( 0 === $gorselli ) : ?>
                <p class="rma-empty">Görseli olan yayında kampanya yok — banner şu an sayfanızda görünmüyor.</p>
            <?php else : ?>
                <p class="rma-card-desc"><strong><?php echo (int) $gorselli; ?></strong> kampanya yayında<?php echo $toplam > $gorselli ? ' (' . (int) ( $toplam - $gorselli ) . ' kampanyanın görseli seçilmemiş, onlar basılmaz)' : ''; ?>.</p>
            <?php endif; ?>

            <ul class="rma-kb-ozet">
                <li><span class="rma-kb-ozet-etiket">Toplam kampanya</span><span class="rma-kb-ozet-deger"><?php echo (int) $toplam; ?></span></li>
                <li><span class="rma-kb-ozet-etiket">Gösterimde</span><span class="rma-kb-ozet-deger"><?php echo (int) $gorselli; ?></span></li>
                <li><span class="rma-kb-ozet-etiket">Bilgisayarda oran</span><span class="rma-kb-ozet-deger"><?php echo esc_html( $oran_adi ); ?></span></li>
                <li><span class="rma-kb-ozet-etiket">Telefonda oran</span><span class="rma-kb-ozet-deger"><?php echo $mobil_farkli ? esc_html( $mobil_oran ) : 'Bilgisayardakiyle aynı'; ?></span></li>
                <li><span class="rma-kb-ozet-etiket">Otomatik geçiş</span><span class="rma-kb-ozet-deger"><?php echo $ayar['autoplay'] ? esc_html( number_format_i18n( $ayar['autoplay'] / 1000, 1 ) . ' sn' ) : 'Kapalı'; ?></span></li>
            </ul>

            <?php if ( '' !== $onizleme ) : ?>
                <div class="rma-kb-onizleme" style="aspect-ratio:<?php echo esc_attr( isset( $oranlar[ $ayar['oran'] ] ) ? $oranlar[ $ayar['oran'] ]['css'] : '16 / 9' ); ?>;">
                    <img src="<?php echo esc_url( $onizleme ); ?>" alt="">
                </div>
            <?php endif; ?>

            <?php $this->render_banner_shortcode_kutusu(); ?>
        </div>

        <div class="rma-kb-nav-grid">
            <a class="rma-kb-nav-card" href="<?php echo esc_url( $this->banner_wizard_url( 'kampanyalar' ) ); ?>">
                <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                <span class="rma-kb-nav-title">Kampanyalar</span>
                <span class="rma-kb-nav-desc">Kampanya görsellerini ekleyin, sırasını ve bağlantısını düzenleyin; banner'ın oranını, geçişini, oklarını ve başlığını ayarlayın.</span>
                <span class="rma-kb-nav-git">Kampanyalara git &rarr;</span>
            </a>
            <a class="rma-kb-nav-card" href="<?php echo esc_url( $this->banner_wizard_url( 'olustur' ) ); ?>">
                <span class="dashicons dashicons-art" aria-hidden="true"></span>
                <span class="rma-kb-nav-title">Toplu Kampanya Görseli Oluştur</span>
                <span class="rma-kb-nav-desc">Hazır şablonla, tarayıcıda tek tıkla kampanya görseli üretin; üretilen görsel doğrudan yeni bir kampanya olarak kaydedilir.</span>
                <span class="rma-kb-nav-git">Görsel üretmeye git &rarr;</span>
            </a>
        </div>
        <?php
    }

    /**
     * "Banner'ı Sayfaya Ekle" — katlanır kısa kod kutusu.
     *
     * NEDEN KATLANIR: kısa kod teknik bir kavram ve restoran sahibinin ana
     * akışında değil. Eskiden aynı kod ekranda dört ayrı yerde yazılıydı
     * (özet kartı, liste açıklaması, ayar formu açıklaması ve boş durum
     * cümlesi); ilk gördüğü şey buydu. Artık TEK yerde, kapalı olarak
     * duruyor — teknik kullanıcı için hiçbir şey kaybolmadı, kopyalama tek
     * düğmeye indi. `autoplay="0"` ipucu buradan çıkarıldı; yeri, o ayarın
     * kendi alanının altıdır (bkz. render_banner_ayar_formu).
     *
     * <details> kullanılır: aç/kapa durumu tarayıcının kendi işidir, JS
     * gerekmez ve aria-expanded'ı tarayıcı yönetir.
     *
     * @return void
     */
    private function render_banner_shortcode_kutusu() {
        $shortcode = '[qmo_banner_slider]';
        ?>
        <details class="rma-kb-kisa-kod">
            <summary class="rma-kb-kisa-kod-baslik">Banner'ı Sayfaya Ekle</summary>
            <div class="rma-kb-kisa-kod-govde">
                <p class="description rma-desc">Banner'ın görünmesini istediğiniz sayfaya aşağıdaki kodu yapıştırın.</p>
                <ul class="rma-kb-kisa-kod-nerede">
                    <li><strong>Elementor:</strong> “Shortcode” widget'ına yapıştırın.</li>
                    <li><strong>WordPress editörü:</strong> “Kısa Kod” bloğuna yapıştırın.</li>
                </ul>
                <div class="rma-shortcode-row">
                    <input type="text" class="rma-shortcode-input" readonly value="<?php echo esc_attr( $shortcode ); ?>" aria-label="Kampanya banner kısa kodu">
                    <button type="button" class="button rma-copy-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>">Kopyala</button>
                </div>
            </div>
        </details>
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
        $banners  = QMO_Banner_CPT::get_admin_banners();
        $toplam   = count( $banners );
        $ayar     = QMO_Banner_Slider_Settings::get();
        $oran     = $ayar['oran'];
        $oran_css = QMO_Banner_Slider_Settings::oran_css( $oran );
        $mobil_farkli = QMO_Banner_Slider_Settings::mobil_oran_farkli( $ayar );
        $mobil_oran   = QMO_Banner_Slider_Settings::oran_mobil( $ayar );
        $kirpma   = class_exists( 'QMO_Banner_Kirpma' );
        // null: masaüstü VE (varsa) mobil oranın ikisi de hesaba katılır.
        $bekleyen = $kirpma ? QMO_Banner_Kirpma::bekleyen_sayisi( null, $banners ) : 0;
        $oran_metni = $mobil_farkli
            ? $oran . ' / ' . $mobil_oran . ' (telefon)'
            : $oran;
        ?>
        <div class="rma-card" id="rma-banner">
            <div class="rma-vitrin-list-head">
                <h3 class="rma-card-title">Aktif Kampanyalar</h3>
                <a class="button" href="<?php echo esc_url( $this->banner_wizard_url( 'olustur' ) ); ?>">Hazır şablonla görsel üret</a>
            </div>
            <p class="rma-card-desc">Sayfanın en üstünde tam genişlikte dönen kampanya görselleri. Görseli seçilmemiş kayıtlar gösterilmez. Küçük resimler ön yüzde görünecek kadrajın aynısıdır.</p>

            <?php if ( $kirpma && $bekleyen > 0 ) : ?>
                <div class="rma-kb-kirpma-uyari">
                    <p>
                        <strong><?php echo (int) $bekleyen; ?> kampanya görseli</strong> güncel <?php echo esc_html( $oran_metni ); ?> oranına göre sunucuda kırpılmamış.
                        Bu görseller ön yüzde yalnızca tarayıcı tarafından kesilir; slaytlar birbirini tutmayabilir.
                    </p>
                    <a class="button button-primary" href="<?php echo esc_url( $this->banner_kirp_url() ); ?>">Tüm görselleri yeniden kırp</a>
                </div>
            <?php elseif ( $kirpma && $toplam > 0 ) : ?>
                <p class="rma-card-desc rma-kb-kirpma-ok">Tüm kampanya görselleri güncel <?php echo esc_html( $oran_metni ); ?> oranına göre hazır.</p>
            <?php endif; ?>

            <?php if ( empty( $banners ) ) : ?>
                <p class="rma-empty">Henüz kampanya eklenmemiş.</p>
            <?php else : ?>
                <ul class="rma-kb-liste" id="rma-banner-sira-listesi" data-banner-sira
                    data-satir-nonce="<?php echo esc_attr( wp_create_nonce( $this->banner_satir_nonce_action ) ); ?>"
                    data-oran-css="<?php echo esc_attr( $oran_css ); ?>">
                    <?php foreach ( $banners as $index => $banner ) :
                        $gorsel_id = (int) get_post_meta( $banner->ID, QMO_Banner_CPT::META_IMAGE, true );
                        $link      = (string) get_post_meta( $banner->ID, QMO_Banner_CPT::META_LINK, true );
                        $edit_link = get_edit_post_link( $banner->ID );
                        $sira_no   = $index + 1;
                        $durum     = get_post_status_object( $banner->post_status );
                        $yayinda   = 'publish' === $banner->post_status;
                        $durum_etiket = ( $durum && ! $yayinda ) ? (string) $durum->label : 'Yayında';

                        // Kadraj ve odak MEVCUT yardımcılardan okunur; liste
                        // kendi kırpma hesabını yapmaz.
                        $odak      = $kirpma ? QMO_Banner_Kirpma::banner_odagi( $banner->ID ) : 'merkez';
                        $odak_css  = $kirpma ? QMO_Banner_Kirpma::odak_css( $odak ) : 'center center';
                        $onizleme  = $this->banner_satir_onizleme( $gorsel_id, $oran, $odak );
                        $kirpma_durum = $kirpma ? QMO_Banner_Kirpma::banner_durumu( (int) $banner->ID ) : 'gorsel-yok';
                        ?>
                        <li class="rma-kb-satir" data-banner-id="<?php echo (int) $banner->ID; ?>">
                            <span class="rma-kb-satir-gorsel-kutu" style="aspect-ratio:<?php echo esc_attr( $oran_css ); ?>;">
                                <?php if ( '' !== $onizleme ) : ?>
                                    <img class="rma-kb-satir-thumb" src="<?php echo esc_url( $onizleme ); ?>" alt=""
                                         style="object-position:<?php echo esc_attr( $odak_css ); ?>;" data-satir-thumb>
                                <?php else : ?>
                                    <span class="rma-kb-satir-bos" aria-hidden="true">Görsel yok</span>
                                <?php endif; ?>
                            </span>

                            <span class="rma-kb-satir-bilgi">
                                <span class="rma-kb-satir-ad">
                                    <?php if ( $edit_link ) : ?>
                                        <a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $banner->post_title ?: 'Başlıksız kampanya' ); ?></a>
                                    <?php else : ?>
                                        <strong><?php echo esc_html( $banner->post_title ?: 'Başlıksız kampanya' ); ?></strong>
                                    <?php endif; ?>
                                </span>

                                <span class="rma-kb-rozetler">
                                    <span class="rma-kb-rozet<?php echo $yayinda ? ' is-yayinda' : ' is-taslak'; ?>"><?php echo esc_html( $durum_etiket ); ?></span>
                                    <span class="rma-kb-rozet"><?php echo esc_html( $oran_metni ); ?></span>
                                    <?php if ( ! $gorsel_id ) : ?>
                                        <span class="rma-kb-rozet is-uyari">Görsel seçilmemiş — gösterilmez</span>
                                    <?php endif; ?>
                                    <?php if ( '' !== $link ) : ?>
                                        <span class="rma-kb-rozet is-link" title="<?php echo esc_attr( $link ); ?>">Bağlantılı</span>
                                    <?php endif; ?>
                                </span>

                                <?php if ( $kirpma && $gorsel_id ) : ?>
                                    <span class="rma-kb-satir-alan">
                                        <label class="rma-kb-satir-etiket" for="rma-kb-odak-<?php echo (int) $banner->ID; ?>">Kırpma odağı</label>
                                        <select class="rma-kb-satir-odak" id="rma-kb-odak-<?php echo (int) $banner->ID; ?>" data-satir-odak>
                                            <?php foreach ( QMO_Banner_Kirpma::odaklar() as $odak_anahtar => $odak_bilgi ) : ?>
                                                <option value="<?php echo esc_attr( $odak_anahtar ); ?>"
                                                        data-odak-css="<?php echo esc_attr( $odak_bilgi['css'] ); ?>"
                                                        <?php selected( $odak, $odak_anahtar ); ?>><?php echo esc_html( $odak_bilgi['etiket'] ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </span>
                                <?php endif; ?>

                                <span class="rma-kb-satir-eylemler">
                                    <button type="button" class="button rma-kb-satir-btn" data-satir-gorsel-sec>
                                        <?php echo $gorsel_id ? 'Görseli değiştir' : 'Görsel seç'; ?>
                                    </button>
                                    <?php if ( $edit_link ) : ?>
                                        <a class="button rma-kb-satir-btn" href="<?php echo esc_url( $edit_link ); ?>">Düzenle</a>
                                    <?php endif; ?>
                                </span>

                                <?php if ( 'bekliyor' === $kirpma_durum ) : ?>
                                    <span class="rma-kb-kirpma-rozet is-bekliyor">
                                        Bu görsel eski: <?php echo esc_html( $oran_metni ); ?> oranına kırpılmadı
                                        <a href="<?php echo esc_url( $this->banner_kirp_url( (int) $banner->ID ) ); ?>">Yeniden kırp</a>
                                    </span>
                                <?php elseif ( 'hazir' === $kirpma_durum ) : ?>
                                    <span class="rma-kb-kirpma-rozet is-hazir"><?php echo esc_html( $oran_metni ); ?> oranına kırpıldı</span>
                                <?php elseif ( 'uygun' === $kirpma_durum ) : ?>
                                    <span class="rma-kb-kirpma-rozet is-hazir">Zaten <?php echo esc_html( $oran_metni ); ?> oranında</span>
                                <?php endif; ?>
                            </span>

                            <span class="rma-banner-sira">
                                <span class="rma-simple-meta" data-sira-etiket>Sıra: <?php echo (int) $sira_no; ?></span>
                                <span class="rma-banner-sira-btns">
                                    <button type="button" class="button rma-banner-sira-btn" data-yon="up" aria-label="Yukarı taşı"<?php echo 0 === $index ? ' disabled' : ''; ?>><span aria-hidden="true">&#9650;</span></button>
                                    <button type="button" class="button rma-banner-sira-btn" data-yon="down" aria-label="Aşağı taşı"<?php echo ( $index === $toplam - 1 ) ? ' disabled' : ''; ?>><span aria-hidden="true">&#9660;</span></button>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php
                /*
                 * Sıra ve satır kaydetmelerinin geri bildirimi. role="status"
                 * + aria-live="polite": ekran okuyucu metni odağı çalmadan
                 * okur. Boşken CSS ile gizlenir (bkz. .rma-kb-durum:empty).
                 */
                ?>
                <p class="rma-kb-durum" id="rma-banner-durum" role="status" aria-live="polite"></p>
            <?php endif; ?>

            <p class="rma-actions">
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . QMO_Banner_CPT::POST_TYPE ) ); ?>">Yeni Kampanya Ekle</a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . QMO_Banner_CPT::POST_TYPE ) ); ?>">Tüm Kampanyaları Yönet</a>
            </p>
        </div>
        <?php
    }

    /**
     * Liste satırındaki küçük resmin adresi.
     *
     * Ön yüzün BASACAĞI dosyayı gösterir: kırpılmış sürüm varsa o, yoksa
     * orijinal. Eskiden burada WordPress'in kare `thumbnail` boyutu
     * basılıyordu; listedeki kare görsel ile ön yüzdeki geniş kadraj
     * birbirini tutmuyordu. Kırpma/odak hesabı QMO_Banner_Kirpma'nın
     * işidir, burada tekrarlanmaz.
     *
     * @param int    $gorsel_id Ek (attachment) ID'si.
     * @param string $oran      Oran anahtarı.
     * @param string $odak      Odak anahtarı.
     * @return string Boş dize = görsel yok.
     */
    private function banner_satir_onizleme( $gorsel_id, $oran, $odak ) {
        $gorsel_id = (int) $gorsel_id;

        if ( $gorsel_id < 1 ) {
            return '';
        }

        if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
            $gorsel = QMO_Banner_Kirpma::gorsel( $gorsel_id, $oran, $odak );

            if ( $gorsel ) {
                return (string) $gorsel['url'];
            }
        }

        return (string) wp_get_attachment_image_url( $gorsel_id, 'large' );
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

        $onceki = QMO_Banner_Slider_Settings::aktif_oranlar();
        $temiz  = QMO_Banner_Slider_Settings::kaydet( $ham );
        $simdi  = QMO_Banner_Slider_Settings::aktif_oranlar( $temiz );

        // ORAN DEĞİŞTİYSE mevcut kırpmalar bayatlar: kırpılmış sürümler oran
        // başına ayrı saklandığı için hiçbiri silinmez, ama yeni oranın
        // kırpması henüz yoktur. Kullanıcıyı karanlıkta bırakmamak için
        // liste ekranına "yeniden kırpılması gereken N görsel var" bildirimi
        // ile döneriz; oradaki tek düğme hepsini üretir.
        $mesaj = 'kaydedildi';

        // Masaüstü VEYA mobil oran değiştiyse bayatlama ihtimali var; hangi
        // oranın değiştiğini ayırt etmeye gerek yok, bekleyen sayısı ikisini
        // birden hesaplıyor.
        if ( $onceki !== $simdi && class_exists( 'QMO_Banner_Kirpma' ) && QMO_Banner_Kirpma::bekleyen_sayisi() > 0 ) {
            $mesaj = 'oran_degisti';
        }

        // Ayar formu sihirbazın 2. adımıdır; kaydeden kullanıcı geldiği
        // yere döner.
        wp_safe_redirect( $this->banner_wizard_url( 'kampanyalar', array( 'banner_msg' => $mesaj ) ) );
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
            'kaydedildi' => array( 'success', 'Banner görünümü kaydedildi.' ),
            'oran_degisti' => array( 'warning', 'Banner görünümü kaydedildi. En-boy oranı değiştiği için mevcut görsellerin yeni orana göre yeniden kırpılması gerekiyor — aşağıdaki “Tüm görselleri yeniden kırp” düğmesini kullanın.' ),
        );

        if ( 'kirpildi' === $durum ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $ok = isset( $_GET['kirp_ok'] ) ? absint( $_GET['kirp_ok'] ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $hata = isset( $_GET['kirp_hata'] ) ? absint( $_GET['kirp_hata'] ) : 0;

            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr( $hata ? 'warning' : 'success' ),
                esc_html(
                    sprintf(
                        '%d görsel güncel orana göre yeniden kırpıldı.%s',
                        $ok,
                        $hata ? sprintf( ' %d görsel kırpılamadı — sunucuda GD/Imagick eksik ya da dosya okunamıyor olabilir.', $hata ) : ''
                    )
                )
            );

            return;
        }

        if ( ! isset( $mesajlar[ $durum ] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr( $mesajlar[ $durum ][0] ),
            esc_html( $mesajlar[ $durum ][1] )
        );
    }

    /**
     * `admin_post_qmo_banner_kirp` — görselleri güncel orana yeniden kırpar.
     *
     * İKİ KAPSAM: `banner` alanı boşsa bütün kampanyalar taranır (oran
     * değişince tek tıkla toparlanır), `banner=<ID>` ise yalnızca o kayıt
     * işlenir (liste satırındaki "Yeniden kırp" düğmesi). İki yol da aynı kırpma
     * fonksiyonuna gider; ayrı olmalarının nedeni yalnızca kullanıcının
     * seçim özgürlüğüdür.
     *
     * @return void
     */
    public function handle_banner_kirp() {
        check_admin_referer( $this->banner_kirp_nonce_action );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
        }

        if ( ! class_exists( 'QMO_Banner_Kirpma' ) ) {
            wp_safe_redirect( $this->banner_wizard_url( 'kampanyalar' ) );
            exit;
        }

        $banner_id = isset( $_GET['banner'] ) ? absint( wp_unslash( $_GET['banner'] ) ) : 0;

        if ( $banner_id > 0 ) {
            $islem = QMO_Banner_Kirpma::banner_kirp( $banner_id );
            $sonuc = array(
                'ok'   => ( true === $islem ) ? 1 : 0,
                'hata' => is_wp_error( $islem ) ? 1 : 0,
            );
        } else {
            $sonuc = QMO_Banner_Kirpma::toplu_kirp();
        }

        wp_safe_redirect(
            $this->banner_wizard_url(
                'kampanyalar',
                array(
                    'banner_msg' => 'kirpildi',
                    'kirp_ok'    => (int) $sonuc['ok'],
                    'kirp_hata'  => (int) $sonuc['hata'],
                )
            )
        );
        exit;
    }

    /**
     * `wp_ajax_qmo_banner_satir_kaydet` — liste satırından görsel ve/veya
     * kırpma odağı güncellemesi.
     *
     * NEDEN AYRI UÇ: mevcut `qmo_banner_sira_kaydet` yalnızca menu_order
     * yazar ve dokunulmadı. Bu uç aynı meta anahtarlarını
     * (_qmo_banner_gorsel_id, _qmo_banner_odak) CPT ekranındaki save_meta
     * ile AYNI kurallarla yazar: odak beyaz listeden geçer, görsel gerçek
     * bir ek olmalıdır, kayıttan sonra kırpma güncel oranlara göre üretilir.
     * CPT ekranındaki alanlar yerinde durur; burası ikinci bir yol, ikame
     * değil.
     *
     * @return void
     */
    public function ajax_banner_satir_kaydet() {
        check_ajax_referer( $this->banner_satir_nonce_action, 'nonce' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
        }

        $banner_id = isset( $_POST['banner'] ) ? absint( wp_unslash( $_POST['banner'] ) ) : 0;

        if ( $banner_id < 1 || get_post_type( $banner_id ) !== QMO_Banner_CPT::POST_TYPE ) {
            wp_send_json_error( array( 'message' => 'Kampanya bulunamadı.' ), 400 );
        }

        if ( ! current_user_can( 'edit_post', $banner_id ) ) {
            wp_send_json_error( array( 'message' => 'Bu kampanyayı düzenleme yetkiniz yok.' ), 403 );
        }

        $degisti = false;

        if ( isset( $_POST['odak'] ) && class_exists( 'QMO_Banner_Kirpma' ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- odak() beyaz listeye çeker.
            $odak = QMO_Banner_Kirpma::odak( wp_unslash( $_POST['odak'] ) );
            update_post_meta( $banner_id, QMO_Banner_Kirpma::META_ODAK, $odak );
            $degisti = true;
        }

        if ( isset( $_POST['gorsel'] ) ) {
            $gorsel_id = absint( wp_unslash( $_POST['gorsel'] ) );

            if ( $gorsel_id > 0 && 'attachment' === get_post_type( $gorsel_id ) ) {
                update_post_meta( $banner_id, QMO_Banner_CPT::META_IMAGE, $gorsel_id );
                $degisti = true;
            } elseif ( 0 === $gorsel_id ) {
                delete_post_meta( $banner_id, QMO_Banner_CPT::META_IMAGE );
                $degisti = true;
            } else {
                wp_send_json_error( array( 'message' => 'Seçilen görsel geçerli değil.' ), 400 );
            }
        }

        if ( ! $degisti ) {
            wp_send_json_error( array( 'message' => 'Değiştirilecek bir şey gönderilmedi.' ), 400 );
        }

        // Kırpma CPT ekranındaki akışın aynısı: aktif oranların hepsi için
        // üretilir, hata olursa kayıt yine durur ve satırda uyarı çıkar.
        if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
            QMO_Banner_Kirpma::banner_kirp( $banner_id );
        }

        $ayar   = QMO_Banner_Slider_Settings::get();
        $gorsel = (int) get_post_meta( $banner_id, QMO_Banner_CPT::META_IMAGE, true );
        $odak   = class_exists( 'QMO_Banner_Kirpma' ) ? QMO_Banner_Kirpma::banner_odagi( $banner_id ) : 'merkez';

        wp_send_json_success(
            array(
                'thumb'    => $this->banner_satir_onizleme( $gorsel, $ayar['oran'], $odak ),
                'odak_css' => class_exists( 'QMO_Banner_Kirpma' ) ? QMO_Banner_Kirpma::odak_css( $odak ) : 'center center',
                'message'  => 'Kaydedildi',
            )
        );
    }

    /**
     * Yeniden kırpma bağlantısının adresi (nonce'lu).
     *
     * Form değil bağlantı: satır eylemi liste öğesinin İÇİNDE, bir <span>
     * altında duruyor — oraya <form> koymak geçersiz HTML olurdu. Nonce'lu
     * bağlantı WordPress'in kendi satır eylemi (çöpe taşı, geri al)
     * deseninin aynısıdır; yetki ve nonce yine sunucuda doğrulanır.
     *
     * @param int $banner_id 0 = tüm kampanyalar.
     * @return string
     */
    private function banner_kirp_url( $banner_id = 0 ) {
        $args = array( 'action' => 'qmo_banner_kirp' );

        if ( $banner_id > 0 ) {
            $args['banner'] = (int) $banner_id;
        }

        return wp_nonce_url(
            add_query_arg( $args, admin_url( 'admin-post.php' ) ),
            $this->banner_kirp_nonce_action
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

            // Ön yüzle AYNI dosyayı göster: kırpılmış sürüm varsa o.
            // Önizlemenin 'large' okuması, yönetim ile ön yüzün farklı
            // görünmesinin ikinci kaynağıydı.
            if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
                $gorsel = QMO_Banner_Kirpma::gorsel( $gorsel_id, null, QMO_Banner_Kirpma::banner_odagi( $banner->ID ) );

                if ( $gorsel ) {
                    return $gorsel['url'];
                }
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
     * iskeleti (page_header/page_footer) düştü, çünkü artık kendi başına bir
     * sayfa değil Kampanya Banner sihirbazının 2. adımıdır.
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
            <p class="rma-card-desc">Kaydettikten sonra banner'ın bulunduğu her sayfaya yansır.</p>

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
                        <p class="rma-card-desc">Banner alanının yüksekliği en-boy oranıyla belirlenir. Yüklenen görseller kaydedilirken <strong>sunucuda</strong> bu orana kırpılır (orijinal dosya korunur), böylece hangi oranda yüklenmiş olurlarsa olsunlar tüm slaytlar aynı görünür. 16:9 için önerilen boyut 1600x900px; 21:9 ve 3:1 seçilince yükseklik o orana göre düşer.</p>
                        <p class="description rma-desc">Oranı sonradan değiştirirseniz mevcut görsellerin yeni orana göre yeniden kırpılması gerekir; kaydettikten sonra yukarıdaki listede tek tıklık bir düğme çıkar.</p>

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
                                    <p class="description rma-desc">Dar bir şerit istiyorsanız 21:9 ya da 3:1 seçin; görselleriniz sunucuda o orana yeniden kırpılır.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Telefonda oran</th>
                                <td>
                                    <input type="hidden" name="qmo_banner_slider_settings[oran_mobil_farkli]" value="0">
                                    <label class="rma-check-row">
                                        <input type="checkbox" name="qmo_banner_slider_settings[oran_mobil_farkli]"
                                               id="qmo-banner-oran-mobil-farkli" value="1"
                                               aria-controls="qmo-banner-oran-mobil-alan"
                                               aria-expanded="<?php echo $ayar['oran_mobil_farkli'] ? 'true' : 'false'; ?>"
                                               <?php checked( 1, $ayar['oran_mobil_farkli'] ); ?>>
                                        <span>Mobilde farklı oran kullan</span>
                                    </label>
                                    <p class="description rma-desc">Kapalıyken telefonda da yukarıdaki oran kullanılır. Geniş bir oran (21:9, 3:1) telefonda çok ince bir şeride dönüşür; burayı açıp 4:3 ya da 1:1 seçerseniz telefon için <strong>ayrı bir kırpma üretilir</strong>.</p>

                                    <div id="qmo-banner-oran-mobil-alan" class="rma-kb-kosullu"<?php echo $ayar['oran_mobil_farkli'] ? '' : ' hidden'; ?>>
                                        <label class="rma-kb-satir-etiket" for="qmo-banner-oran-mobil">Telefonda en-boy oranı</label>
                                        <select name="qmo_banner_slider_settings[oran_mobil]" id="qmo-banner-oran-mobil" class="rma-select-wide">
                                            <?php foreach ( QMO_Banner_Slider_Settings::mobil_oranlar() as $oran_anahtar => $oran_bilgi ) : ?>
                                                <option value="<?php echo esc_attr( $oran_anahtar ); ?>"
                                                        data-oran-css="<?php echo esc_attr( $oran_bilgi['css'] ); ?>"
                                                        <?php selected( $ayar['oran_mobil'], $oran_anahtar ); ?>><?php echo esc_html( $oran_bilgi['etiket'] ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description rma-desc">Bilgisayardakiyle aynı oranı seçerseniz ikinci kırpma üretilmez.</p>
                                    </div>
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
     * QMO_Banner_Slider_Settings::onerilen_px() TEK KAYNAK: canvas export,
     * kısa kod width/height ipucu ve CSS --qmo-banner-oran aynı hesabı
     * paylaşır.
     *
     * @param string $oran Oran anahtarı.
     * @return array{0:int,1:int} Genişlik ve yükseklik (px).
     */
    private function banner_oran_boyutu( $oran ) {
        if ( class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            return QMO_Banner_Slider_Settings::onerilen_px( $oran );
        }

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

        foreach ( QMO_Banner_CPT::get_admin_banners() as $mevcut ) {
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

        // Üretilen görsel seçilen oranda çizilir; ama kullanıcı araçta
        // banner ayarından FARKLI bir oran seçmiş olabilir. Elle yüklenen
        // görsellerle aynı yoldan geçirilir: uyuyorsa kırpma üretilmez,
        // uymuyorsa sunucuda kırpılır.
        if ( class_exists( 'QMO_Banner_Kirpma' ) ) {
            QMO_Banner_Kirpma::banner_kirp( $kayit_id );
        }

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
