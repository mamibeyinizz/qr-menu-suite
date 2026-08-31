<?php
/**
 * Ortak yardımcılar — oturum zorlama, nonce doğrulama, hız sınırlama.
 *
 * Tüm public AJAX/REST uçları buradaki guard'lardan geçer. Guard'sız uç
 * bırakmayın: admin-ajax.php herkese açıktır.
 *
 * @package QR_Menu_Official
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** AJAX nonce eylem adı. */
if ( ! defined( 'QMO_NONCE_ACTION' ) ) {
	define( 'QMO_NONCE_ACTION', 'qmo_ajax' );
}

/**
 * Hata günlüğü — yalnızca WP_DEBUG açıkken yazar.
 * Müşteriye gösterilmeyen ayrıntılar (API hataları vb.) buraya düşer.
 *
 * @param string $mesaj Mesaj.
 */
if ( ! function_exists( 'qmo_log' ) ) {
	function qmo_log( $mesaj ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[QR Menu Official] ' . $mesaj );
		}
	}
}

/**
 * Geçerli masa oturumunu döndürür.
 *
 * @return array{masa:string,issued:int,last:int,epoch:int}|false
 */
if ( ! function_exists( 'qmo_oturum' ) ) {
	function qmo_oturum() {
		$token = isset( $_COOKIE[ QMO_Oturum::COOKIE ] ) ? wp_unslash( $_COOKIE[ QMO_Oturum::COOKIE ] ) : '';
		return QMO_Oturum::dogrula( $token );
	}
}

/**
 * AJAX nonce'unu doğrula. Geçersizse isteği sonlandırır.
 *
 * Nonce, wp_localize_script ile ön yüze iletilir (qmoData.nonce).
 */
if ( ! function_exists( 'qmo_nonce_dogrula' ) ) {
	function qmo_nonce_dogrula() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, QMO_NONCE_ACTION ) ) {
			wp_send_json_error(
				array(
					'kod'   => 'nonce',
					'mesaj' => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.',
				),
				403
			);
		}
	}
}

/**
 * AJAX ucu için oturum zorla: nonce + geçerli masa oturumu.
 * Başarılıysa oturumu tazeler ve oturum verisini döndürür.
 *
 * @return array{masa:string,issued:int,last:int,epoch:int}
 */
if ( ! function_exists( 'qmo_oturum_zorla' ) ) {
	function qmo_oturum_zorla() {
		qmo_nonce_dogrula();

		$data = qmo_oturum();
		if ( ! $data ) {
			wp_send_json_error(
				array(
					'kod'   => 'oturum_bitti',
					'mesaj' => 'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.',
				),
				403
			);
		}

		// İşlem yapıldı — idle sayacını sıfırla.
		qmo_cookie_yaz( QMO_Oturum::token_uret( $data['masa'], $data['issued'] ) );
		return $data;
	}
}

/**
 * Chatbot ucu için oturum zorla + oturum başına mesaj limiti.
 * Gemini faturasını korur: limitsiz bir uç, döngüyle POST atan biri
 * tarafından sömürülebilir.
 *
 * @return array{masa:string,issued:int,last:int,epoch:int}
 */
if ( ! function_exists( 'qmo_chat_zorla' ) ) {
	function qmo_chat_zorla() {
		$sess = qmo_oturum_zorla();

		$k     = 'qr_chat_' . md5( $sess['masa'] . '_' . $sess['issued'] );
		$sayac = (int) get_transient( $k );
		if ( $sayac >= QMO_Oturum::chat_limit() ) {
			wp_send_json_error(
				array(
					'kod'   => 'limit',
					'mesaj' => 'Bu oturum için mesaj limitine ulaştınız.',
				),
				429
			);
		}
		set_transient( $k, $sayac + 1, QMO_Oturum::hard_cap() );

		return $sess;
	}
}

/**
 * ?masa=X değeri qrm_tables tablosunda kayıtlı bir slug mu?
 *
 * @param string $slug Masa slug'ı (string; asla absint edilmez).
 * @return bool
 */
