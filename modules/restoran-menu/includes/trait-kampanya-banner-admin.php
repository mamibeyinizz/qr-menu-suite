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

    /** Sihirbaz içinden yeni kampanya görseli oluşturma ucunun nonce eylemi. */
    private $banner_kampanya_olustur_nonce_action = 'qmo_banner_kampanya_olustur';

    /** Canlı önizleme (kaydedilmemiş oran) AJAX ucunun nonce eylemi. */
    private $banner_onizleme_nonce_action = 'qmo_banner_onizleme';

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
            'kampanyalar' => array( 'no' => 2, 'etiket' => 'Kampanya Görselleri', 'baslik' => 'Kampanya Görselleri ve Görünüm' ),
            'olustur'     => array( 'no' => 3, 'etiket' => 'Görsel Üret', 'baslik' => 'Hazır Şablonla Görsel Üret' ),
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
            'Kampanya Görselleri',
            'Menünüzün üstünde dönen kampanya görsellerini ekleyin, sıralayın ve görünümünü ayarlayın. İsterseniz hazır şablonla yeni görsel de üretebilirsiniz.'
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
            <nav class="rma-kb-tabs" aria-label="Kampanya görselleri bölümleri">
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
                <span class="rma-kb-nav-title">Kampanya Görselleri</span>
                <span class="rma-kb-nav-desc">Görselleri ekleyin, sıralayın ve tıklanınca gidilecek bağlantıyı belirleyin; banner oranı, geçişi ve okları burada ayarlanır.</span>
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
                <h3 class="rma-card-title">Kampanya Görselleri</h3>
                <a class="button" href="<?php echo esc_url( $this->banner_wizard_url( 'olustur' ) ); ?>">Hazır şablonla görsel üret</a>
            </div>
            <p class="rma-card-desc">Menü üstünde dönen görseller buradan yönetilir. Görseli olmayan kayıtlar ziyaretçiye gösterilmez. Küçük resim, sitede görünecek kadrajın önizlemesidir; altın çerçeve kalacak alanı gösterir.</p>

            <?php if ( $kirpma && $bekleyen > 0 ) : ?>
                <div class="rma-kb-kirpma-uyari">
                    <p>
                        <strong><?php echo (int) $bekleyen; ?> görsel</strong> seçtiğiniz oran için henüz hazır değil.
                        Fotoğraf banner alanına tam oturmayabilir; aşağıdaki düğmeyle hepsini güncelleyin veya listede odağı değiştirin.
                    </p>
                    <a class="button button-primary" href="<?php echo esc_url( $this->banner_kirp_url() ); ?>">Tüm görselleri banner oranına uydur</a>
                    <details class="rma-kb-teknik-not">
                        <summary>Teknik bilgi</summary>
                        <p class="description rma-desc">Görseller sunucuda seçilen en-boy oranına kırpılır. Eski oranla yüklenmiş dosyalar tarayıcıda geçici kesilir; hepsini aynı hizaya getirmek için toplu güncelleme gerekir.</p>
                    </details>
                </div>
            <?php elseif ( $kirpma && $toplam > 0 ) : ?>
                <p class="rma-card-desc rma-kb-kirpma-ok">Tüm görseller güncel <?php echo esc_html( $oran_metni ); ?> oranına hazır.</p>
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
                            <span class="rma-kb-satir-gorsel-kutu is-crop-preview is-odak-<?php echo esc_attr( $odak ); ?>" style="aspect-ratio:<?php echo esc_attr( $oran_css ); ?>;">
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
                                        <label class="rma-kb-satir-etiket" for="rma-kb-odak-<?php echo (int) $banner->ID; ?>">Odak (hangi bölüm kalsın)</label>
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

            <?php $this->render_banner_yeni_kampanya_panel(); ?>

            <p class="rma-actions">
                <button type="button" class="button button-secondary" id="qmo-banner-yeni-toggle" aria-expanded="false" aria-controls="qmo-banner-yeni-panel">+ Yeni kampanya görseli</button>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . QMO_Banner_CPT::POST_TYPE ) ); ?>">Tüm kayıtları WordPress'te aç</a>
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

    /**
     * Sihirbaz içinde yeni kampanya görseli ekleme paneli (post-new.php yerine).
     *
     * @return void
     */
    private function render_banner_yeni_kampanya_panel() {
        $odaklar = class_exists( 'QMO_Banner_Kirpma' ) ? QMO_Banner_Kirpma::odaklar() : array();
        ?>
        <div class="rma-card rma-kb-yeni-panel" id="qmo-banner-yeni-panel" hidden>
            <h4 class="rma-section-title">Yeni kampanya görseli</h4>
            <p class="rma-card-desc">Kampanya adı, görsel ve isteğe bağlı bağlantıyı buradan kaydedin; WordPress kayıt ekranına gitmenize gerek kalmaz.</p>
            <table class="form-table rma-form-table">
                <tr>
                    <th><label for="qmo-banner-yeni-baslik">Kampanya adı</label></th>
                    <td>
                        <input type="text" id="qmo-banner-yeni-baslik" class="regular-text" maxlength="120" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th>Görsel</th>
                    <td>
                        <input type="hidden" id="qmo-banner-yeni-gorsel" value="">
                        <button type="button" class="button" id="qmo-banner-yeni-gorsel-sec">Görsel seç</button>
                        <span class="rma-kb-yeni-gorsel-ad" id="qmo-banner-yeni-gorsel-ad"></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="qmo-banner-yeni-link">Banner tıklanınca gidilecek bağlantı</label></th>
                    <td>
                        <input type="url" id="qmo-banner-yeni-link" class="regular-text widefat" placeholder="https://">
                    </td>
                </tr>
                <?php if ( ! empty( $odaklar ) ) : ?>
                    <tr>
                        <th><label for="qmo-banner-yeni-odak">Odak</label></th>
                        <td>
                            <select id="qmo-banner-yeni-odak" class="rma-select-wide">
                                <?php foreach ( $odaklar as $odak_anahtar => $odak_bilgi ) : ?>
                                    <option value="<?php echo esc_attr( $odak_anahtar ); ?>"><?php echo esc_html( $odak_bilgi['etiket'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description rma-desc">Yüz, yemek veya logo kenarda kalıyorsa odağı o yöne alın.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <p class="rma-actions">
                <button type="button" class="button button-primary" id="qmo-banner-yeni-kaydet">Kampanya görselini kaydet</button>
                <button type="button" class="button" id="qmo-banner-yeni-iptal">İptal</button>
            </p>
            <p class="rma-kb-durum" id="qmo-banner-yeni-durum" role="status" aria-live="polite"></p>
        </div>
        <?php
    }

    /**
     * En-boy oranı seçici kartları (radyo grubu form alanıdır; JS yalnızca önizlemeyi günceller).
     *
     * @param string $select_id       Grup tanımlayıcısı (data-oran-select / önizleme JS).
     * @param string $name_attr       Form name attribute.
     * @param string $secili          Seçili oran anahtarı.
     * @param array  $oran_kaynak     QMO_Banner_Slider_Settings::oranlar() veya mobil_oranlar().
     * @return void
     */
    private function render_banner_oran_kartlari( $select_id, $name_attr, $secili, array $oran_kaynak ) {
        $labelledby = 'qmo-banner-oran-mobil' === $select_id ? 'qmo-banner-oran-mobil-label' : 'qmo-banner-oran-label';
        ?>
        <div class="qmo-banner-oran-secici" id="<?php echo esc_attr( $select_id ); ?>" data-oran-select="<?php echo esc_attr( $select_id ); ?>">
            <div class="qmo-banner-oran-grid" role="radiogroup" aria-labelledby="<?php echo esc_attr( $labelledby ); ?>">
                <?php foreach ( $oran_kaynak as $oran_anahtar => $oran_bilgi ) :
                    $baslik   = isset( $oran_bilgi['ux_baslik'] ) ? $oran_bilgi['ux_baslik'] : $oran_anahtar;
                    $kullanim = isset( $oran_bilgi['ux_kullanim'] ) ? $oran_bilgi['ux_kullanim'] : '';
                    $px       = class_exists( 'QMO_Banner_Slider_Settings' ) ? QMO_Banner_Slider_Settings::oran_px_etiketi( $oran_anahtar ) : '';
                    ?>
                    <label class="qmo-banner-oran-kart<?php echo $secili === $oran_anahtar ? ' is-selected' : ''; ?>">
                        <input type="radio"
                               name="<?php echo esc_attr( $name_attr ); ?>"
                               value="<?php echo esc_attr( $oran_anahtar ); ?>"
                               data-oran-css="<?php echo esc_attr( $oran_bilgi['css'] ); ?>"
                               <?php checked( $secili, $oran_anahtar ); ?>>
                        <span class="qmo-banner-oran-kutu" style="aspect-ratio:<?php echo esc_attr( $oran_bilgi['css'] ); ?>;" aria-hidden="true"></span>
                        <span class="qmo-banner-oran-metin">
                            <span class="qmo-banner-oran-baslik"><?php echo esc_html( $baslik ); ?></span>
                            <?php if ( '' !== $kullanim ) : ?>
                                <span class="qmo-banner-oran-kullanim"><?php echo esc_html( $kullanim ); ?></span>
                            <?php endif; ?>
                            <span class="qmo-banner-oran-teknik"><?php echo esc_html( $oran_anahtar ); ?><?php echo '' !== $px ? ' · ' . esc_html( $px ) : ''; ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * `wp_ajax_qmo_banner_kampanya_olustur` — sihirbazdan yeni kampanya kaydı.
     *
     * @return void
     */
    public function ajax_banner_kampanya_olustur() {
        check_ajax_referer( $this->banner_kampanya_olustur_nonce_action, 'nonce' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
        }

        if ( ! class_exists( 'QMO_Banner_CPT' ) ) {
            wp_send_json_error( array( 'message' => 'Bileşen yüklü değil.' ), 500 );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- olustur_kayit sanitize eder.
        $baslik = isset( $_POST['baslik'] ) ? wp_unslash( $_POST['baslik'] ) : '';

        $gorsel_id = isset( $_POST['gorsel'] ) ? absint( wp_unslash( $_POST['gorsel'] ) ) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw olustur_kayit içinde.
        $link = isset( $_POST['link'] ) ? wp_unslash( $_POST['link'] ) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- odak() beyaz listeye çeker.
        $odak = isset( $_POST['odak'] ) ? wp_unslash( $_POST['odak'] ) : 'merkez';

        $sonuc = QMO_Banner_CPT::olustur_kayit(
            array(
                'baslik'    => $baslik,
                'gorsel_id' => $gorsel_id,
                'link'      => $link,
                'odak'      => $odak,
                'durum'     => 'publish',
            )
        );

        if ( is_wp_error( $sonuc ) ) {
            wp_send_json_error( array( 'message' => $sonuc->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'id'      => (int) $sonuc,
                'message' => 'Kampanya görseli eklendi.',
                'reload'  => $this->banner_wizard_url( 'kampanyalar', array( 'banner_msg' => 'kampanya_eklendi' ) ),
            )
        );
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
            'kampanya_eklendi' => array( 'success', 'Yeni kampanya görseli eklendi.' ),
            'oran_degisti' => array( 'warning', 'Banner görünümü kaydedildi. En-boy oranı değişti — listedeki görselleri yeni orana uydurmanız gerekebilir.' ),
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
     * `wp_ajax_qmo_banner_onizleme` — form KAYDEDİLMEDEN, oran/mobil oran
     * seçimleriyle kısa kodun üreteceği HTML'in canlı önizlemesi.
     *
     * NEDEN AYRI UÇ: ayar formu 2. adımdaki oran/mobil oran seçimi
     * değiştiğinde kullanıcı sonucu kaydetmeden görebilmeli. Ayarlar
     * option'a YAZILMAZ, kırpma dosyası ÜRETİLMEZ — yalnızca hâlihazırda
     * var olan kırpmalar (QMO_Banner_Kirpma::gorsel()/oranli_gorsel(), her
     * ikisi de salt okunur) okunur. HTML, render_shortcode()'un kullandığı
     * AYNI renderer'dan (QMO_Shortcode_Banner_Slider::payloadlar() →
     * kok_html()) üretilir; ikinci bir HTML üretim yolu yoktur.
     *
     * @return void
     */
    public function ajax_banner_onizleme() {
        check_ajax_referer( $this->banner_onizleme_nonce_action, 'nonce' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
            return;
        }

        $ayar = class_exists( 'QMO_Banner_Slider_Settings' )
            ? QMO_Banner_Slider_Settings::get()
            : array(
                'show_nav'   => 0,
                'show_dots'  => 1,
                'show_title' => 0,
                'gecis'      => 'slide',
                'autoplay'   => 4500,
                'oran'       => '16:9',
            );

        // Ham girdi hiçbir zaman doğrudan kullanılmaz: oran/oran_mobil
        // yalnızca QMO_Banner_Slider_Settings::oranlar() beyaz listesindeki
        // bir anahtarsa kabul edilir — GEÇERSİZ oran sessizce varsayılana
        // çekilmez, istek reddedilir (kullanıcı yönetimdeki listede
        // OLMAYAN bir şey seçemeyeceğinden bu yalnızca bozuk/kurcalanmış
        // isteklerde tetiklenir). oran_mobil_farkli yalnızca '0'/'1' kabul
        // eder. Sonuç YALNIZCA bu istek boyunca bellekte tutulan $ayar'a
        // yazılır — kaydedilmez.
        $beyaz_liste = class_exists( 'QMO_Banner_Slider_Settings' ) ? array_keys( QMO_Banner_Slider_Settings::oranlar() ) : array();

        if ( isset( $_POST['oran'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- aşağıda beyaz listeyle karşılaştırılıyor.
            $ham_oran = trim( (string) wp_unslash( $_POST['oran'] ) );

            if ( ! in_array( $ham_oran, $beyaz_liste, true ) ) {
                wp_send_json_error( array( 'message' => 'Geçersiz oran.' ), 400 );
                return;
            }

            $ayar['oran'] = $ham_oran;
        }

        if ( isset( $_POST['oran_mobil'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- aşağıda beyaz listeyle karşılaştırılıyor.
            $ham_mobil = trim( (string) wp_unslash( $_POST['oran_mobil'] ) );

            if ( ! in_array( $ham_mobil, $beyaz_liste, true ) ) {
                wp_send_json_error( array( 'message' => 'Geçersiz mobil oran.' ), 400 );
                return;
            }

            $ayar['oran_mobil'] = $ham_mobil;
        }

        if ( isset( $_POST['oran_mobil_farkli'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- aşağıda '0'/'1' ile karşılaştırılıyor.
            $ham_farkli = trim( (string) wp_unslash( $_POST['oran_mobil_farkli'] ) );

            if ( '0' !== $ham_farkli && '1' !== $ham_farkli ) {
                wp_send_json_error( array( 'message' => 'Geçersiz değer.' ), 400 );
                return;
            }

            $ayar['oran_mobil_farkli'] = (int) $ham_farkli;
        }

        $ayar = $this->banner_onizleme_post_ayar_birlestir( $ayar );

        $banners = QMO_Shortcode_Banner_Slider::payloadlar( $ayar );
        $html    = QMO_Shortcode_Banner_Slider::kok_html(
            $banners,
            $ayar,
            array(
                'autoplay' => (int) $ayar['autoplay'],
                'betik'    => false,
            )
        );

        // "durum": önizlenen oranlar için sunucuda kırpma bekleyen banner
        // var mı (salt okunur — bkz. QMO_Banner_Kirpma::bekleyen_sayisi(),
        // kırpma ÜRETMEZ, yalnızca mevcut ek meta'sını okur).
        $durum = 'hazir';

        if ( class_exists( 'QMO_Banner_Kirpma' ) && class_exists( 'QMO_Banner_CPT' ) && class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            $banner_kayitlari = QMO_Banner_CPT::get_published_banners();
            $bekleyen         = QMO_Banner_Kirpma::bekleyen_sayisi_oranlar(
                QMO_Banner_Slider_Settings::aktif_oranlar( $ayar ),
                $banner_kayitlari
            );

            $durum = $bekleyen > 0 ? 'bekliyor' : 'hazir';
        }

        wp_send_json_success(
            array(
                'html'  => $html,
                'durum' => $durum,
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
     * Önizleme AJAX isteğindeki görünüm alanlarını bellekteki $ayar ile birleştirir.
     *
     * Yalnızca tanınan anahtarlar sanitize edilir; option/meta YAZILMAZ.
     *
     * @param array $ayar Mevcut birleşik ayar.
     * @return array
     */
    private function banner_onizleme_post_ayar_birlestir( array $ayar ) {
        if ( ! class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            return $ayar;
        }

        $ham = array();

        $bayraklar = array( 'show_nav', 'show_dots', 'show_title' );
        foreach ( $bayraklar as $anahtar ) {
            if ( isset( $_POST[ $anahtar ] ) ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() içinde bayrak().
                $ham[ $anahtar ] = wp_unslash( $_POST[ $anahtar ] );
            }
        }

        if ( isset( $_POST['gecis'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() gecis().
            $ham['gecis'] = wp_unslash( $_POST['gecis'] );
        }

        if ( isset( $_POST['autoplay'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() autoplay().
            $ham['autoplay'] = wp_unslash( $_POST['autoplay'] );
        }

        $metin_alanlar = array(
            'title_font',
            'title_color',
            'title_size',
            'title_size_mobile',
            'title_weight',
            'title_align',
        );
        foreach ( $metin_alanlar as $anahtar ) {
            if ( isset( $_POST[ $anahtar ] ) ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize().
                $ham[ $anahtar ] = wp_unslash( $_POST[ $anahtar ] );
            }
        }

        if ( empty( $ham ) ) {
            return $ayar;
        }

        return QMO_Banner_Slider_Settings::sanitize( array_merge( $ayar, $ham ) );
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

        $onizleme_gorsel = $this->banner_onizleme_gorseli();
        $banner_payload  = QMO_Shortcode_Banner_Slider::payloadlar( $ayar );
        $onizleme_kok    = ! empty( $banner_payload )
            ? QMO_Shortcode_Banner_Slider::kok_html(
                $banner_payload,
                $ayar,
                array(
                    'autoplay' => (int) $ayar['autoplay'],
                    'betik'    => false,
                )
            )
            : '';

        $adimlar = array(
            1 => array( 'Biçim', 'Oran ve Geçiş' ),
            2 => array( 'Gezinme', 'Oklar, Noktalar ve Otomatik Geçiş' ),
            3 => array( 'Başlık', 'Banner Başlığı' ),
        );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="qmo-banner-form">
            <?php wp_nonce_field( $this->banner_nonce_action ); ?>
            <input type="hidden" name="action" value="qmo_banner_ayar_kaydet">

            <h3 class="rma-kb-subheading">Görünüm ayarları</h3>
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
                        <p class="rma-card-desc">Banner yüksekliği seçtiğiniz oranla belirlenir. Fotoğraflar kaydederken bu alana sığacak şekilde kesilir; orijinal dosya korunur.</p>
                        <p class="description rma-desc">Oranı değiştirirseniz mevcut görselleri yeni orana uydurmanız gerekir; kaydettikten sonra listede toplu güncelleme düğmesi görünür.</p>

                        <table class="form-table rma-form-table">
                            <tr>
                                <th><span id="qmo-banner-oran-label">Bilgisayarda banner şekli</span></th>
                                <td>
                                    <?php $this->render_banner_oran_kartlari( 'qmo-banner-oran', 'qmo_banner_slider_settings[oran]', $ayar['oran'], QMO_Banner_Slider_Settings::oranlar() ); ?>
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
                                        <span>Telefonda farklı banner şekli kullan</span>
                                    </label>
                                    <p class="description rma-desc">Geniş bir oran telefonda çok ince bir şerit olabilir; burayı açıp kare veya klasik oran seçebilirsiniz.</p>

                                    <div id="qmo-banner-oran-mobil-alan" class="rma-kb-kosullu"<?php echo $ayar['oran_mobil_farkli'] ? '' : ' hidden'; ?>>
                                        <span class="rma-kb-satir-etiket" id="qmo-banner-oran-mobil-label">Telefonda banner şekli</span>
                                        <?php $this->render_banner_oran_kartlari( 'qmo-banner-oran-mobil', 'qmo_banner_slider_settings[oran_mobil]', $ayar['oran_mobil'], QMO_Banner_Slider_Settings::mobil_oranlar() ); ?>
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
                                    <p class="description rma-desc">Sayfa düzeninde otomatik geçişi kapatmak için buradan “Kapalı” seçin.</p>
                                    <details class="rma-kb-teknik-not">
                                        <summary>Teknik bilgi</summary>
                                        <p class="description rma-desc">Belirli bir sayfada farklı bir süre istiyorsanız kısa kod bloğunda otomatik geçiş süresini sayfa bazında değiştirebilirsiniz.</p>
                                    </details>
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

                        <div class="rma-vitrin-preview-toggle" role="group" aria-label="Önizleme cihazı">
                            <button type="button" class="button rma-vitrin-preview-btn is-active" data-preview-mode="desktop" aria-pressed="true">Masaüstü Önizleme</button>
                            <button type="button" class="button rma-vitrin-preview-btn" data-preview-mode="mobile" aria-pressed="false">Mobil Önizleme</button>
                        </div>

                        <div class="rma-vitrin-preview-stage" id="qmo-banner-preview-stage">
                            <div class="qmo-banner-preview-frame" id="qmo-banner-preview-frame">
                                <div class="qmo-banner-crop-guide" id="qmo-banner-crop-guide" aria-hidden="true"></div>
                                <iframe id="qmo-banner-preview-iframe"
                                        class="qmo-banner-preview-iframe"
                                        title="<?php echo esc_attr( 'Kampanya banner canlı önizleme' ); ?>"
                                        tabindex="0"></iframe>
                            </div>
                            <p class="qmo-banner-preview-durum rma-kb-durum" id="qmo-banner-preview-durum" aria-live="polite"></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php
        wp_add_inline_script(
            'rma-admin-ui',
            'window.QMO_BANNER_PREVIEW_INITIAL=' . wp_json_encode(
                array(
                    'kokHtml' => $onizleme_kok,
                )
            ) . ';',
            'before'
        );
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
                                <p class="description rma-desc">Varsayılan olarak görünüm ayarlarındaki oran seçilidir.</p>
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
                        <a class="button" href="<?php echo esc_url( $this->banner_wizard_url( 'kampanyalar' ) ); ?>">Kampanyalara dön</a>
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
