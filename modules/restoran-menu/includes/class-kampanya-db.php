<?php
/**
 * Toplu Fiyat Kampanyası — tablo şeması, saf hesap ve veri erişimi.
 *
 * Bu sınıf ÜRÜN FİYATINA HİÇ DOKUNMAZ: `rma_price` (ve kombin ürünlerde
 * `_qmo_kombin_fiyat`) sonsuza dek orijinal fiyattır. Kampanya yalnızca bir
 * KURAL kaydıdır; gösterilecek fiyat her render'da orijinal fiyat + kural
 * birleştirilerek hesaplanır (bkz. class-kampanya.php). Geri alma bu yüzden
 * bir hesaplama değil, tek bir durum değişikliğidir.
 *
 * Şema, modülün mevcut "ana tablo + alt tablo" desenini izler
 * (bkz. class-vitrin-db.php).
 *
 * Dosyanın "SAF HESAP" bölümündeki fonksiyonların WordPress'e hiçbir
 * bağımlılığı yoktur — hem yönetim önizlemesi hem ön yüz render'ı hem de
 * testler aynı fonksiyonları çağırır, dolayısıyla önizlemede görülen fiyat
 * ile menüde çıkan fiyat tanım gereği aynıdır.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RMA_Kampanya_DB' ) ) :

class RMA_Kampanya_DB {

    /** Şema sürümü. Değişince tablolar dbDelta ile tazelenir. */
    const DB_VERSION = '1.0.0';

    /** Sürümün saklandığı option adı. */
    const VERSION_OPTION = 'rma_kampanya_db_version';

    /** Orijinal fiyatın yedeklendiği post meta anahtarı (write-once). */
    const ORIJINAL_META = '_qrms_orijinal_fiyat';

    /** Tutar sınırları — hem form hem sanitize burayı kullanır. */
    const MAX_YUZDE = 90;
    const MAX_TUTAR = 100000;

    /** Aktif kampanyanın saklandığı transient. */
    const AKTIF_TRANSIENT = 'rma_kampanya_aktif';

    /** Aktif kampanya önbelleğinin ömrü. */
    const AKTIF_TTL = 5 * MINUTE_IN_SECONDS;

    /**
     * Kampanya tanımları tablosu.
     *
     * @return string
     */
    public static function tablo() {
        global $wpdb;
        return $wpdb->prefix . 'rma_price_campaigns';
    }

    /**
     * Aktivasyon anındaki fiyat fotoğrafı tablosu.
     *
     * @return string
     */
    public static function anlik_tablo() {
        global $wpdb;
        return $wpdb->prefix . 'rma_price_campaign_snapshot';
    }

    /**
     * Tabloları oluşturur/tazeler.
     *
     * Suite modüllerinin kendi register_activation_hook'u yoktur (loader
     * plugins_loaded'da yükler), bu yüzden vitrin tablosundaki gibi sürüm
     * guard'ıyla admin_init'ten çağrılır.
     *
     * @return void
     */
    public static function tablo_kur() {
        global $wpdb;

        $tablo   = self::tablo();
        $anlik   = self::anlik_tablo();
        $collate = $wpdb->get_charset_collate();

        // starts_at/ends_at/daily_*/days_mask sütunları İKİNCİ FAZ içindir
        // (zamanlanmış kampanya, Happy Hour, gün bazlı). v1'de yazılmazlar;
        // değerlendirici (aktif_mi) boş değeri "her zaman geçerli" sayar.
        $sql_kampanya = "CREATE TABLE {$tablo} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            calc_type varchar(10) NOT NULL DEFAULT 'percent',
            direction varchar(10) NOT NULL DEFAULT 'increase',
            amount decimal(10,2) NOT NULL DEFAULT 0,
            rounding varchar(12) NOT NULL DEFAULT 'none',
            scope_type varchar(10) NOT NULL DEFAULT 'all',
            scope_ids longtext NULL,
            show_old_price tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'passive',
            starts_at datetime NULL DEFAULT NULL,
            ends_at datetime NULL DEFAULT NULL,
            daily_start time NULL DEFAULT NULL,
            daily_end time NULL DEFAULT NULL,
            days_mask tinyint(3) unsigned NOT NULL DEFAULT 0,
            applied_at datetime NULL DEFAULT NULL,
            ended_at datetime NULL DEFAULT NULL,
            affected_count int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status_idx (status)
        ) {$collate};";

        // UNIQUE campaign_product: bir ürünün bir kampanyada tek fotoğrafı olur.
        $sql_anlik = "CREATE TABLE {$anlik} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            original_price decimal(12,2) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_product (campaign_id, product_id)
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_kampanya );
        dbDelta( $sql_anlik );

        // Autoload açık: ön yüzde her istekte "tablo kuruldu mu?" diye
        // bakılır (bkz. RMA_Kampanya::ham_aktif), autoload sayesinde bu
        // kontrol ek sorgu doğurmaz.
        update_option( self::VERSION_OPTION, self::DB_VERSION, true );
    }

    /**
     * Şema güncel değilse kurar. admin_init'ten çağrılır.
     *
     * @return void
     */
    public static function belki_kur() {
        if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
            return;
        }

        self::tablo_kur();
    }

    /* =================================================================
       SAF HESAP
       WordPress'e bağımlılığı olmayan dönüşümler — doğrudan test edilir.
    ================================================================= */

    /**
     * Kampanya varsayılanları.
     *
     * @return array<string,mixed>
     */
    public static function varsayilanlar() {
        return array(
            'title'          => '',
            'calc_type'      => 'percent',
            'direction'      => 'increase',
            'amount'         => 0.0,
            'rounding'       => 'none',
            'scope_type'     => 'all',
            'scope_ids'      => '',
            'show_old_price' => 1,
        );
    }

    /**
     * Geçerli yuvarlama modları — anahtar => yönetimdeki etiket.
     *
     * @return array<string,string>
     */
    public static function yuvarlama_secenekleri() {
        return array(
            'none'  => 'Yuvarlama yok (kuruşuna kadar)',
            'half'  => 'En yakın 0,50 ₺',
            'whole' => 'En yakın 1 ₺',
            'end90' => 'Psikolojik fiyat — ,90 ile bitir',
            'end99' => 'Psikolojik fiyat — ,99 ile bitir',
        );
    }

    /**
     * Ham form girdisini şemaya uygun, sınırlanmış değerlere çevirir.
     *
     * Fiyatla oynayan bir özellik olduğu için sınırlar bilinçli olarak dar:
     * yüzde en fazla %90 (bir kalemde menüyü uçurmayı önler), sabit tutar en
     * fazla 100.000 ₺. Tanınmayan seçim değerleri sessizce varsayılana düşer.
     *
     * @param array $ham Ham `$_POST` benzeri dizi.
     * @return array<string,mixed>
     */
    public static function ayarlari_temizle( array $ham ) {
        $v = self::varsayilanlar();

        $baslik = isset( $ham['title'] ) ? trim( (string) $ham['title'] ) : '';

        $tur = isset( $ham['calc_type'] ) && 'fixed' === $ham['calc_type'] ? 'fixed' : 'percent';
        $yon = isset( $ham['direction'] ) && 'decrease' === $ham['direction'] ? 'decrease' : 'increase';

        $kapsam = isset( $ham['scope_type'] ) ? (string) $ham['scope_type'] : $v['scope_type'];
        if ( ! in_array( $kapsam, array( 'all', 'category', 'manual' ), true ) ) {
            $kapsam = $v['scope_type'];
        }

        $yuvarlama = isset( $ham['rounding'] ) ? (string) $ham['rounding'] : $v['rounding'];
        if ( ! array_key_exists( $yuvarlama, self::yuvarlama_secenekleri() ) ) {
            $yuvarlama = $v['rounding'];
        }

        $tutar = isset( $ham['amount'] ) ? self::tutar_temizle( $ham['amount'] ) : 0.0;
        $ust   = ( 'percent' === $tur ) ? self::MAX_YUZDE : self::MAX_TUTAR;
        $tutar = (float) min( $ust, $tutar );

        // Kapsam listesi yalnızca kendi kapsam türünde anlamlıdır; tür
        // değişince eski liste taşınmasın diye burada temizlenir.
        $idler = ( 'all' === $kapsam ) ? array() : self::id_listesi_temizle( $ham['scope_ids'] ?? '' );

        return array(
            'title'          => '' !== $baslik ? $baslik : 'Fiyat Kampanyası',
            'calc_type'      => $tur,
            'direction'      => $yon,
            'amount'         => $tutar,
            'rounding'       => $yuvarlama,
            'scope_type'     => $kapsam,
            'scope_ids'      => implode( ',', $idler ),
            'show_old_price' => self::bayrak( $ham['show_old_price'] ?? 0 ),
        );
    }

    /**
     * Tutar girdisini pozitif ondalığa çevirir.
     *
     * Türkçe klavyeden "12,5" gelmesi olağan: virgül ondalık ayırıcı sayılır.
     * Negatif değer mutlak değerine çekilir — yön ayrı alanda (direction)
     * tutulur, iki yerden birden gelen işaret çelişki üretirdi.
     *
     * @param mixed $ham Ham değer.
     * @return float
     */
    public static function tutar_temizle( $ham ) {
        if ( is_string( $ham ) ) {
            $ham = str_replace( array( ' ', "\xc2\xa0" ), '', $ham );
            $ham = str_replace( ',', '.', $ham );
        }

        if ( ! is_numeric( $ham ) ) {
            return 0.0;
        }

        return round( abs( (float) $ham ), 2 );
    }

    /**
     * Checkbox girdisini 0/1'e çevirir.
     *
     * @param mixed $deger Ham değer.
     * @return int
     */
    public static function bayrak( $deger ) {
        return ( '1' === (string) $deger || 1 === $deger || true === $deger || 'on' === $deger || 'yes' === $deger ) ? 1 : 0;
    }

    /**
     * ID listesini temizler: pozitif tamsayı, tekrarsız, sırası korunur.
     *
     * @param mixed $ham Virgülle ayrılmış dize ya da dizi.
     * @return int[]
     */
    public static function id_listesi_temizle( $ham ) {
        if ( is_string( $ham ) ) {
            $ham = '' === trim( $ham ) ? array() : explode( ',', $ham );
        }

        if ( ! is_array( $ham ) ) {
            return array();
        }

        $temiz = array();

        foreach ( $ham as $id ) {
            $id = (int) $id;

            if ( $id > 0 && ! in_array( $id, $temiz, true ) ) {
                $temiz[] = $id;
            }
        }

        return $temiz;
    }

    /**
     * Kampanya kuralını orijinal fiyata uygular.
     *
     * Fiyat 0'ın altına inemez: -10 ₺'lik bir indirim 5 ₺'lik üründe 0 verir
     * (negatif fiyat menüde anlamsız, önizlemede uyarı ile işaretlenir).
     *
     * @param mixed $taban Orijinal fiyat.
     * @param array $kural calc_type/direction/amount/rounding taşıyan dizi.
     * @return float|null Uygulanamıyorsa null (fiyat yok ya da tutar sıfır).
     */
    public static function yeni_fiyat( $taban, array $kural ) {
        if ( ! is_numeric( $taban ) ) {
            return null;
        }

        $tutar = self::tutar_temizle( $kural['amount'] ?? 0 );

        if ( $tutar <= 0 ) {
            return null;
        }

        $taban = (float) $taban;
        $isaret = ( 'decrease' === ( $kural['direction'] ?? 'increase' ) ) ? -1 : 1;

        if ( 'fixed' === ( $kural['calc_type'] ?? 'percent' ) ) {
            $yeni = $taban + $isaret * $tutar;
        } else {
            $yeni = $taban * ( 1 + $isaret * $tutar / 100 );
        }

        $yeni = self::yuvarla( $yeni, (string) ( $kural['rounding'] ?? 'none' ) );

        return max( 0.0, $yeni );
    }

    /**
     * Hesaplanan fiyatı seçilen moda göre yuvarlar.
     *
     * @param float  $deger Ham hesap sonucu.
     * @param string $mod   yuvarlama_secenekleri() anahtarı.
     * @return float
     */
    public static function yuvarla( $deger, $mod ) {
        $deger = (float) $deger;

        switch ( $mod ) {
            case 'half':
                return round( round( $deger * 2 ) / 2, 2 );

            case 'whole':
                return round( $deger );

            case 'end90':
                return self::sonu_sabit( $deger, 0.90 );

            case 'end99':
                return self::sonu_sabit( $deger, 0.99 );

            case 'none':
            default:
                return round( $deger, 2 );
        }
    }

    /**
     * Değeri kuruş kısmı sabit olan en yakın fiyata çeker (,90 / ,99).
     *
     * Üç aday denenir (bir alt, aynı, bir üst tam sayı) ve en yakını seçilir;
     * eşitlikte küçük olan kazanır. Böylece 52,25 → 52,90 olurken 52,95 →
     * 52,90'a iner, yani mod her zaman yukarı yuvarlamaz.
     *
     * @param float $deger Ham değer.
     * @param float $son   Sabit kuruş kısmı (0.90 / 0.99).
     * @return float
     */
    private static function sonu_sabit( $deger, $son ) {
        $tam    = floor( $deger );
        $secim  = null;
        $enYakin = null;

        foreach ( array( $tam - 1, $tam, $tam + 1 ) as $aday_tam ) {
            $aday = $aday_tam + $son;
            $fark = abs( $deger - $aday );

            if ( null === $enYakin || $fark < $enYakin ) {
                $enYakin = $fark;
                $secim   = $aday;
            }
        }

        return round( (float) $secim, 2 );
    }

    /**
     * Ürün kampanyanın kapsamında mı?
     *
     * Kapsam her render'da CANLI çözülür (aktivasyon anında dondurulmaz):
     * "tüm menü" ya da bir kategori seçiliyken sonradan eklenen ürün de
     * kampanyaya dahil olur.
     *
     * @param int   $urun_id       Ürün ID.
     * @param array $kampanya      scope_type + scope_ids taşıyan dizi.
     * @param int[] $kategori_idler Ürünün rma_category terim ID'leri.
     * @return bool
     */
    public static function kapsamda_mi( $urun_id, array $kampanya, array $kategori_idler = array() ) {
        $tur = $kampanya['scope_type'] ?? 'all';

        if ( 'all' === $tur ) {
            return true;
        }

        $idler = self::id_listesi_temizle( $kampanya['scope_ids'] ?? '' );

        if ( empty( $idler ) ) {
            return false;
        }

        if ( 'manual' === $tur ) {
            return in_array( (int) $urun_id, $idler, true );
        }

        if ( 'category' === $tur ) {
            foreach ( $kategori_idler as $terim_id ) {
                if ( in_array( (int) $terim_id, $idler, true ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Kampanya verilen anda geçerli mi?
     *
     * v1'de yalnızca `status` dalı çalışır. Zaman alanları İKİNCİ FAZ için
     * baştan değerlendiriliyor: boş alan "sınır yok" demektir, dolayısıyla
     * bugünkü davranış değişmez ama Faz 2'de yalnızca form alanları eklemek
     * yetecek — render ve önbellek tarafına dokunulmayacak.
     *
     * $zaman, `current_time( 'timestamp' )` gibi YEREL duvar saatini veren bir
     * damgadır; biçimlendirme gmdate ile yapılır ki testler makinenin saat
     * diliminden etkilenmesin.
     *
     * @param array|object $kampanya Kampanya kaydı.
     * @param int|null     $zaman    Unix damgası (yerel duvar saati).
     * @return bool
     */
    public static function aktif_mi( $kampanya, $zaman = null ) {
        $k = (array) $kampanya;

        if ( empty( $k ) || 'active' !== ( $k['status'] ?? '' ) ) {
            return false;
        }

        if ( null === $zaman ) {
            $zaman = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();
        }

        $zaman = (int) $zaman;

        $basla = self::damga( $k['starts_at'] ?? '' );
        if ( null !== $basla && $zaman < $basla ) {
            return false;
        }

        $bitis = self::damga( $k['ends_at'] ?? '' );
        if ( null !== $bitis && $zaman > $bitis ) {
            return false;
        }

        // Gün maskesi: bit 0 = Pazar … bit 6 = Cumartesi. 0 = her gün.
        $maske = (int) ( $k['days_mask'] ?? 0 );
        if ( $maske > 0 ) {
            $gun = (int) gmdate( 'w', $zaman );

            if ( 0 === ( $maske & ( 1 << $gun ) ) ) {
                return false;
            }
        }

        return self::saat_araliginda_mi(
            (string) gmdate( 'H:i:s', $zaman ),
            (string) ( $k['daily_start'] ?? '' ),
            (string) ( $k['daily_end'] ?? '' )
        );
    }

    /**
     * Günlük saat aralığı kontrolü (Happy Hour — İkinci Faz).
     *
     * Aralık gece yarısını aşabilir: 22:00-02:00 girildiğinde 23:30 da
     * 01:00 da aralık içidir.
     *
     * @param string $simdi Şu anki saat (H:i:s).
     * @param string $basla Aralık başı (boş = sınır yok).
     * @param string $bitis Aralık sonu (boş = sınır yok).
     * @return bool
     */
    public static function saat_araliginda_mi( $simdi, $basla, $bitis ) {
        $basla = trim( (string) $basla );
        $bitis = trim( (string) $bitis );

        if ( '' === $basla || '' === $bitis ) {
            return true;
        }

        $s = self::saniye( $simdi );
        $b = self::saniye( $basla );
        $e = self::saniye( $bitis );

        if ( null === $s || null === $b || null === $e || $b === $e ) {
            return true;
        }

        return ( $b < $e ) ? ( $s >= $b && $s <= $e ) : ( $s >= $b || $s <= $e );
    }

    /**
     * "H:i" / "H:i:s" dizesini gün içi saniyeye çevirir.
     *
     * @param string $saat Saat dizesi.
     * @return int|null
     */
    private static function saniye( $saat ) {
        if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim( (string) $saat ), $m ) ) {
            return null;
        }

        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) ( $m[3] ?? 0 );
    }

    /**
     * MySQL datetime dizesini damgaya çevirir (yerel duvar saati kabulüyle).
     *
     * @param string $tarih 'Y-m-d H:i:s' ya da boş/NULL.
     * @return int|null Boş/geçersizse null.
     */
    private static function damga( $tarih ) {
        $tarih = trim( (string) $tarih );

        if ( '' === $tarih || '0000-00-00 00:00:00' === $tarih ) {
            return null;
        }

        $damga = strtotime( $tarih . ' UTC' );

        return false === $damga ? null : (int) $damga;
    }

    /**
     * Fiyatı menüdeki biçime çevirir: 52.5 → "52,50", 52.0 → "52".
     *
     * Kuruş kısmı sıfırsa tamamen atılır; değilse iki hane korunur (47,25 ₺
     * yerine 47,3 gibi bir çıktı fiyatı yanlış gösterirdi). WordPress'e
     * bağımlılığı yoktur — önizleme, ön yüz ve testler tek kaynaktan beslenir.
     *
     * @param float $deger Fiyat.
     * @return string
     */
    public static function bicimle( $deger ) {
        $metin = number_format( (float) $deger, 2, ',', '.' );

        return ( ',00' === substr( $metin, -3 ) ) ? substr( $metin, 0, -3 ) : $metin;
    }

    /**
     * Kuralın insan diline çevirisi — "%10 zam", "5 ₺ indirim".
     *
     * @param array|object $kampanya Kampanya kaydı.
     * @return string
     */
    public static function kural_metni( $kampanya ) {
        $k     = (array) $kampanya;
        $tutar = self::tutar_temizle( $k['amount'] ?? 0 );
        $yon   = ( 'decrease' === ( $k['direction'] ?? 'increase' ) ) ? 'indirim' : 'zam';

        if ( 'fixed' === ( $k['calc_type'] ?? 'percent' ) ) {
            return self::bicimle( $tutar ) . ' ₺ ' . $yon;
        }

        return '%' . self::bicimle( $tutar ) . ' ' . $yon;
    }

    /* =================================================================
       CRUD
    ================================================================= */

    /**
     * Tüm kampanyalar — yenisi üstte.
     *
     * @return array<int,object>
     */
    public static function hepsi() {
        global $wpdb;

        $tablo = self::tablo();

        return (array) $wpdb->get_results( "SELECT * FROM {$tablo} ORDER BY id DESC" );
    }

    /**
     * Tek kampanya.
     *
     * @param int $id Kampanya ID.
     * @return object|null
     */
    public static function getir( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return null;
        }

        $tablo = self::tablo();

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tablo} WHERE id = %d", $id ) );
    }

    /**
     * Durumu 'active' olan kampanya.
     *
     * ÇAKIŞMA KARARI: aynı anda yalnızca BİR kampanya aktif olabilir
     * (bkz. aktiflestir). Yine de LIMIT 1 ile okunur — elle veritabanı
     * müdahalesi bile olsa render tarafı ikircikli kalmasın.
     *
     * @return object|null
     */
    public static function aktif_kayit() {
        global $wpdb;

        // Bu sorgu ön yüzdeki HER menü render'ında çalışır (menü önbelleğinin
        // anahtarı aktif kampanyayı içerir, bkz. RMA_Kampanya::imza), yani her
        // ziyaretçi için bir bağlantı kullanımı demektir. Oysa kampanya
        // tablosu nadiren değişir: sonuç transient'te tutulur ve yalnızca
        // kaydet/aktifleştir/pasifleştir/sil yollarından geçersizlenir.
        $onbellek = get_transient( self::AKTIF_TRANSIENT );

        if ( is_array( $onbellek ) && array_key_exists( 'kayit', $onbellek ) ) {
            return $onbellek['kayit'];
        }

        $tablo = self::tablo();
        $kayit = $wpdb->get_row( "SELECT * FROM {$tablo} WHERE status = 'active' ORDER BY id DESC LIMIT 1" );

        // "Aktif kampanya yok" da geçerli bir cevaptır ve saklanır; aksi hâlde
        // kampanyasız sitelerde (çoğunluk) önbellek hiç isabet etmezdi. Sarmalayıcı
        // dizi bu yüzden var: null sonuç, get_transient'ın "kayıt yok" cevabı olan
        // false'tan böyle ayrılır.
        set_transient( self::AKTIF_TRANSIENT, array( 'kayit' => $kayit ), self::AKTIF_TTL );

        return $kayit;
    }

    /**
     * Aktif kampanya önbelleğini geçersizler.
     *
     * @return void
     */
    public static function onbellek_temizle() {
        delete_transient( self::AKTIF_TRANSIENT );
    }

    /**
     * Kampanya oluşturur ya da günceller.
     *
     * @param int   $id      0 ise yeni kayıt açılır.
     * @param array $ayarlar ayarlari_temizle() çıktısı.
     * @return int Kaydın ID'si; hata hâlinde 0.
     */
    public static function kaydet( $id, array $ayarlar ) {
        global $wpdb;

        $id    = (int) $id;
        $tablo = self::tablo();
        $simdi = current_time( 'mysql' );

        $veri = array(
            'title'          => $ayarlar['title'],
            'calc_type'      => $ayarlar['calc_type'],
            'direction'      => $ayarlar['direction'],
            'amount'         => $ayarlar['amount'],
            'rounding'       => $ayarlar['rounding'],
            'scope_type'     => $ayarlar['scope_type'],
            'scope_ids'      => $ayarlar['scope_ids'],
            'show_old_price' => $ayarlar['show_old_price'],
            'updated_at'     => $simdi,
        );

        $format = array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' );

        if ( $id > 0 && self::getir( $id ) ) {
            $wpdb->update( $tablo, $veri, array( 'id' => $id ), $format, array( '%d' ) );

            // Aktif kampanyanın kuralı değişmiş olabilir; önbellek satırın
            // kendisini sakladığı için burada da geçersizlenmeli.
            self::onbellek_temizle();

            return $id;
        }

        $veri['status']     = 'passive';
        $veri['created_at'] = $simdi;
        $format[]           = '%s';
        $format[]           = '%s';

        if ( false === $wpdb->insert( $tablo, $veri, $format ) ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Kampanyayı aktifleştirir — TEK KAMPANYA KURALI.
     *
     * Önce aktif olan her kayıt pasifleştirilir (ended_at damgalanır), sonra
     * hedef kampanya aktif olur. Böylece "hangi kural geçerli?" sorusunun
     * cevabı her zaman tektir ve yüzdeler asla üst üste binmez.
     *
     * @param int $id             Kampanya ID.
     * @param int $etkilenen      Aktivasyon anındaki etkilenen ürün sayısı.
     * @return bool
     */
    public static function aktiflestir( $id, $etkilenen = 0 ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 || ! self::getir( $id ) ) {
            return false;
        }

        $tablo = self::tablo();
        $simdi = current_time( 'mysql' );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tablo} SET status = 'passive', ended_at = %s WHERE status = 'active' AND id <> %d",
                $simdi,
                $id
            )
        );

        // ended_at NULL'a çekilmeli: $wpdb->update() null değeri boş dizeye
        // çevirdiği için (datetime sütununda geçersiz) burada elle sorgu
        // yazılıyor.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tablo}
                    SET status = 'active', applied_at = %s, ended_at = NULL, affected_count = %d, updated_at = %s
                  WHERE id = %d",
                $simdi,
                (int) $etkilenen,
                $simdi,
                $id
            )
        );

        self::onbellek_temizle();

        return true;
    }

    /**
     * Kampanyayı geri alır: durum pasif, bitiş damgası şimdi.
     *
     * Fiyat verisine dokunulmadığı için bu tek satırlık değişiklik ürünleri
     * anında orijinal fiyatlarına döndürür.
     *
     * @param int $id Kampanya ID.
     * @return bool
     */
    public static function pasiflestir( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return false;
        }

        $ok = (bool) $wpdb->update(
            self::tablo(),
            array(
                'status'     => 'passive',
                'ended_at'   => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        self::onbellek_temizle();

        return $ok;
    }

    /**
     * Kampanyayı ve fiyat fotoğrafını siler.
     *
     * @param int $id Kampanya ID.
     * @return bool
     */
    public static function sil( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return false;
        }

        $wpdb->delete( self::anlik_tablo(), array( 'campaign_id' => $id ), array( '%d' ) );

        $ok = (bool) $wpdb->delete( self::tablo(), array( 'id' => $id ), array( '%d' ) );

        self::onbellek_temizle();

        return $ok;
    }

    /**
     * Aktivasyon anındaki fiyat fotoğrafını yazar.
     *
     * @param int   $id      Kampanya ID.
     * @param array $satirlar product_id => original_price eşlemesi.
     * @return void
     */
    public static function anlik_yaz( $id, array $satirlar ) {
        global $wpdb;

        $id    = (int) $id;
        $tablo = self::anlik_tablo();

        $wpdb->delete( $tablo, array( 'campaign_id' => $id ), array( '%d' ) );

        if ( empty( $satirlar ) ) {
            return;
        }

        /*
         * TOPLU YAZIM — eskiden ürün başına bir $wpdb->insert() çalışıyordu.
         * Menünün tamamını kapsayan bir kampanyada bu, tek bir yönetim
         * işleminde yüzlerce ayrı sorgu (ve yüzlerce ağ gidiş-dönüşü) demekti;
         * aktivasyon uzun sürüyor, bu sırada veritabanı bağlantısı da meşgul
         * kalıyordu. Aynı satırlar tek INSERT'te yazılır.
         *
         * Parçalama şart: tek bir dev INSERT max_allowed_packet sınırına
         * takılabilir. 500 satırlık parçalar hem güvenli hem yeterince az sorgu.
         */
        foreach ( array_chunk( self::anlik_satirlari( $id, $satirlar ), 500 ) as $parca ) {
            $degerler = array();
            $params   = array();

            foreach ( $parca as $satir ) {
                $degerler[] = '(%d, %d, %f)';
                $params[]   = $satir['campaign_id'];
                $params[]   = $satir['product_id'];
                $params[]   = $satir['original_price'];
            }

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$tablo} (campaign_id, product_id, original_price)
                     VALUES " . implode( ', ', $degerler ),
                    $params
                )
            );
        }
    }

    /**
     * Fiyat fotoğrafı satırlarını normalize eder.
     *
     * Saf fonksiyon (WordPress'e bağımlılığı yok), bu yüzden doğrudan test
     * edilir: toplu yazımda tip dönüşümünün satır satır yazımdakiyle birebir
     * aynı kalması gerekir.
     *
     * @param int   $id       Kampanya ID.
     * @param array $satirlar product_id => original_price eşlemesi.
     * @return array<int,array{campaign_id:int,product_id:int,original_price:float}>
     */
    public static function anlik_satirlari( $id, array $satirlar ) {
        $out = array();

        foreach ( $satirlar as $urun_id => $fiyat ) {
            $out[] = array(
                'campaign_id'    => (int) $id,
                'product_id'     => (int) $urun_id,
                'original_price' => (float) $fiyat,
            );
        }

        return $out;
    }

    /**
     * Kampanyanın fiyat fotoğrafı — ürün ID => orijinal fiyat.
     *
     * @param int $id Kampanya ID.
     * @return array<int,float>
     */
    public static function anlik_getir( $id ) {
        global $wpdb;

        $id = (int) $id;

        if ( $id <= 0 ) {
            return array();
        }

        $tablo = self::anlik_tablo();

        $satirlar = (array) $wpdb->get_results(
            $wpdb->prepare( "SELECT product_id, original_price FROM {$tablo} WHERE campaign_id = %d", $id )
        );

        $harita = array();

        foreach ( $satirlar as $satir ) {
            $harita[ (int) $satir->product_id ] = (float) $satir->original_price;
        }

        return $harita;
    }
}

endif;