if ( ! function_exists( 'qmo_masa_gecerli_mi' ) ) {
	function qmo_masa_gecerli_mi( $slug ) {
		global $wpdb;

		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return false;
		}

		$cache_key = 'qmo_masa_' . md5( $slug );

		// Önce obje önbelleği: kalıcı bir obje önbelleği (Redis/Memcached)
		// kurulu olan sitelerde en ucuz yol budur.
		$cached = wp_cache_get( $cache_key, 'qmo' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		// Kalıcı obje önbelleği YOKSA — paylaşımlı hosting'de tipik durum —
		// wp_cache_* yalnızca istek içinde yaşar, yani masa doğrulaması her
		// ziyaretçi isteğinde yeniden sorgu açardı. Masa listesi ise nadiren
		// değişir; transient ikinci katman olarak o boşluğu kapatır.
		$saklanan = get_transient( $cache_key );
		if ( false !== $saklanan ) {
			wp_cache_set( $cache_key, (int) $saklanan, 'qmo', 300 );
			return ( (int) $saklanan ) > 0;
		}

		$tablo = $wpdb->prefix . 'qrm_tables';
		$var   = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tablo} WHERE table_slug = %s", $slug )
		);
		$ok = ( (int) $var ) > 0;

		wp_cache_set( $cache_key, $ok ? 1 : 0, 'qmo', 300 );

		// Olumsuz sonuç da saklanır ama çok kısa süreyle: sahte bir QR'ın
		// sorguyu her istekte tekrarlaması engellenir, buna karşılık yeni
		// eklenen bir masa en fazla bir dakika "yok" görünür (masa
		// eklendiğinde qmo_masa_cache_temizle zaten çağrılır).
		set_transient( $cache_key, $ok ? 1 : 0, $ok ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS );

		return $ok;
	}
}

/**
 * Masa önbelleğini temizle (masa eklendiğinde/silindiğinde çağrılır).
 *
 * @param string $slug Masa slug'ı.
 */
if ( ! function_exists( 'qmo_masa_cache_temizle' ) ) {
	function qmo_masa_cache_temizle( $slug ) {
		$cache_key = 'qmo_masa_' . md5( sanitize_title( $slug ) );

		wp_cache_delete( $cache_key, 'qmo' );
		delete_transient( $cache_key );
	}
}

/**
 * Tüm önbellekleri temizler — nesne önbelleği + kurulu önbellek eklentileri.
 *
 * Bir ayar kaydedildiğinde ön yüz çıktısı değişir, ama sayfa çoğu kurulumda
 * bir önbellek katmanının arkasındadır: kullanıcı "Kaydet" deyip sayfayı
 * yenilese bile eski HTML'i görür ve değişikliğin kaydedilmediğini sanır.
 * Bu yüzden kayıt akışları (HFB, vitrin, banner…) kayıttan HEMEN SONRA
 * burayı çağırır.
 *
 * Eklenti temizlikleri koşulludur: kurulu olmayan eklenti sessizce atlanır,
 * hiçbir zaman ölümcül hata üretilmez. qmo_masa_cache_temizle() tek bir masa
 * anahtarını hedefler ve bu fonksiyondan bağımsızdır; ikisi birbirinin yerine
 * geçmez.
 *
 * @param string $cache_group Yalnızca bu nesne önbelleği grubu temizlensin
 *                            (ör. 'qmo'). Boşsa ya da kurulum grup bazlı
 *                            temizliği desteklemiyorsa genel flush yapılır.
 * @return string[] Gerçekten çalıştırılan temizleyicilerin adları.
 */
