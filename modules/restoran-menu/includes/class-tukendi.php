<?php
/**
 * Ürün "Tükendi" (stok) durumu.
 *
 * Göster/Gizle (`rma_active`) ürünü menüden TAMAMEN kaldırır. Tükendi öyle
 * değildir: ürün menüde görünmeye devam eder, yalnızca sipariş/tıklama
 * akışı kapanır ve görsel bir etiket basılır. Bu yüzden durum, fiyat
 * kampanyasındaki gibi ORİJİNAL kaydı ezmeden ayrı bir meta'da tutulur
 * (`_rma_tukendi`). Boş / '0' = stokta var; '1' = tükendi.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RMA_Tukendi' ) ) :

class RMA_Tukendi {

    /** Post meta anahtarı — özel alan listesinde görünmesin diye alt çizgili. */
    const META = '_rma_tukendi';

    /** Müşteriye ve chatbot'a dönen sabit mesaj (çeviri köprüsünden geçer). */
    const MESAJ = 'Bu ürün şu an tükendi';

    /** Rozet / etiket metni. */
    const ETIKET = 'Tükendi';

    /**
     * Meta değerinin "tükendi" sayılıp sayılmadığı.
     *
     * WordPress'e bağımlılığı yoktur — hem render hem test aynı kuralı kullanır.
     * Yalnızca açık '1' tükendi'dir; boş, '0' ve diğer her şey stokta var.
     *
     * @param mixed $deger get_post_meta sonucu.
     * @return bool
     */
    public static function meta_tukendi_mi( $deger ) {
        return '1' === (string) $deger;
    }

    /**
     * Ürün adı karşılaştırması için Unicode-güvenli küçük harf.
     *
     * Chatbot siparişi Türkçe ürün adıyla gelir; İ/i ayrımı strtolower ile
     * bozulur, bu yüzden mb_strtolower tercih edilir.
     *
     * @param mixed $ad
     * @return string
     */
    public static function ad_normalize( $ad ) {
        $ad = trim( (string) $ad );
        if ( '' === $ad ) {
            return '';
        }

        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( $ad, 'UTF-8' );
        }

        return strtolower( $ad );
    }

    /**
     * İki ürün adı aynı mı (boşluk ve büyük/küçük harf farkı yok sayılır).
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    public static function ad_eslesir( $a, $b ) {
        $na = self::ad_normalize( $a );
        $nb = self::ad_normalize( $b );

        return '' !== $na && $na === $nb;
    }

    /**
     * Bu ürün tükendi olarak işaretli mi?
     *
     * @param int $post_id Ürün ID.
     * @return bool
     */
    public static function urun_tukendi( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id < 1 ) {
            return false;
        }

        return self::meta_tukendi_mi( get_post_meta( $post_id, self::META, true ) );
    }

    /**
     * Durumu kaydeder. Yalnızca '1' / '0' yazılır.
     *
     * @param int  $post_id Ürün ID.
     * @param bool $tukendi Tükendi mi.
     * @return void
     */
    public static function kaydet( $post_id, $tukendi ) {
        $post_id = (int) $post_id;
        if ( $post_id < 1 ) {
            return;
        }

        update_post_meta( $post_id, self::META, $tukendi ? '1' : '0' );
    }

    /**
     * Çevirilmiş rozet metni.
     *
     * @return string
     */
    public static function etiket() {
        return self::cevir( self::ETIKET );
    }

    /**
     * Çevirilmiş müşteri mesajı.
     *
     * @return string
     */
    public static function mesaj() {
        return self::cevir( self::MESAJ );
    }

    /**
     * Görsel rozet HTML'i. Ürün tükendiyse dolu string, değilse boş.
     *
     * Konum CSS'te belirlenir (görselin alt şeridi); kart düzenini
     * bozmaması için çağıran taraf bunu görsel sargısının İÇİNE basar.
     *
     * @param int $post_id Ürün ID.
     * @return string
     */
    public static function rozet_html( $post_id ) {
        if ( ! self::urun_tukendi( $post_id ) ) {
            return '';
        }

        $etiket = self::etiket();

        return '<span class="rma-tukendi-rozet">' . esc_html( $etiket ) . '</span>';
    }

    /**
     * Chatbot / sipariş kalemlerinde tükendi ürün var mı?
     *
     * İlk tükendi kalemde durur; kısmi sipariş geçirilmez (mutfak yarım
     * liste almasın). Mesaj sabit tutulur — istemci bunu aynen basar.
     *
     * @param array<int,array<string,mixed>> $kalemler urunAdi içeren dizi.
     * @return string|null Engel mesajı veya null.
     */
    public static function siparis_engeli( array $kalemler ) {
        $detay = self::siparis_engeli_detay( $kalemler );

        return null !== $detay ? $detay['mesaj'] : null;
    }

    /**
     * Tükendi engelinin yapısal ayrıntısı — mesajdan ürün adı AYIKLANMAZ.
     *
     * @param array<int,array<string,mixed>> $kalemler urunAdi içeren dizi.
     * @return array{mesaj:string,item_id:int,item_name:string}|null
     */
    public static function siparis_engeli_detay( array $kalemler ) {
        foreach ( $kalemler as $kalem ) {
            if ( ! is_array( $kalem ) ) {
                continue;
            }

            $ad = isset( $kalem['urunAdi'] ) ? (string) $kalem['urunAdi'] : '';
            if ( '' === $ad ) {
                continue;
            }

            $urun = self::ad_tukendi_urun( $ad );
            if ( $urun ) {
                return array(
                    'mesaj'     => self::mesaj(),
                    'item_id'   => (int) $urun['id'],
                    'item_name' => (string) $urun['name'],
                );
            }
        }

        return null;
    }

    /**
     * Yayınlanmış menü ürünleri arasında bu ada sahip ve tükendi işaretli
     * bir kayıt var mı.
     *
     * Yalnızca `_rma_tukendi = 1` olanlar çekilir (tipik olarak az kayıt);
     * karşılaştırma PHP'de Unicode küçük harfle yapılır.
     *
     * @param string $urun_adi Chatbot'un gönderdiği Türkçe ad.
     * @return bool
     */
    public static function ad_tukendi( $urun_adi ) {
        return null !== self::ad_tukendi_urun( $urun_adi );
    }

    /**
     * Ada göre tükendi ürünü (id + görünen ad) veya null.
     *
     * @param string $urun_adi Chatbot'un gönderdiği Türkçe ad.
     * @return array{id:int,name:string,ad:string}|null
     */
    public static function ad_tukendi_urun( $urun_adi ) {
        $hedef = self::ad_normalize( $urun_adi );
        if ( '' === $hedef ) {
            return null;
        }

        foreach ( self::tukendi_urunleri() as $urun ) {
            if ( $urun['ad'] === $hedef ) {
                return $urun;
            }
        }

        return null;
    }

    /**
     * Tükendi işaretli yayın ürünleri (istek içi önbellek).
     *
     * @return array<int,array{id:int,name:string,ad:string}>
     */
    private static function tukendi_urunleri() {
        static $urunler = null;

        if ( is_array( $urunler ) ) {
            return $urunler;
        }

        $urunler = array();

        $posts = get_posts(
            array(
                'post_type'              => 'rma_menu_item',
                'post_status'            => 'publish',
                'posts_per_page'         => 200,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'meta_key'               => self::META,
                'meta_value'             => '1',
            )
        );

        foreach ( $posts as $post ) {
            $ad = self::ad_normalize( $post->post_title );
            if ( '' !== $ad ) {
                $urunler[] = array(
                    'id'   => (int) $post->ID,
                    'name' => (string) $post->post_title,
                    'ad'   => $ad,
                );
            }
        }

        return $urunler;
    }

    /**
     * Tükendi işaretli yayın ürün adları (normalize, istek içi önbellek).
     *
     * @return string[]
     */
    private static function tukendi_adlari() {
        $adlar = array();

        foreach ( self::tukendi_urunleri() as $urun ) {
            $adlar[] = $urun['ad'];
        }

        return $adlar;
    }

    /**
     * Chatbot sipariş filtresi — qmo_siparis_isle içinden çağrılır.
     *
     * String dönüşü (önceki bir filtrenin mesajı) olduğu gibi geçer. Bu
     * filtrenin kendi engeli yapısal dizi döner: rest-order ürünü mesajdan
     * ayıklamak zorunda kalmasın. Dizi tanımayan eski tüketiciler
     * `is_string` ile eskisi gibi çalışır.
     *
     * @param string|array|null $engel  Önceki filtrenin mesajı veya ayrıntısı.
     * @param array             $kalemler Temizlenmiş sipariş kalemleri.
     * @return string|array|null
     */
    public static function siparis_filtresi( $engel, $kalemler ) {
        if ( is_array( $engel ) && ! empty( $engel['mesaj'] ) ) {
            return $engel;
        }

        if ( is_string( $engel ) && '' !== $engel ) {
            return $engel;
        }

        if ( ! is_array( $kalemler ) ) {
            return $engel;
        }

        $detay = self::siparis_engeli_detay( $kalemler );

        return null !== $detay ? $detay : $engel;
    }

    /**
     * Sabit arayüz metnini çeviri köprüsünden geçirir.
     *
     * @param string $metin
     * @return string
     */
    private static function cevir( $metin ) {
        return function_exists( 'rma_ceviri_ui' ) ? rma_ceviri_ui( $metin ) : $metin;
    }
}

endif;

/**
 * Ürün tükendi mi — modül dışından okunabilsin diye global köprü.
 *
 * Deseni `rma_get_effective_price()` ile aynı: chatbot menü JSON'u ve
 * sipariş ucu restoran-menu sınıflarına doğrudan bağlanmadan sorabilsin.
 *
 * @param int $post_id Ürün ID.
 * @return bool
 */
function rma_urun_tukendi( $post_id ) {
    return class_exists( 'RMA_Tukendi' ) ? RMA_Tukendi::urun_tukendi( $post_id ) : false;
}
