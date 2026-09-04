<?php
/**
 * Toplu Fiyat Kampanyası — yönetim ekranı.
 *
 * Liste görünümü (aktif kampanya kartı + geçmiş) ve kampanya formu. Kaydetme,
 * aktifleştirme ve geri alma AJAX değil `admin_post_` + nonce + redirect ile
 * yapılır (modüldeki vitrin ekranının deseninin aynısı). Yalnızca CANLI
 * ÖNİZLEME AJAX'tır: kaydedilmemiş form değerleriyle çalışması gerekir.
 *
 * Fiyat verisine dokunulmaz. Ekranın yaptığı tek yazma işi kampanya kaydı,
 * aktivasyon anındaki fiyat fotoğrafı ve `_qrms_orijinal_fiyat` yedeğidir.
 *
 * İSİMLENDİRME: bu dosya YALNIZCA "Fiyat Kampanyaları"dır (toplu zam/indirim).
 * Banner GÖRSELLERİ artık "Kampanya" adıyla ayrı bir ekranda yönetilir ve bu
 * sayfayla hiçbir ortak kodu kalmadı — bkz. trait-kampanya-banner-admin.php
 * (kendi başına ayrı bir ekran: qrms-rm-kampanya-banner). İki kavram bilerek
 * birbirinden ayrıldı; buraya banner kodu geri eklenmemelidir.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Kampanya_Admin_Trait {

    /** Form ve buton nonce eylemi. */
    private $kampanya_nonce_action = 'rma_kampanya_kaydet';

    /**
     * Kampanya ekranının adresi.
     *
     * @param array $args Ek query arg'ları.
     * @return string
     */
    private function kampanya_url( array $args = array() ) {
        return add_query_arg(
            array_merge( array( 'page' => 'qrms-rm-kampanya' ), $args ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Kampanya ekranı: `kampanya` parametresi varsa form, yoksa liste.
     *
     * @return void
     */
    public function render_campaign_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $duzenle = isset( $_GET['kampanya'] ) ? sanitize_text_field( wp_unslash( $_GET['kampanya'] ) ) : '';

        if ( 'yeni' === $duzenle ) {
            $this->render_kampanya_form( null );
            return;
        }

        if ( '' !== $duzenle && ctype_digit( $duzenle ) ) {
            $kayit = RMA_Kampanya_DB::getir( (int) $duzenle );

            if ( $kayit ) {
                $this->render_kampanya_form( $kayit );
                return;
            }
        }

        $this->render_kampanya_list();
    }

    /**
     * Kaydetme/geri alma sonrası bildirimi basar.
     *
     * @return void
     */
    private function kampanya_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $durum = isset( $_GET['kmp_msg'] ) ? sanitize_key( wp_unslash( $_GET['kmp_msg'] ) ) : '';

        $mesajlar = array(
            'kaydedildi' => array( 'success', 'Kampanya kaydedildi. Uygulamak için "Kampanyayı Başlat" deyin.' ),
            'uygulandi'  => array( 'success', 'Kampanya uygulandı. Menüdeki fiyatlar bu andan itibaren yeni kurala göre görünüyor.' ),
            'geri'       => array( 'success', 'Kampanya geri alındı. Tüm ürünler orijinal fiyatlarına döndü.' ),
            'silindi'    => array( 'success', 'Kampanya silindi.' ),
            'hata'       => array( 'error', 'Kampanya kaydedilemedi. Lütfen tekrar deneyin.' ),
        );

        if ( ! isset( $mesajlar[ $durum ] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr( $mesajlar[ $durum ][0] ),
            esc_html( $mesajlar[ $durum ][1] )
        );
    }

    /* -----------------------------------------------------------------
       LİSTE
    ----------------------------------------------------------------- */

    /**
     * Kampanya listesi: aktif kampanya göstergesi + geçmiş.
     *
     * @return void
     */
    private function render_kampanya_list() {
        $this->page_header(
            'Fiyat Kampanyaları',
            'Menüdeki fiyatları toplu olarak zamlayın ya da indirin. Ürünlerinizin orijinal fiyatı hiçbir zaman değişmez — kampanyayı kaldırdığınız anda eski fiyatlara dönülür.'
        );

        $this->kampanya_notice();

        $hepsi  = RMA_Kampanya_DB::hepsi();
        $aktif  = null;
        $gecmis = array();

        foreach ( $hepsi as $k ) {
            if ( 'active' === $k->status && null === $aktif ) {
                $aktif = $k;
                continue;
            }

            $gecmis[] = $k;
        }
        ?>
        <div class="rma-card rma-kmp-durum <?php echo $aktif ? 'is-aktif' : ''; ?>">
            <?php if ( $aktif ) : ?>
                <div class="rma-kmp-durum-head">
                    <div>
                        <span class="rma-kmp-rozet">Şu an aktif</span>
                        <h2 class="rma-card-title"><?php echo esc_html( $aktif->title ); ?></h2>
                    </div>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                          onsubmit="return confirm('Kampanya geri alınsın mı? Tüm ürünler anında orijinal fiyatlarına döner.');">
                        <?php wp_nonce_field( $this->kampanya_nonce_action ); ?>
                        <input type="hidden" name="action" value="rma_kampanya_geri_al">
                        <input type="hidden" name="kampanya_id" value="<?php echo (int) $aktif->id; ?>">
                        <button type="submit" class="button button-primary rma-kmp-geri">↩ Kampanyayı Geri Al</button>
                    </form>
                </div>

                <table class="rma-kmp-ozet">
                    <tr>
                        <td data-label="Kural"><strong><?php echo esc_html( RMA_Kampanya_DB::kural_metni( $aktif ) ); ?></strong></td>
                        <td data-label="Kapsam"><?php echo esc_html( $this->kampanya_kapsam_metni( $aktif ) ); ?></td>
                        <td data-label="Uygulandı"><?php echo esc_html( $this->kampanya_tarih( $aktif->applied_at ) ); ?></td>
                        <td data-label="Etkilenen"><?php echo (int) $aktif->affected_count; ?> ürün</td>
                    </tr>
                </table>

                <p class="rma-desc">Ürünlerin <code>rma_price</code> alanı değişmedi; menüde görünen fiyat her açılışta orijinal fiyat + bu kural birleştirilerek hesaplanıyor.</p>

                <p>
                    <a class="button" href="<?php echo esc_url( $this->kampanya_url( array( 'kampanya' => (int) $aktif->id ) ) ); ?>">Kampanyayı Düzenle</a>
                </p>
            <?php else : ?>
                <h2 class="rma-card-title">Şu an aktif kampanya yok</h2>
                <p class="rma-card-desc">Menüdeki bütün ürünler orijinal fiyatlarıyla görünüyor.</p>
                <p><a class="button button-primary" href="<?php echo esc_url( $this->kampanya_url( array( 'kampanya' => 'yeni' ) ) ); ?>">+ Yeni Kampanya Oluştur</a></p>
            <?php endif; ?>
        </div>

        <div class="rma-card">
            <div class="rma-vitrin-list-head">
                <h2 class="rma-card-title">Kampanyalarım</h2>
                <a class="button" href="<?php echo esc_url( $this->kampanya_url( array( 'kampanya' => 'yeni' ) ) ); ?>">+ Yeni Kampanya</a>
            </div>

            <?php if ( empty( $gecmis ) ) : ?>
                <p class="rma-empty">Kayıtlı başka kampanya yok.</p>
            <?php else : ?>
                <table class="rma-kmp-tablo">
                    <thead>
                        <tr>
                            <th>Kampanya</th>
                            <th>Kural</th>
                            <th>Kapsam</th>
                            <th>Son çalıştığı zaman</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $gecmis as $k ) : ?>
                            <tr>
                                <td data-label="Kampanya"><strong><?php echo esc_html( $k->title ); ?></strong></td>
                                <td data-label="Kural"><?php echo esc_html( RMA_Kampanya_DB::kural_metni( $k ) ); ?></td>
                                <td data-label="Kapsam"><?php echo esc_html( $this->kampanya_kapsam_metni( $k ) ); ?></td>
                                <td data-label="Son çalıştığı zaman">
                                    <?php
                                    echo $k->applied_at
                                        ? esc_html( $this->kampanya_tarih( $k->applied_at ) . ' → ' . ( $k->ended_at ? $this->kampanya_tarih( $k->ended_at ) : '—' ) )
                                        : 'Hiç uygulanmadı';
                                    ?>
                                </td>
                                <td data-label="İşlemler" class="rma-kmp-islem">
                                    <a class="button" href="<?php echo esc_url( $this->kampanya_url( array( 'kampanya' => (int) $k->id ) ) ); ?>">Düzenle</a>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                          onsubmit="return confirm('Bu kampanya kaydı silinsin mi? Ürün fiyatlarınız etkilenmez.');">
                                        <?php wp_nonce_field( $this->kampanya_nonce_action ); ?>
                                        <input type="hidden" name="action" value="rma_kampanya_sil">
                                        <input type="hidden" name="kampanya_id" value="<?php echo (int) $k->id; ?>">
                                        <button type="submit" class="button rma-btn-danger">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        $this->page_footer();
    }

    /* -----------------------------------------------------------------
       TAŞINDI — KAMPANYA BANNER

       Banner görsellerinin listesi (eski render_banner_section) ve banner
       görünüm ayarları (eski render_banner_settings_page +
       handle_banner_settings_save) artık bu dosyada DEĞİL: ikisi de
       trait-kampanya-banner-admin.php'ye taşındı ve kendi başına ayrı bir
       ekranda (qrms-rm-kampanya-banner) üç adımlı sihirbaz olarak render
       ediliyor. Eski `qrms-rm-banner-ayar` sayfası kaldırıldı; o adrese
       gelen istekler get_legacy_page_map() üzerinden yeni sayfaya
       yönlendiriliyor (bkz. trait-admin-pages.php).

       Bu sayfa artık yalnızca FİYAT kampanyalarını (toplu zam/indirim)
       yönetir; banner ile hiçbir ortak kodu yoktur.
    ----------------------------------------------------------------- */

    /**
     * Kampanya kapsamının insan diline çevirisi.
     *
     * @param object|array $kampanya Kampanya kaydı.
     * @return string
     */
    private function kampanya_kapsam_metni( $kampanya ) {
        $k     = (array) $kampanya;
        $idler = RMA_Kampanya_DB::id_listesi_temizle( $k['scope_ids'] ?? '' );

        if ( 'category' === ( $k['scope_type'] ?? 'all' ) ) {
            $adlar = array();

            foreach ( $idler as $terim_id ) {
                $terim = get_term( $terim_id, 'rma_category' );

                if ( $terim && ! is_wp_error( $terim ) ) {
                    $adlar[] = $terim->name;
                }
            }

            return $adlar ? 'Kategori: ' . implode( ', ', $adlar ) : 'Kategori seçilmemiş';
        }

        if ( 'manual' === ( $k['scope_type'] ?? 'all' ) ) {
            return 'Seçilen ' . count( $idler ) . ' ürün';
        }

        return 'Menüdeki tüm ürünler';
    }

    /**
     * MySQL tarihini okunur biçime çevirir.
     *
     * @param string|null $tarih MySQL datetime.
     * @return string
     */
    private function kampanya_tarih( $tarih ) {
        $tarih = (string) $tarih;

        if ( '' === $tarih || '0000-00-00 00:00:00' === $tarih ) {
            return '—';
        }

        return mysql2date( 'j F Y, H:i', $tarih );
    }

    /* -----------------------------------------------------------------
       FORM
    ----------------------------------------------------------------- */

    /**
     * Yeni/mevcut kampanya formu.
     *
     * @param object|null $kayit Mevcut kampanya ya da null (yeni).
     * @return void
     */
    private function render_kampanya_form( $kayit ) {
        $yeni = ( null === $kayit );
        $id   = $yeni ? 0 : (int) $kayit->id;
        $v    = RMA_Kampanya_DB::varsayilanlar();

        $deger = array(
            'title'          => $yeni ? '' : $kayit->title,
            'calc_type'      => $yeni ? $v['calc_type'] : $kayit->calc_type,
            'direction'      => $yeni ? $v['direction'] : $kayit->direction,
            'amount'         => $yeni ? '' : RMA_Kampanya_DB::bicimle( $kayit->amount ),
            'rounding'       => $yeni ? $v['rounding'] : $kayit->rounding,
            'scope_type'     => $yeni ? $v['scope_type'] : $kayit->scope_type,
            'scope_ids'      => $yeni ? array() : RMA_Kampanya_DB::id_listesi_temizle( $kayit->scope_ids ),
            'show_old_price' => $yeni ? 1 : (int) $kayit->show_old_price,
        );

        $aktif_mi   = ! $yeni && 'active' === $kayit->status;
        $baska      = RMA_Kampanya_DB::aktif_kayit();
        $kategoriler = get_terms( array( 'taxonomy' => 'rma_category', 'hide_empty' => false ) );

        if ( is_wp_error( $kategoriler ) ) {
            $kategoriler = array();
        }

        $urunler = $this->kampanya_urun_listesi();

        $this->page_header(
            $yeni ? 'Yeni Fiyat Kampanyası' : 'Kampanyayı Düzenle',
            'Kuralı ve kapsamı belirleyin, önizlemede hangi ürünün kaç liraya çıkacağını görün, sonra uygulayın.'
        );

        $this->kampanya_notice();
        ?>
        <p><a class="rma-back-link" href="<?php echo esc_url( $this->kampanya_url() ); ?>">&larr; Tüm kampanyalar</a></p>

        <?php if ( $aktif_mi ) : ?>
            <div class="notice notice-info inline rma-kmp-uyari">
                <p>Bu kampanya <strong>şu an çalışıyor</strong>. Kaydettiğiniz her değişiklik menüdeki fiyatlara anında yansır — bu yüzden önce önizlemeyi görmeniz gerekir.</p>
            </div>
        <?php endif; ?>

        <?php if ( $baska && (int) $baska->id !== $id ) : ?>
            <div class="notice notice-warning inline rma-kmp-uyari">
                <p><strong>"<?php echo esc_html( $baska->title ); ?>"</strong> kampanyası şu an aktif. Bu kampanyayı başlatırsanız o kendiliğinden kapanır — aynı anda yalnızca bir kampanya çalışır, böylece indirimler üst üste binmez.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rma-kmp-form">
            <?php wp_nonce_field( $this->kampanya_nonce_action ); ?>
            <input type="hidden" name="action" value="rma_kampanya_kaydet">
            <input type="hidden" name="kampanya_id" value="<?php echo (int) $id; ?>">
            <?php /* Hangi butona basıldığına göre JS 0/1 yazar; varsayılan
                     "yalnızca kaydet"tir. Aktif kampanyada tek buton vardır. */ ?>
            <input type="hidden" name="uygula" id="rma-kmp-uygula" value="<?php echo $aktif_mi ? '1' : '0'; ?>">

            <div class="rma-card">
                <h2 class="rma-card-title">1. Kampanya Adı</h2>
                <p class="rma-card-desc">Yalnızca yönetim panelinde görünür; kampanyaları birbirinden ayırmanız için.</p>
                <input type="text" name="title" class="regular-text" value="<?php echo esc_attr( $deger['title'] ); ?>" placeholder="Örn. Ocak Zammı, Hafta Sonu İndirimi">
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">2. Ne Yapılsın?</h2>
                <p class="rma-card-desc">Fiyatlar yüzde olarak mı yoksa sabit bir tutar olarak mı değişsin?</p>

                <div class="rma-kmp-secim">
                    <label class="rma-kmp-choice<?php echo 'increase' === $deger['direction'] ? ' is-selected' : ''; ?>">
                        <input type="radio" name="direction" value="increase" <?php checked( 'increase', $deger['direction'] ); ?>>
                        <span class="rma-kmp-choice-ic">📈 Zam</span>
                    </label>
                    <label class="rma-kmp-choice<?php echo 'decrease' === $deger['direction'] ? ' is-selected' : ''; ?>">
                        <input type="radio" name="direction" value="decrease" <?php checked( 'decrease', $deger['direction'] ); ?>>
                        <span class="rma-kmp-choice-ic">📉 İndirim</span>
                    </label>
                </div>

                <div class="rma-kmp-secim">
                    <label class="rma-kmp-choice<?php echo 'percent' === $deger['calc_type'] ? ' is-selected' : ''; ?>">
                        <input type="radio" name="calc_type" value="percent" <?php checked( 'percent', $deger['calc_type'] ); ?>>
                        <span class="rma-kmp-choice-ic">% Yüzde bazlı</span>
                    </label>
                    <label class="rma-kmp-choice<?php echo 'fixed' === $deger['calc_type'] ? ' is-selected' : ''; ?>">
                        <input type="radio" name="calc_type" value="fixed" <?php checked( 'fixed', $deger['calc_type'] ); ?>>
                        <span class="rma-kmp-choice-ic">₺ Sabit tutar</span>
                    </label>
                </div>

                <table class="form-table rma-form-table">
                    <tr>
                        <th><label for="rma-kmp-amount">Değer</label></th>
                        <td>
                            <div class="rma-kmp-amount-row">
                                <input type="text" inputmode="decimal" name="amount" id="rma-kmp-amount"
                                       value="<?php echo esc_attr( $deger['amount'] ); ?>" placeholder="10">
                                <span class="rma-kmp-birim" id="rma-kmp-birim"><?php echo 'fixed' === $deger['calc_type'] ? '₺' : '%'; ?></span>
                            </div>
                            <p class="description rma-desc">Yüzde en fazla <?php echo (int) RMA_Kampanya_DB::MAX_YUZDE; ?> olabilir. Eksi değer yazmanıza gerek yok — yönü yukarıdan seçiyorsunuz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rma-kmp-rounding">Yuvarlama</label></th>
                        <td>
                            <select name="rounding" id="rma-kmp-rounding" class="rma-select-wide">
                                <?php foreach ( RMA_Kampanya_DB::yuvarlama_secenekleri() as $anahtar => $etiket ) : ?>
                                    <option value="<?php echo esc_attr( $anahtar ); ?>" <?php selected( $deger['rounding'], $anahtar ); ?>><?php echo esc_html( $etiket ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description rma-desc">Hesap sonucu küsuratlı çıktığında menüde nasıl görünsün?</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">3. Hangi Ürünler?</h2>

                <div class="rma-kmp-secim rma-kmp-kapsam">
                    <?php
                    $kapsamlar = array(
                        'all'      => array( '🍽️', 'Menüdeki tüm ürünler' ),
                        'category' => array( '🗂️', 'Belirli kategori(ler)' ),
                        'manual'   => array( '✅', 'Tek tek seçtiklerim' ),
                    );
                    foreach ( $kapsamlar as $anahtar => $bilgi ) :
                        ?>
                        <label class="rma-kmp-choice<?php echo $anahtar === $deger['scope_type'] ? ' is-selected' : ''; ?>">
                            <input type="radio" name="scope_type" value="<?php echo esc_attr( $anahtar ); ?>" <?php checked( $anahtar, $deger['scope_type'] ); ?>>
                            <span class="rma-kmp-choice-ic"><?php echo esc_html( $bilgi[0] . ' ' . $bilgi[1] ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="rma-kmp-kapsam-kutu" data-kapsam="category"<?php echo 'category' === $deger['scope_type'] ? '' : ' style="display:none;"'; ?>>
                    <?php if ( empty( $kategoriler ) ) : ?>
                        <p class="rma-empty">Henüz kategori yok.</p>
                    <?php else : ?>
                        <div class="rma-kmp-kategori-liste">
                            <?php foreach ( $kategoriler as $terim ) : ?>
                                <label class="rma-kmp-kategori">
                                    <input type="checkbox" class="rma-kmp-kat-cb" value="<?php echo (int) $terim->term_id; ?>"
                                        <?php checked( in_array( (int) $terim->term_id, $deger['scope_ids'], true ) ); ?>>
                                    <span><?php echo esc_html( $terim->name ); ?> <em>(<?php echo (int) $terim->count; ?>)</em></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rma-kmp-kapsam-kutu" data-kapsam="manual"<?php echo 'manual' === $deger['scope_type'] ? '' : ' style="display:none;"'; ?>>
                    <?php if ( empty( $urunler ) ) : ?>
                        <p class="rma-empty">Yayında ürün yok. Önce "Ürün Ekle" sayfasından ürün ekleyin.</p>
                    <?php else : ?>
                        <label class="rma-vitrin-search-label" for="rma-kmp-search">Ürün ara</label>
                        <input type="search" id="rma-kmp-search" class="rma-vitrin-search" placeholder="Ürün adı yazın…">

                        <div class="rma-vitrin-pool-list">
                            <?php
                            foreach ( $urunler as $urun ) :
                                $secili = in_array( $urun['id'], $deger['scope_ids'], true );
                                ?>
                                <label class="rma-vitrin-pool-row<?php echo $secili ? ' is-selected' : ''; ?>" data-title="<?php echo esc_attr( $urun['title'] ); ?>">
                                    <input type="checkbox" class="rma-kmp-urun-cb" value="<?php echo (int) $urun['id']; ?>" <?php checked( $secili ); ?>>
                                    <span class="rma-vitrin-pool-main">
                                        <span class="rma-vitrin-pool-title"><?php echo esc_html( $urun['title'] ); ?></span>
                                        <span class="rma-vitrin-pool-cat"><?php echo esc_html( $urun['category'] ); ?><?php echo '' !== $urun['fiyat'] ? ' · ' . esc_html( $urun['fiyat'] ) . ' ₺' : ''; ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php /* Kapsam listesi JS tarafından bu alana yazılır. */ ?>
                <input type="hidden" name="scope_ids" id="rma-kmp-scope-ids" value="<?php echo esc_attr( implode( ',', $deger['scope_ids'] ) ); ?>">
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">4. Müşteri Ne Görsün?</h2>
                <label class="rma-check-row">
                    <input type="checkbox" name="show_old_price" value="1" <?php checked( 1, $deger['show_old_price'] ); ?>>
                    <span>Eski fiyat üstü çizili olarak görünsün</span>
                </label>
                <p class="description rma-desc">İndirim kampanyalarında ayrıca ürün kartına indirim rozeti eklenir.</p>
            </div>

            <div class="rma-card" id="rma-kmp-onizleme-kart">
                <h2 class="rma-card-title">5. Önizleme <span class="rma-kmp-zorunlu">— uygulamadan önce zorunlu</span></h2>
                <p class="rma-card-desc">Hangi üründe fiyatın kaç liraya çıkacağını burada görün. Kampanyayı başlatma butonu, önizlemeyi görene kadar kapalı kalır.</p>

                <p>
                    <button type="button" class="button button-secondary" id="rma-kmp-onizle">Önizlemeyi Göster</button>
                    <span class="spinner rma-kmp-spinner"></span>
                </p>

                <div id="rma-kmp-onizleme"></div>
            </div>

            <p class="submit rma-kmp-submit">
                <?php
                // ÇALIŞAN kampanyada "yalnızca kaydet" YOKTUR: kayıt aynı anda
                // canlı fiyatı da değiştireceği için her kaydetme önizlemeden
                // geçmeli ve etkilenen ürün fotoğrafı tazelenmelidir.
                if ( ! $aktif_mi ) :
                    ?>
                    <button type="submit" class="button" id="rma-kmp-kaydet">Yalnızca Kaydet</button>
                <?php endif; ?>
                <button type="submit" class="button button-primary" id="rma-kmp-uygula-btn" disabled>
                    <?php echo $aktif_mi ? 'Kaydet ve Güncelle' : 'Kampanyayı Başlat'; ?>
                </button>
                <a class="button" href="<?php echo esc_url( $this->kampanya_url() ); ?>">Vazgeç</a>
            </p>
        </form>
        <?php
        $this->page_footer();
    }

    /**
     * Seçilebilecek ürünler (manuel kapsam listesi).
     *
     * Sorgu deseni vitrin ekranının aynısı: post/meta/terim cache'leri toplu
     * ısıtılır, ürün başına ek sorgu doğmaz.
     *
     * @return array<int,array{id:int,title:string,category:string,fiyat:string}>
     */
    private function kampanya_urun_listesi() {
        $liste = array();

        foreach ( $this->kampanya_urun_sorgusu() as $post ) {
            $terimler = get_the_terms( $post->ID, 'rma_category' );
            $kategori = ( ! empty( $terimler ) && ! is_wp_error( $terimler ) ) ? reset( $terimler )->name : 'Kategorisiz';

            $liste[] = array(
                'id'       => (int) $post->ID,
                'title'    => $post->post_title,
                'category' => $kategori,
                'fiyat'    => (string) get_post_meta( $post->ID, 'rma_price', true ),
            );
        }

        return $liste;
    }

    /**
     * Kampanyanın dokunabileceği tüm yayındaki ürünler.
     *
     * @return WP_Post[]
     */
    private function kampanya_urun_sorgusu() {
        $sorgu = new WP_Query(
            array(
                'post_type'              => 'rma_menu_item',
                'post_status'            => 'publish',
                'posts_per_page'         => (int) apply_filters( 'rma_max_menu_items', 800 ),
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => true,
                'orderby'                => 'title',
                'order'                  => 'ASC',
            )
        );

        return $sorgu->posts;
    }

    /* -----------------------------------------------------------------
       ÖNİZLEME
       Kaydetme ile AYNI saf hesaplayıcıyı kullanır: önizlemede görülen fiyat
       ile menüde çıkan fiyat tanım gereği aynıdır.
    ----------------------------------------------------------------- */

    /**
     * Kampanya kuralının tüm menüye etkisi.
     *
     * @param array $ayarlar ayarlari_temizle() çıktısı.
     * @return array{satirlar:array,toplam:int,etkilenen:int,uyari:int}
     */
    private function kampanya_etkilenenler( array $ayarlar ) {
        $satirlar  = array();
        $toplam    = 0;
        $etkilenen = 0;
        $uyari     = 0;

        foreach ( $this->kampanya_urun_sorgusu() as $post ) {
            ++$toplam;

            $id       = (int) $post->ID;
            $terimler = get_the_terms( $id, 'rma_category' );
            $terimler = ( is_wp_error( $terimler ) || ! $terimler ) ? array() : $terimler;

            $kat_idler = array();
            foreach ( $terimler as $t ) {
                $kat_idler[] = (int) $t->term_id;
            }

            if ( ! RMA_Kampanya_DB::kapsamda_mi( $id, $ayarlar, $kat_idler ) ) {
                continue;
            }

            $taban    = RMA_Kampanya::taban_fiyat( $id );
            $orijinal = is_numeric( $taban['ham'] ) ? (float) $taban['ham'] : null;
            $yeni     = ( null === $orijinal ) ? null : RMA_Kampanya_DB::yeni_fiyat( $orijinal, $ayarlar );

            if ( null === $orijinal ) {
                $durum = 'fiyat_yok';
                ++$uyari;
            } elseif ( null === $yeni || abs( $yeni - $orijinal ) < 0.005 ) {
                $durum = 'degismez';
            } elseif ( $yeni <= 0 ) {
                $durum = 'sifir';
                ++$uyari;
                ++$etkilenen;
            } else {
                $durum = 'ok';
                ++$etkilenen;
            }

            $satirlar[] = array(
                'id'       => $id,
                'title'    => $post->post_title,
                'category' => $terimler ? reset( $terimler )->name : 'Kategorisiz',
                'orijinal' => $orijinal,
                'yeni'     => $yeni,
                'durum'    => $durum,
                'kombin'   => $taban['kombin'],
            );
        }

        return array(
            'satirlar'  => $satirlar,
            'toplam'    => $toplam,
            'etkilenen' => $etkilenen,
            'uyari'     => $uyari,
        );
    }

    /**
     * `wp_ajax_rma_kampanya_onizleme` — kaydedilmemiş form değerleriyle önizleme.
     *
     * @return void
     */
    public function ajax_kampanya_onizleme() {
        check_ajax_referer( 'rma_admin_nonce', 'nonce' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_send_json_error( 'yetki' );
        }

        $ayarlar = RMA_Kampanya_DB::ayarlari_temizle( $this->kampanya_form_verisi() );

        if ( $ayarlar['amount'] <= 0 ) {
            wp_send_json_success(
                array(
                    'html'      => '<p class="rma-kmp-bayat">' . esc_html( 'Lütfen bir indirim/zam değeri girin' ) . '</p>',
                    'etkilenen' => 0,
                )
            );
        }

        $sonuc = $this->kampanya_etkilenenler( $ayarlar );

        wp_send_json_success(
            array(
                'html'      => $this->kampanya_onizleme_html( $ayarlar, $sonuc ),
                'etkilenen' => $sonuc['etkilenen'],
            )
        );
    }

    /**
     * Önizleme tablosunun HTML'i.
     *
     * @param array $ayarlar Temizlenmiş kampanya ayarları.
     * @param array $sonuc   kampanya_etkilenenler() çıktısı.
     * @return string
     */
    private function kampanya_onizleme_html( array $ayarlar, array $sonuc ) {
        ob_start();

        $etiket = array(
            'ok'        => '',
            'degismez'  => 'Fiyat değişmiyor',
            'fiyat_yok' => 'Fiyat girilmemiş — kampanya dışı',
            'sifir'     => 'Fiyat 0 ₺ olur!',
        );
        ?>
        <div class="rma-kmp-ozet-kutu<?php echo $sonuc['uyari'] ? ' has-uyari' : ''; ?>">
            <strong><?php echo (int) $sonuc['toplam']; ?> üründen <?php echo (int) $sonuc['etkilenen']; ?> tanesi</strong>
            <?php echo esc_html( RMA_Kampanya_DB::kural_metni( $ayarlar ) ); ?> kuralından etkilenecek.
            <?php if ( $sonuc['uyari'] ) : ?>
                <span class="rma-kmp-uyari-metin"><?php echo (int) $sonuc['uyari']; ?> üründe dikkat edilmesi gereken bir durum var (aşağıda işaretli).</span>
            <?php endif; ?>
        </div>

        <?php if ( empty( $sonuc['satirlar'] ) ) : ?>
            <p class="rma-empty">Bu kapsamda ürün yok. Kapsam seçiminizi gözden geçirin.</p>
        <?php else : ?>
            <div class="rma-kmp-onizleme-kaydir">
                <table class="rma-kmp-tablo rma-kmp-onizleme-tablo">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th>Eski fiyat</th>
                            <th>Yeni fiyat</th>
                            <th>Fark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sonuc['satirlar'] as $satir ) : ?>
                            <tr class="durum-<?php echo esc_attr( $satir['durum'] ); ?>">
                                <td data-label="Ürün">
                                    <?php echo esc_html( $satir['title'] ); ?>
                                    <?php if ( $satir['kombin'] ) : ?><span class="rma-kmp-etiket">kombin</span><?php endif; ?>
                                </td>
                                <td data-label="Kategori"><?php echo esc_html( $satir['category'] ); ?></td>
                                <td data-label="Eski fiyat">
                                    <?php echo null === $satir['orijinal'] ? '—' : esc_html( RMA_Kampanya_DB::bicimle( $satir['orijinal'] ) ) . ' ₺'; ?>
                                </td>
                                <td data-label="Yeni fiyat">
                                    <strong><?php echo null === $satir['yeni'] ? '—' : esc_html( RMA_Kampanya_DB::bicimle( $satir['yeni'] ) ) . ' ₺'; ?></strong>
                                </td>
                                <td data-label="Fark">
                                    <?php
                                    if ( '' !== $etiket[ $satir['durum'] ] ) {
                                        echo '<span class="rma-kmp-not">' . esc_html( $etiket[ $satir['durum'] ] ) . '</span>';
                                    } else {
                                        $fark = $satir['yeni'] - $satir['orijinal'];
                                        echo esc_html( ( $fark > 0 ? '+' : '−' ) . RMA_Kampanya_DB::bicimle( abs( $fark ) ) . ' ₺' );
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif;

        return ob_get_clean();
    }

    /* -----------------------------------------------------------------
       KAYDETME / AKTİFLEŞTİRME / GERİ ALMA
    ----------------------------------------------------------------- */

    /**
     * Form/AJAX gövdesinden ham kampanya alanlarını toplar.
     *
     * @return array
     */
    private function kampanya_form_verisi() {
        return array(
            'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'calc_type'      => isset( $_POST['calc_type'] ) ? sanitize_key( wp_unslash( $_POST['calc_type'] ) ) : '',
            'direction'      => isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : '',
            'amount'         => isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '',
            'rounding'       => isset( $_POST['rounding'] ) ? sanitize_key( wp_unslash( $_POST['rounding'] ) ) : '',
            'scope_type'     => isset( $_POST['scope_type'] ) ? sanitize_key( wp_unslash( $_POST['scope_type'] ) ) : '',
            'scope_ids'      => isset( $_POST['scope_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['scope_ids'] ) ) : '',
            'show_old_price' => isset( $_POST['show_old_price'] ) ? wp_unslash( $_POST['show_old_price'] ) : 0,
        );
    }

    /**
     * `admin_post_rma_kampanya_kaydet` — kaydeder, istenirse uygular.
     *
     * @return void
     */
    public function handle_kampanya_save() {
        $this->kampanya_yetki_kontrol();

        $id      = isset( $_POST['kampanya_id'] ) ? absint( wp_unslash( $_POST['kampanya_id'] ) ) : 0;
        $uygula  = isset( $_POST['uygula'] ) && '1' === (string) wp_unslash( $_POST['uygula'] );
        $ayarlar = RMA_Kampanya_DB::ayarlari_temizle( $this->kampanya_form_verisi() );

        $kayit_id = RMA_Kampanya_DB::kaydet( $id, $ayarlar );

        if ( ! $kayit_id ) {
            wp_safe_redirect( $this->kampanya_url( array( 'kmp_msg' => 'hata' ) ) );
            exit;
        }

        if ( $uygula ) {
            $this->kampanya_uygula( $kayit_id, $ayarlar );
        }

        $this->kampanya_sonrasi_temizlik();

        wp_safe_redirect(
            $this->kampanya_url( array( 'kmp_msg' => $uygula ? 'uygulandi' : 'kaydedildi' ) )
        );
        exit;
    }

    /**
     * Kampanyayı aktifleştirir ve aktivasyon anının fiyat fotoğrafını çeker.
     *
     * Fotoğraf ve `_qrms_orijinal_fiyat` yedeği fiyatı DEĞİŞTİRMEZ; ikisi de
     * denetim/kurtarma amaçlıdır. Menüde görünen fiyat her zaman orijinal +
     * kural hesabından gelir.
     *
     * @param int   $kayit_id Kampanya ID.
     * @param array $ayarlar  Temizlenmiş ayarlar.
     * @return void
     */
    private function kampanya_uygula( $kayit_id, array $ayarlar ) {
        $sonuc  = $this->kampanya_etkilenenler( $ayarlar );
        $anlik  = array();

        foreach ( $sonuc['satirlar'] as $satir ) {
            if ( null === $satir['orijinal'] ) {
                continue;
            }

            $anlik[ $satir['id'] ] = $satir['orijinal'];

            // Write-once: orijinal fiyat yedeği bir kez yazılır, ASLA
            // üzerine yazılmaz. Fiyat verisiyle oynayan ikinci güvence katmanı.
            if ( '' === (string) get_post_meta( $satir['id'], RMA_Kampanya_DB::ORIJINAL_META, true ) ) {
                update_post_meta( $satir['id'], RMA_Kampanya_DB::ORIJINAL_META, $satir['orijinal'] );
            }
        }

        RMA_Kampanya_DB::anlik_yaz( $kayit_id, $anlik );
        RMA_Kampanya_DB::aktiflestir( $kayit_id, $sonuc['etkilenen'] );
    }

    /**
     * `admin_post_rma_kampanya_geri_al` — kampanyayı kapatır.
     *
     * @return void
     */
    public function handle_kampanya_undo() {
        $this->kampanya_yetki_kontrol();

        $id = isset( $_POST['kampanya_id'] ) ? absint( wp_unslash( $_POST['kampanya_id'] ) ) : 0;

        if ( $id ) {
            RMA_Kampanya_DB::pasiflestir( $id );
        }

        $this->kampanya_sonrasi_temizlik();

        wp_safe_redirect( $this->kampanya_url( array( 'kmp_msg' => 'geri' ) ) );
        exit;
    }

    /**
     * `admin_post_rma_kampanya_sil` — kampanya kaydını siler.
     *
     * @return void
     */
    public function handle_kampanya_delete() {
        $this->kampanya_yetki_kontrol();

        $id = isset( $_POST['kampanya_id'] ) ? absint( wp_unslash( $_POST['kampanya_id'] ) ) : 0;

        if ( $id ) {
            RMA_Kampanya_DB::sil( $id );
        }

        $this->kampanya_sonrasi_temizlik();

        wp_safe_redirect( $this->kampanya_url( array( 'kmp_msg' => 'silindi' ) ) );
        exit;
    }

    /**
     * Kampanya değişikliğinden sonra menü önbelleğini ve istek içi belleği tazeler.
     *
     * @return void
     */
    private function kampanya_sonrasi_temizlik() {
        RMA_Kampanya::bellegi_bosalt();
        $this->force_bump_cache_version();
    }

    /**
     * Nonce ve yetki kontrolü — üç handler'ın ortak girişi.
     *
     * @return void
     */
    private function kampanya_yetki_kontrol() {
        check_admin_referer( $this->kampanya_nonce_action );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
        }
    }
}