if ( ! function_exists( 'qmo_tum_onbellek_temizle' ) ) {
	function qmo_tum_onbellek_temizle( $cache_group = '' ) {
		$temizlenen = array();

		/*
		 * 1) WordPress nesne önbelleği.
		 *
		 * Grup bazlı temizlik daha dar kapsamlıdır ama her arka uç
		 * desteklemez (wp_cache_supports() WP 6.1+). Desteklenmiyorsa
		 * genel flush'a düşülür — ayar kaydı seyrek bir işlemdir, genel
		 * flush'ın bedeli kabul edilebilir.
		 */
		$cache_group = is_string( $cache_group ) ? trim( $cache_group ) : '';

		if ( '' !== $cache_group
			&& function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' )
			&& function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $cache_group );
			$temizlenen[] = 'wp_cache_flush_group:' . $cache_group;
		} elseif ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$temizlenen[] = 'wp_cache_flush';
		}

		// 2) WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$temizlenen[] = 'wp_rocket';
		}

		// 3) LiteSpeed Cache — önce genel fonksiyon, yoksa eylem kancası.
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
			$temizlenen[] = 'litespeed';
		} elseif ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed\\Core' ) ) {
			do_action( 'litespeed_purge_all' );
			$temizlenen[] = 'litespeed';
		}

		// 4) W3 Total Cache.
		if ( class_exists( 'W3TC\\Dispatcher' ) ) {
			$flush = \W3TC\Dispatcher::component( 'CacheFlush' );
			if ( is_object( $flush ) && method_exists( $flush, 'flush_all' ) ) {
				$flush->flush_all();
				$temizlenen[] = 'w3tc';
			}
		}

		// 5) WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$temizlenen[] = 'wp_super_cache';
		}

		// 6) Autoptimize (birleştirilmiş CSS/JS sayfa önbelleği).
		if ( function_exists( 'autoptimize_flush_pagecache' ) ) {
			autoptimize_flush_pagecache();
			$temizlenen[] = 'autoptimize';
		}

		/**
		 * Listede olmayan bir önbellek katmanı için kanca.
		 *
		 * @param string[] $temizlenen  Çalıştırılan temizleyiciler.
		 * @param string   $cache_group İstenen nesne önbelleği grubu.
		 */
		do_action( 'qmo_onbellek_temizlendi', $temizlenen, $cache_group );

		return $temizlenen;
	}
}

/**
 * İstemci IP'sinin kısa hash'i — hız sınırlama anahtarlarında kullanılır.
 * Ham IP saklanmaz.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_ip_hash' ) ) {
	function qmo_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( md5( $ip . '|' . wp_salt( 'auth' ) ), 0, 16 );
	}
}

/**
 * IP + masa bazlı hız sınırı.
 *
 * @param string $anahtar Eylem adı (ör. 'garson').
 * @param string $masa    Masa slug'ı.
 * @param int    $saniye  Bekleme süresi.
 * @return bool true = izin var, false = çok sık istek.
 */
if ( ! function_exists( 'qmo_hiz_siniri' ) ) {
	function qmo_hiz_siniri( $anahtar, $masa, $saniye = 60 ) {
		$k = 'qmo_rl_' . md5( $anahtar . '|' . sanitize_title( $masa ) . '|' . qmo_ip_hash() );
		if ( get_transient( $k ) ) {
			return false;
		}
		set_transient( $k, 1, max( 1, (int) $saniye ) );
		return true;
	}
}

/* -------------------------------------------------------------------------
 * VERİTABANI BAĞLANTISININ UZUN DIŞ İSTEKLER BOYUNCA SERBEST BIRAKILMASI
 *
 * PHP, MySQL bağlantısını isteğin sonuna kadar açık tutar. Bu modüllerde
 * "isteğin sonu" 45 saniyelik bir Gemini çağrısının ya da arka planda tamamlanan
 * bir sipariş çevirisinin ardı demek olabiliyor — o süre boyunca bağlantı
 * TAMAMEN KULLANILMADAN havuzda yer kaplar. Aynı anda yirmi müşteri chatbot'a
 * yazdığında yirmi bağlantı, hiçbiri sorgu çalıştırmadan dakikalarca tutulur;
 * "Too many connections" hatasının en pahalı sebebi budur.
 *
 * Çözüm, HTTP çağrısını iki yardımcının arasına almaktır. Dikkat: wpdb::close()
 * sonrasında bağlantı KENDİLİĞİNDEN geri gelmez — wpdb `ready` bayrağını
 * düşürür ve sonraki sorgular sessizce false döner. Bu yüzden geri bağlanma
 * açıkça yapılır ve HTTP'den sonra veritabanına ihtiyaç duyan her kod
 * qmo_db_geri_baglan()'ın ARDINDA durmalıdır.
 * ---------------------------------------------------------------------- */

