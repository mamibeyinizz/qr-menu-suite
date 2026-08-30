<?php
/**
 * Kampanya Banner — SUNUCU TARAFI görsel kırpma.
 *
 * NEDEN VAR: banner görselleri uzun süre yalnızca CSS `object-fit: cover`
 * ile "kırpılıyordu". CSS hiçbir dosyayı değiştirmez; sadece tarayıcıda
 * taşan kısmı gizler. Kullanıcı biri kare, biri dikey, biri zaten 16:9 üç
 * görsel yüklediğinde her slaytta FARKLI bir bölge kayboluyor, üstelik
 * yönetim önizlemesi ile ön yüz farklı genişlikte render edildiği için
 * ikisi birbirini tutmuyordu. Kalıcı çözüm dosyanın kendisini hedef orana
 * getirmektir; bu sınıf onu yapar.
 *
 * YÖNTEM — wp_get_image_editor():
 *   - WordPress'in kendi soyutlaması; Imagick varsa onu, yoksa GD'yi seçer.
 *     Doğrudan GD/Imagick çağırmak paylaşımlı hostinglerde "hangi eklenti
 *     kurulu" kontrolünü ve MIME/kalite/EXIF döndürme işini bize yıkardı.
 *   - Hata durumunda fatal değil WP_Error döner (çağıran sessizce eski
 *     davranışa düşebilir).
 *   - Eklentide zaten kullanılıyor (qr-galeri'deki webp dönüştürmesi), yani
 *     yeni bir bağımlılık değil.
 *
 * NEREYE YAZILIR — orijinalin YANINA, ek boyut olarak:
 *   Kırpılmış dosya `_wp_attachment_metadata['sizes']` içine
 *   `qmo-banner-16x9` gibi kendi adıyla eklenir. Bu, ayrı bir medya kaydı
 *   (attachment) üretmeye göre şu nedenlerle tercih edildi:
 *     1. Medya kütüphanesi kirlenmez — kullanıcı her banner için iki kayıt
 *        görmez, arama/ızgara aynı kalır.
 *     2. Orijinal dosyaya DOKUNULMAZ; oran sonradan değişirse yeniden
 *        kırpma hep tam çözünürlüklü kaynaktan yapılır (kırpılmışın
 *        kırpılması kalite kaybıdır).
 *     3. Ek silinince WordPress `sizes` altındaki dosyaları da siler; yetim
 *        dosya kalmaz, ayrı bir temizlik kancası gerekmez.
 *     4. `wp_get_attachment_image_src( $id, 'qmo-banner-16x9' )` bu kaydı
 *        doğrudan çözer (image_get_intermediate_size metadata'ya bakar),
 *        yani okuma tarafı WordPress'in normal yolundan geçer.
 *   Boyut adı ORAN BAŞINA ayrıdır: 16:9 kırpması dururken 3:1'e geçilirse
 *   eski kırpma silinmez, sadece "bu oran için kırpma yok" durumuna düşülür
 *   ve yönetimde uyarı çıkar. Orana geri dönülürse dosya hazırdır.
 *
 * BİLİNEN SINIRLAR (bilinçli tercihler):
 *   - Kırpma ekin KENDİSİNE bağlıdır. Aynı görseli iki banner farklı
 *     odakla kullanırsa son kaydeden kazanır; diğerinin satırında "yeniden
 *     kırp" uyarısı çıkar ve tek tıkla kendi odağına döner. Odağı da
 *     boyut adına katmak her odak için ayrı dosya demek olurdu.
 *   - Hareketli GIF kırpılırsa tek kareye iner. Önerilen biçimler zaten
 *     JPG/WEBP; orijinal dosya her hâlükârda korunduğu için geri dönüş
 *     mümkündür.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'QMO_Banner_Kirpma' ) ) :

class QMO_Banner_Kirpma {

    /** Banner kaydındaki kırpma odağı meta anahtarı. */
    const META_ODAK = '_qmo_banner_odak';

    /**
     * Oran sapma toleransı (bağıl).
     *
     * Kaynak görselin oranı hedeften bu kadar az sapıyorsa kırpma DOSYASI
     * üretilmez: 1600x901 bir görseli 16:9 için yeniden yazmak boşuna
     * disk ve kalite kaybıdır, kalan ~1px'i CSS `object-fit` zaten yutar.
     * CSS'in rolü budur — asıl kırpmayı yapan değil, son 1 pikseli
     * kapatan güvenlik ağı.
     */
    const TOLERANS = 0.01;

    /**
     * Kırpma odağı seçenekleri.
     *
     * Merkezî kırpma her zaman doğru sonucu vermez (kenarda kalan bir yüz
     * kesilebilir); bu liste kullanıcıya sürükleme arayüzü olmadan da bir
     * çıkış yolu bırakır. TEK KAYNAK: meta kutusundaki açılır liste,
     * temizleme beyaz listesi ve object-position karşılıkları buradan.
     *
     * @return array<string,array{etiket:string,css:string}>
     */
    public static function odaklar() {
        return array(
            'merkez' => array( 'etiket' => 'Merkez (varsayılan)', 'css' => 'center center' ),
            'ust'    => array( 'etiket' => 'Üst kenar',            'css' => 'center top' ),
            'alt'    => array( 'etiket' => 'Alt kenar',            'css' => 'center bottom' ),
            'sol'    => array( 'etiket' => 'Sol kenar',            'css' => 'left center' ),
            'sag'    => array( 'etiket' => 'Sağ kenar',            'css' => 'right center' ),
        );
    }

    /**
     * Odak anahtarını beyaz listeye göre doğrular.
     *
     * @param mixed $deger Ham değer.
     * @return string
     */
    public static function odak( $deger ) {
        $deger = strtolower( trim( (string) $deger ) );

        return array_key_exists( $deger, self::odaklar() ) ? $deger : 'merkez';
    }

    /**
     * Odağın CSS object-position karşılığı.
     *
     * Yönetim önizlemesi ve henüz kırpılmamış eski görsellerin ön yüzü
     * bunu kullanır: önizleme sunucunun üreteceği kadrajın aynısını
     * gösterir, eski görsel de en azından doğru bölgeden kesilir.
     *
     * @param mixed $deger Ham odak değeri.
     * @return string
     */
    public static function odak_css( $deger ) {
        $odaklar = self::odaklar();

        return $odaklar[ self::odak( $deger ) ]['css'];
    }

    /**
     * Bir banner kaydının kırpma odağı.
     *
     * @param int $banner_id Banner (CPT) kayıt ID'si.
     * @return string
     */
    public static function banner_odagi( $banner_id ) {
        return self::odak( get_post_meta( (int) $banner_id, self::META_ODAK, true ) );
    }

    /**
     * Oran anahtarının ek boyut adı ("16:9" -> "qmo-banner-16x9").
     *
     * @param string $oran oranlar() anahtarı.
     * @return string
     */
    public static function boyut_adi( $oran ) {
        return 'qmo-banner-' . str_replace( ':', 'x', self::gecerli_oran( $oran ) );
    }

    /**
     * Oran anahtarının sayısal karşılığı ("16:9" -> 1.777…).
     *
     * @param string $oran oranlar() anahtarı.
     * @return float
     */
    public static function oran_orani( $oran ) {
        $parca = explode( ':', self::gecerli_oran( $oran ) );
        $en    = isset( $parca[0] ) ? (float) $parca[0] : 16.0;
        $boy   = isset( $parca[1] ) ? (float) $parca[1] : 9.0;

        if ( $en <= 0 || $boy <= 0 ) {
            return 16 / 9;
        }

        return $en / $boy;
    }

    /**
     * Merkez/odak tabanlı kırpma kutusu ve çıktı boyutu — SAF hesap.
     *
     * WordPress'e hiç dokunmaz, doğrudan test edilir. Kaynak hedeften
     * genişse yatayda, darsa dikeyde kesilir; kesilen kenar odağa göre
     * kaydırılır. Çıktı hiçbir zaman kaynaktan büyük olmaz (upscale
     * bulanıklık üretir), en fazla $azami_en genişliğine iner.
     *
     * @param int    $kaynak_en  Kaynak genişlik (px).
     * @param int    $kaynak_boy Kaynak yükseklik (px).
     * @param float  $hedef_oran Hedef en/boy (ör. 1.7777).
     * @param string $odak       odaklar() anahtarı.
     * @param int    $azami_en   Çıktının azami genişliği (px).
     * @return array{x:int,y:int,en:int,boy:int,cikti_en:int,cikti_boy:int}
     */
    public static function kirpma_kutusu( $kaynak_en, $kaynak_boy, $hedef_oran, $odak = 'merkez', $azami_en = 1600 ) {
        $kaynak_en  = max( 1, (int) $kaynak_en );
        $kaynak_boy = max( 1, (int) $kaynak_boy );
        $hedef_oran = (float) $hedef_oran > 0 ? (float) $hedef_oran : 16 / 9;
        $odak       = self::odak( $odak );

        if ( ( $kaynak_en / $kaynak_boy ) > $hedef_oran ) {
            // Kaynak fazla geniş: yatayda kes, tam yüksekliği koru.
            $en  = min( $kaynak_en, max( 1, (int) round( $kaynak_boy * $hedef_oran ) ) );
            $boy = $kaynak_boy;
            $pay = $kaynak_en - $en;

            if ( 'sol' === $odak ) {
                $x = 0;
            } elseif ( 'sag' === $odak ) {
                $x = $pay;
            } else {
                $x = (int) round( $pay / 2 );
            }

            $y = 0;
        } else {
            // Kaynak fazla dik (ya da tam oranında): dikeyde kes.
            $en  = $kaynak_en;
            $boy = min( $kaynak_boy, max( 1, (int) round( $kaynak_en / $hedef_oran ) ) );
            $pay = $kaynak_boy - $boy;

            if ( 'ust' === $odak ) {
                $y = 0;
            } elseif ( 'alt' === $odak ) {
                $y = $pay;
            } else {
                $y = (int) round( $pay / 2 );
            }

            $x = 0;
        }

        $cikti_en  = min( max( 1, (int) $azami_en ), $en );
        $cikti_boy = max( 1, (int) round( $cikti_en / $hedef_oran ) );

        return array(
            'x'         => (int) $x,
            'y'         => (int) $y,
            'en'        => (int) $en,
            'boy'       => (int) $boy,
            'cikti_en'  => (int) $cikti_en,
            'cikti_boy' => (int) $cikti_boy,
        );
    }

    /**
     * Kaynak oran hedefe zaten uyuyor mu (tolerans içinde)?
     *
     * @param int   $kaynak_en  Kaynak genişlik.
     * @param int   $kaynak_boy Kaynak yükseklik.
     * @param float $hedef_oran Hedef en/boy.
     * @return bool
     */
    public static function oran_uyuyor( $kaynak_en, $kaynak_boy, $hedef_oran ) {
        $kaynak_en  = (int) $kaynak_en;
        $kaynak_boy = (int) $kaynak_boy;
        $hedef_oran = (float) $hedef_oran;

        if ( $kaynak_en < 1 || $kaynak_boy < 1 || $hedef_oran <= 0 ) {
            return false;
        }

        return abs( ( $kaynak_en / $kaynak_boy ) / $hedef_oran - 1 ) <= self::TOLERANS;
    }

    /**
     * Ekin (attachment) piksel boyutu.
     *
     * Önce metadata okunur (disk erişimi yok); metadata eksikse dosyadan
     * ölçülür.
     *
     * @param int $ek_id Attachment ID.
     * @return array{0:int,1:int}|null
     */
    public static function kaynak_boyutu( $ek_id ) {
        $meta = wp_get_attachment_metadata( (int) $ek_id );

        if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
            return array( (int) $meta['width'], (int) $meta['height'] );
        }

        $dosya = get_attached_file( (int) $ek_id );

        if ( ! $dosya || ! file_exists( $dosya ) ) {
            return null;
        }

        $olcu = @getimagesize( $dosya ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if ( empty( $olcu[0] ) || empty( $olcu[1] ) ) {
            return null;
        }

        return array( (int) $olcu[0], (int) $olcu[1] );
    }

    /**
     * Bir ekin verili oran/odak için kırpma durumu.
     *
     * 'gorsel-yok' — ek yok ya da dosyası okunamıyor.
     * 'uygun'      — kaynak zaten hedef oranda; kırpma dosyası gerekmiyor.
     * 'hazir'      — bu oran ve bu odak için kırpılmış dosya duruyor.
     * 'bekliyor'   — kırpma gerekiyor ama yok (ya da odak değişmiş).
     *
     * @param int         $ek_id Attachment ID.
     * @param string|null $oran  Oran anahtarı; null ise kayıtlı ayar.
     * @param string      $odak  Odak anahtarı.
     * @return string
     */
    public static function durum( $ek_id, $oran = null, $odak = 'merkez' ) {
        $ek_id = (int) $ek_id;

        if ( $ek_id < 1 || 'attachment' !== get_post_type( $ek_id ) ) {
            return 'gorsel-yok';
        }

        $boyut = self::kaynak_boyutu( $ek_id );

        if ( null === $boyut ) {
            return 'gorsel-yok';
        }

        $oran  = self::gecerli_oran( $oran );
        $hedef = self::oran_orani( $oran );

        if ( self::oran_uyuyor( $boyut[0], $boyut[1], $hedef ) ) {
            return 'uygun';
        }

        return self::kayit( $ek_id, $oran, $odak ) ? 'hazir' : 'bekliyor';
    }

    /**
     * Kırpılmış sürümün metadata kaydı (yoksa null).
     *
     * Odak verilirse yalnızca AYNI odakla üretilmiş kayıt kabul edilir;
     * odak değiştiğinde kırpma bayatlar ve yeniden üretilmesi gerekir.
     *
     * @param int         $ek_id Attachment ID.
     * @param string|null $oran  Oran anahtarı.
     * @param string|null $odak  Odak anahtarı; null ise odak kontrol edilmez.
     * @return array|null
     */
    public static function kayit( $ek_id, $oran = null, $odak = null ) {
        $meta = wp_get_attachment_metadata( (int) $ek_id );
        $ad   = self::boyut_adi( $oran );

        if ( ! is_array( $meta ) || empty( $meta['sizes'][ $ad ] ) || ! is_array( $meta['sizes'][ $ad ] ) ) {
            return null;
        }

        $kayit = $meta['sizes'][ $ad ];

        if ( empty( $kayit['file'] ) ) {
            return null;
        }

        if ( null !== $odak && self::odak( $kayit['qmo_odak'] ?? 'merkez' ) !== self::odak( $odak ) ) {
            return null;
        }

        return $kayit;
    }

    /**
     * Ön yüz/yönetim için gösterilecek görsel.
     *
     * Kırpılmış sürüm varsa o, yoksa orijinal döner. 'kirpildi' bayrağı
     * çağırana srcset basıp basmayacağını söyler: kırpılmış sürüm TEK
     * dosyadır, orijinalin srcset'i ise farklı oranda adaylar içerdiği için
     * onunla karıştırılamaz.
     *
     * @param int         $ek_id Attachment ID.
     * @param string|null $oran  Oran anahtarı; null ise kayıtlı ayar.
     * @param string      $odak  Odak anahtarı.
     * @return array{url:string,en:int,boy:int,kirpildi:bool}|null
     */
    public static function gorsel( $ek_id, $oran = null, $odak = 'merkez' ) {
        $ek_id = (int) $ek_id;

        if ( $ek_id < 1 ) {
            return null;
        }

        $oran = self::gecerli_oran( $oran );

        $kayit = self::kayit( $ek_id, $oran, $odak );

        if ( $kayit ) {
            $src = wp_get_attachment_image_src( $ek_id, self::boyut_adi( $oran ) );

            // Genişlik kayıtla birebir tutmuyorsa ek boyut çözülmemiş, tam
            // boyuta düşülmüş demektir (bir filtre araya girmiş olabilir):
            // o zaman "kırpıldı" deyip srcset'i düşürmek yanlış olur.
            if ( is_array( $src ) && ! empty( $src[0] ) && (int) $src[1] === (int) $kayit['width'] ) {
                return array(
                    'url'      => (string) $src[0],
                    'en'       => (int) $src[1],
                    'boy'      => (int) $src[2],
                    'kirpildi' => true,
                );
            }
        }

        $url = wp_get_attachment_image_url( $ek_id, 'full' );

        if ( ! $url ) {
            return null;
        }

        $boyut = self::kaynak_boyutu( $ek_id );

        return array(
            'url'      => (string) $url,
            'en'       => $boyut ? (int) $boyut[0] : 0,
            'boy'      => $boyut ? (int) $boyut[1] : 0,
            'kirpildi' => false,
        );
    }

    /**
     * Eki hedef orana SUNUCU TARAFINDA kırpar ve ek boyut olarak kaydeder.
     *
     * Kaynak zaten hedef orandaysa dosya üretilmez (bkz. TOLERANS).
     * Aynı oran için üretilen dosya adı sabittir; yeniden kırpma eskisinin
     * üzerine yazar, yetim dosya birikmez.
     *
     * @param int         $ek_id Attachment ID.
     * @param string|null $oran  Oran anahtarı; null ise kayıtlı ayar.
     * @param string      $odak  Odak anahtarı.
     * @return true|WP_Error true = iş bitti (kırpıldı ya da gerekmedi).
     */
    public static function kirp( $ek_id, $oran = null, $odak = 'merkez' ) {
        $ek_id = (int) $ek_id;
        $odak  = self::odak( $odak );

        if ( $ek_id < 1 || 'attachment' !== get_post_type( $ek_id ) ) {
            return new WP_Error( 'qmo_banner_kirpma_ek', 'Kırpılacak görsel bulunamadı.' );
        }

        $dosya = get_attached_file( $ek_id );

        if ( ! $dosya || ! file_exists( $dosya ) ) {
            return new WP_Error( 'qmo_banner_kirpma_dosya', 'Görsel dosyası diskte bulunamadı.' );
        }

        $boyut = self::kaynak_boyutu( $ek_id );

        if ( null === $boyut ) {
            return new WP_Error( 'qmo_banner_kirpma_olcu', 'Görselin boyutu okunamadı.' );
        }

        $oran  = self::gecerli_oran( $oran );
        $hedef = self::oran_orani( $oran );

        // Zaten hedef orandaysa dokunma: kırpma dosyası üretmek kalite ve
        // disk kaybı olurdu, kalan sapmayı CSS object-fit yutar.
        if ( self::oran_uyuyor( $boyut[0], $boyut[1], $hedef ) ) {
            return true;
        }

        $azami = 1600;

        if ( class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            $onerilen = QMO_Banner_Slider_Settings::onerilen_px( $oran );
            $azami    = (int) $onerilen[0];
        }

        $kutu   = self::kirpma_kutusu( $boyut[0], $boyut[1], $hedef, $odak, $azami );
        $editor = wp_get_image_editor( $dosya );

        if ( is_wp_error( $editor ) ) {
            return $editor;
        }

        $kirpildi = $editor->crop(
            $kutu['x'],
            $kutu['y'],
            $kutu['en'],
            $kutu['boy'],
            $kutu['cikti_en'],
            $kutu['cikti_boy']
        );

        if ( is_wp_error( $kirpildi ) ) {
            return $kirpildi;
        }

        $hedef_dosya = $editor->generate_filename( self::boyut_adi( $oran ) );
        $sonuc       = $editor->save( $hedef_dosya );

        if ( is_wp_error( $sonuc ) ) {
            return $sonuc;
        }

        $ad   = self::boyut_adi( $oran );
        $meta = wp_get_attachment_metadata( $ek_id );

        if ( ! is_array( $meta ) ) {
            $meta = array();
        }
        if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
            $meta['sizes'] = array();
        }

        // Ad değiştiyse (uzantı/dizin farkı) eski dosyayı bırakma.
        if ( ! empty( $meta['sizes'][ $ad ]['file'] ) && $meta['sizes'][ $ad ]['file'] !== $sonuc['file'] ) {
            $eski = dirname( $dosya ) . '/' . basename( (string) $meta['sizes'][ $ad ]['file'] );

            if ( file_exists( $eski ) ) {
                wp_delete_file( $eski );
            }
        }

        $meta['sizes'][ $ad ] = array(
            'file'      => $sonuc['file'],
            'width'     => (int) $sonuc['width'],
            'height'    => (int) $sonuc['height'],
            'mime-type' => $sonuc['mime-type'],
            // WordPress bu diziyi olduğu gibi saklar; odak kaydı sayesinde
            // odak değiştiğinde kırpmanın bayatladığı anlaşılır.
            'qmo_odak'  => $odak,
        );

        wp_update_attachment_metadata( $ek_id, $meta );

        return true;
    }

    /**
     * Bir banner kaydının görselini güncel orana göre kırpar.
     *
     * @param int         $banner_id Banner (CPT) kayıt ID'si.
     * @param string|null $oran      Oran anahtarı; null ise kayıtlı ayar.
     * @return true|WP_Error|null null = kayıtta görsel yok.
     */
    public static function banner_kirp( $banner_id, $oran = null ) {
        $ek_id = (int) get_post_meta( (int) $banner_id, QMO_Banner_CPT::META_IMAGE, true );

        if ( $ek_id < 1 ) {
            return null;
        }

        return self::kirp( $ek_id, $oran, self::banner_odagi( $banner_id ) );
    }

    /**
     * Tüm banner kayıtlarını güncel orana göre yeniden kırpar.
     *
     * Yönetimdeki "Tüm görselleri yeniden kırp" düğmesi ve oran
     * değiştikten sonraki toplu işlem bunu kullanır. Sayfalama yoktur:
     * banner sayısı doğası gereği azdır (tipik kurulumda 3–10 kayıt) ve
     * kırpma yalnızca bayat olanlar için çalışır.
     *
     * @param string|null $oran Oran anahtarı; null ise kayıtlı ayar.
     * @return array{ok:int,atlandi:int,hata:int,mesaj:string}
     */
    public static function toplu_kirp( $oran = null ) {
        $oran   = self::gecerli_oran( $oran );
        $sonuc  = array( 'ok' => 0, 'atlandi' => 0, 'hata' => 0, 'mesaj' => '' );
        $hatali = array();

        foreach ( QMO_Banner_CPT::get_admin_banners() as $banner ) {
            $ek_id = (int) get_post_meta( $banner->ID, QMO_Banner_CPT::META_IMAGE, true );

            if ( $ek_id < 1 ) {
                $sonuc['atlandi']++;
                continue;
            }

            $odak = self::banner_odagi( $banner->ID );

            if ( 'bekliyor' !== self::durum( $ek_id, $oran, $odak ) ) {
                $sonuc['atlandi']++;
                continue;
            }

            $islem = self::kirp( $ek_id, $oran, $odak );

            if ( is_wp_error( $islem ) ) {
                $sonuc['hata']++;
                $hatali[] = $islem->get_error_message();
                continue;
            }

            $sonuc['ok']++;
        }

        $sonuc['mesaj'] = $hatali ? (string) $hatali[0] : '';

        return $sonuc;
    }

    /**
     * Güncel orana göre kırpılmayı bekleyen banner sayısı.
     *
     * @param string|null   $oran    Oran anahtarı; null ise kayıtlı ayar.
     * @param WP_Post[]|null $banners Zaten çekilmiş liste; null ise sorgulanır.
     * @return int
     */
    public static function bekleyen_sayisi( $oran = null, $banners = null ) {
        $oran = self::gecerli_oran( $oran );
        $sayi = 0;

        if ( ! is_array( $banners ) ) {
            $banners = QMO_Banner_CPT::get_admin_banners();
        }

        foreach ( $banners as $banner ) {
            $ek_id = (int) get_post_meta( $banner->ID, QMO_Banner_CPT::META_IMAGE, true );

            if ( $ek_id < 1 ) {
                continue;
            }

            if ( 'bekliyor' === self::durum( $ek_id, $oran, self::banner_odagi( $banner->ID ) ) ) {
                $sayi++;
            }
        }

        return $sayi;
    }

    /**
     * Oran anahtarını doğrular; null gelirse kayıtlı ayarı okur.
     *
     * @param string|null $oran Ham oran.
     * @return string
     */
    private static function gecerli_oran( $oran = null ) {
        if ( ! class_exists( 'QMO_Banner_Slider_Settings' ) ) {
            return null === $oran ? '16:9' : (string) $oran;
        }

        if ( null === $oran ) {
            $oran = QMO_Banner_Slider_Settings::get()['oran'];
        }

        return QMO_Banner_Slider_Settings::oran( $oran );
    }
}

endif;