/**
 * Veritabanı bağlantısını geçici olarak kapatır.
 *
 * Yalnızca uzun süren bir dış istekten HEMEN ÖNCE çağrılmalıdır; ardından
 * qmo_db_geri_baglan() ile geri açılır.
 *
 * @return bool Bağlantı gerçekten kapatıldıysa true.
 */
if ( ! function_exists( 'qmo_db_serbest_birak' ) ) {
	function qmo_db_serbest_birak() {
		global $wpdb;

		/**
		 * Uzun dış istekler boyunca veritabanı bağlantısı bırakılsın mı?
		 *
		 * Kalıcı bağlantı kullanan ya da wpdb'yi değiştiren kurulumlarda
		 * (ör. HyperDB) `add_filter( 'qmo_db_baglanti_serbest', '__return_false' )`
		 * ile kapatılabilir.
		 *
		 * @param bool $serbest Varsayılan true.
		 */
		if ( ! apply_filters( 'qmo_db_baglanti_serbest', true ) ) {
			return false;
		}

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'close' )
			|| ! method_exists( $wpdb, 'db_connect' ) ) {
			return false;
		}

		return (bool) $wpdb->close();
	}
}

/**
 * qmo_db_serbest_birak() ile kapatılan bağlantıyı geri açar.
 *
 * @param bool $kapatildi qmo_db_serbest_birak() çıktısı.
 * @return void
 */
if ( ! function_exists( 'qmo_db_geri_baglan' ) ) {
	function qmo_db_geri_baglan( $kapatildi ) {
		global $wpdb;

		if ( ! $kapatildi ) {
			return;
		}

		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'db_connect' ) ) {
			$wpdb->db_connect();
		}
	}
}

/**
 * Kullanılacak Gemini modeli. Ayarlar sayfasından değiştirilebilir.
 *
 * @return string
 */
if ( ! function_exists( 'qmo_gemini_model' ) ) {
	function qmo_gemini_model() {
		$m = trim( (string) get_option( 'qmo_gemini_model', '' ) );
		return '' !== $m ? $m : 'gemini-3-flash-preview';
	}
}

/**
 * Oturum yokken kısa kodların bastığı bilgi kutusu.
 *
 * @param string $mesaj Gösterilecek metin.
 * @return string
 */
if ( ! function_exists( 'qmo_oturum_uyari_kutusu' ) ) {
	function qmo_oturum_uyari_kutusu( $mesaj = '' ) {
		if ( '' === $mesaj ) {
			$mesaj = __( 'Bu bölümü kullanmak için masanızdaki QR kodu okutun.', 'qrms' );
			if ( function_exists( 'rma_ceviri_modul' ) ) {
				// Uyarı kutusu kilit ekranı değildir: Accept-Language yok,
				// rma_get_current_lang() (?lang= → cookie → tr) kullanılır.
				$mesaj = rma_ceviri_modul( 'lock', $mesaj );
			}
		}
		qmo_asset_enqueue( 'qmo-oturum-kutu' );
		return '<div class="qmo-oturum-kutu"><span class="qmo-oturum-kutu-ikon">🔒</span>'
			. '<span>' . esc_html( $mesaj ) . '</span></div>';
	}
}

/* -------------------------------------------------------------------------
 * ANALİTİK YAZIM — qr-analiz lisansta yoksa no-op
 *
 * Chatbot sipariş/sepet/çağrı uçları buradan yazar. Sınıf yüklenmemişse
 * (modül pasif) hiçbir şey olmaz; analitik bir bağımlılık değildir.
 * ---------------------------------------------------------------------- */

/**
 * Bir analitik olayını sessizce kaydeder.
 *
 * Yazım QRMS_Analitik::kaydet() üzerinden gider; yeni INSERT yolu açılmaz.
 * Başarısızlık yutulur — çağıranın akışı kesilmesin.
 *
 * @param array $satir event_type ve isteğe bağlı item_id / item_name / category_name / masa_no.
 * @return void
 */
if ( ! function_exists( 'qmo_analitik_yaz' ) ) {
	function qmo_analitik_yaz( array $satir ) {
		if ( ! class_exists( 'QRMS_Analitik' ) ) {
			return;
		}

		try {
			QRMS_Analitik::kaydet( $satir );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return;
		}
	}
}

/**
 * Yayınlanmış bir menü ürününden analitik alanlarını çözer.
 *
 * Kimlik geçersizse (yok, yanlış tip, taslak) boş dizi döner; çağıran
 * o olayı atlar. Ad ve kategori SUNUCUDA okunur, istemciye güvenilmez.
 *
 * @param int $item_id Ürün kimliği.
 * @return array{item_id:int,item_name:string,category_name:string}|array{}
 */
if ( ! function_exists( 'qmo_analitik_urun_alani' ) ) {
	function qmo_analitik_urun_alani( $item_id ) {
		$item_id = absint( $item_id );

		if ( $item_id < 1 ) {
			return array();
		}

		$post = get_post( $item_id );

		if ( ! $post || 'rma_menu_item' !== $post->post_type || 'publish' !== $post->post_status ) {
			return array();
		}

		$terimler = wp_get_post_terms( $item_id, 'rma_category' );
		$kategori = ( ! is_wp_error( $terimler ) && ! empty( $terimler ) ) ? (string) $terimler[0]->name : '';

		return array(
			'item_id'       => $item_id,
			'item_name'     => (string) get_the_title( $item_id ),
			'category_name' => $kategori,
		);
	}
}

/**
 * Ürün adından yayınlanmış menü kaydını bulur (sipariş kalemleri kimlik taşımaz).
 *
 * Tam başlık eşleşmesi yoksa ad yine yazılır, item_id 0 kalır — sipariş
 * akışı bunun için ek sorguya boğulmasın.
 *
 * @param string $ad Türkçe ürün adı.
 * @return array{item_id:int,item_name:string,category_name:string}
 */
if ( ! function_exists( 'qmo_analitik_urun_ada_gore' ) ) {
	function qmo_analitik_urun_ada_gore( $ad ) {
		$ad = sanitize_text_field( (string) $ad );

		if ( '' === $ad ) {
			return array(
				'item_id'       => 0,
				'item_name'     => '',
				'category_name' => '',
			);
		}

		$posts = get_posts(
			array(
				'post_type'              => 'rma_menu_item',
				'post_status'            => 'publish',
				'title'                  => $ad,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
			)
		);

		if ( ! empty( $posts ) && isset( $posts[0]->ID ) ) {
			$alan = qmo_analitik_urun_alani( (int) $posts[0]->ID );

			if ( ! empty( $alan ) ) {
				return $alan;
			}
		}

		return array(
			'item_id'       => 0,
			'item_name'     => $ad,
			'category_name' => '',
		);
	}
}

/* -------------------------------------------------------------------------
 * Geriye dönük uyumluluk — eski snippet fonksiyon adları
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'qr_masa_oturum_zorla' ) ) {
	/**
	 * @deprecated qmo_oturum_zorla() kullanın.
	 * @return array
	 */
	function qr_masa_oturum_zorla() {
		return qmo_oturum_zorla();
	}
}

if ( ! function_exists( 'qr_masa_chat_zorla' ) ) {
	/**
	 * @deprecated qmo_chat_zorla() kullanın.
	 * @return array
	 */
	function qr_masa_chat_zorla() {
		return qmo_chat_zorla();
	}
}

if ( ! function_exists( 'qrservis_masa_gecerli_mi' ) ) {
	/**
	 * @deprecated qmo_masa_gecerli_mi() kullanın.
	 * @param string $slug Masa slug'ı.
	 * @return bool
	 */
	function qrservis_masa_gecerli_mi( $slug ) {
		return qmo_masa_gecerli_mi( $slug );
	}
}
