<?php
/**
 * Çeviri kaynakları: hangi post type ve taxonomy'ler taranacak.
 *
 * Menü eklentisinin (RMA) kaynak koduna erişilemediği için slug'lar
 * HARDCODE EDİLMEZ. Varsayılanlar sitede kayıtlı tiplerden otomatik tahmin
 * edilir, yönetim ekranından değiştirilebilir. Böylece gerçek slug
 * `rma_menu_item` de olsa başka bir şey de olsa sistem çalışır.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Alerjen taxonomy'sini adından tanıyan desen — iki yerde kullanılıyor. */
if ( ! defined( 'RMA_CEVIRI_ALERJEN_DESEN' ) ) {
	define( 'RMA_CEVIRI_ALERJEN_DESEN', '/(allerg|alerjen)/i' );
}

/**
 * Bu post type bir menü ürünü olabilir mi?
 *
 * `item_type=product` satırları YALNIZCA RMA'nın kart/modal render
 * noktalarında tüketiliyor (render_card(), ajax_get_product_details() —
 * ikisi de rma_menu_item'a bağlı). Dolayısıyla sistem post'ları,
 * Elementor şablonları ve `post`/`page` için üretilen product satırları
 * hiçbir zaman kullanılamaz; CSV'yi şişirir ve kullanıcıyı yanıltır.
 *
 * Sayfa ve yazı metinleri "Elementor sayfaları" bölümünden yönetilir
 * (item_type=elementor), sayfa başlıkları dahil.
 *
 * @param string $slug Post type slug'ı.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_urun_tipi_uygun_mu' ) ) {
	function rma_ceviri_urun_tipi_uygun_mu( $slug ) {
		$haric = array(
			// WordPress iç kayıtları.
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			// Menü ürünü değil; Elementor bölümünden çevrilir.
			'post',
			'page',
		);

		/**
		 * Ürün olamayacak post type'lar.
		 *
		 * @param string[] $haric Slug listesi.
		 */
		$haric = (array) apply_filters( 'rma_ceviri_haric_urun_tipleri', $haric );

		if ( in_array( $slug, $haric, true ) ) {
			return false;
		}

		// Elementor'ün tüm kayıtları (elementor_library, e-landing-page, …).
		return ! preg_match( '/^(elementor|e-)/i', $slug );
	}
}

/**
 * Bu taxonomy kategori/alerjen kaynağı olabilir mi?
 *
 * Çekirdeğin `category` / `post_tag`'i uygundur (bazı kurulumlarda menü
 * kategorileri orada duruyor); Elementor'ün şablon taksonomileri ve
 * WordPress'in yapısal taksonomileri değildir.
 *
 * @param string $slug Taxonomy slug'ı.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_taksonomi_uygun_mu' ) ) {
	function rma_ceviri_taksonomi_uygun_mu( $slug ) {
		$haric = array(
			'nav_menu',
			'link_category',
			'post_format',
			'wp_theme',
			'wp_template_part_area',
			'wp_pattern_category',
		);

		/**
		 * Çeviri kaynağı olamayacak taxonomy'ler.
		 *
		 * @param string[] $haric Slug listesi.
		 */
		$haric = (array) apply_filters( 'rma_ceviri_haric_taksonomiler', $haric );

		if ( in_array( $slug, $haric, true ) ) {
			return false;
		}

		return ! preg_match( '/^(elementor|e-)/i', $slug );
	}
}

/**
 * Yönetim ekranında seçilebilecek ürün post type'ları.
 *
 * Admin listesi ile doğrulama TEK kaynaktan beslenir: burada olmayan bir
 * slug seçili kalsa bile dışa aktarıma giremez (eski/bozuk seçenekler
 * böylece kendiliğinden temizlenir).
 *
 * @return array<string,string> slug => etiket.
 */
if ( ! function_exists( 'rma_ceviri_uygun_urun_tipleri' ) ) {
	function rma_ceviri_uygun_urun_tipleri() {
		$sonuc = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $slug => $nesne ) {
			if ( rma_ceviri_urun_tipi_uygun_mu( $slug ) ) {
				$sonuc[ $slug ] = $nesne->labels->singular_name;
			}
		}

		return $sonuc;
	}
}

/**
 * Yönetim ekranında seçilebilecek taxonomy'ler.
 *
 * @return array<string,string> slug => etiket.
 */
if ( ! function_exists( 'rma_ceviri_uygun_taksonomiler' ) ) {
	function rma_ceviri_uygun_taksonomiler() {
		$sonuc = array();

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $slug => $nesne ) {
			if ( rma_ceviri_taksonomi_uygun_mu( $slug ) ) {
				$sonuc[ $slug ] = $nesne->labels->singular_name;
			}
		}

		return $sonuc;
	}
}

/**
 * Menü ürünü olabilecek post type'ları tahmin et.
 *
 * QR MENÜ kuruluysa cevap tartışmasız: rma_menu_item. Ada göre tahmin
 * yalnızca RMA yoksa devreye girer.
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_tahmini_urun_tipleri' ) ) {
	function rma_ceviri_tahmini_urun_tipleri() {
		if ( post_type_exists( 'rma_menu_item' ) ) {
			return array( 'rma_menu_item' );
		}

		$bulunan = array();

		foreach ( array_keys( rma_ceviri_uygun_urun_tipleri() ) as $slug ) {
			if ( preg_match( '/(rma|menu|urun|ürün|product|yemek|item)/i', $slug ) ) {
				$bulunan[] = $slug;
			}
		}

		return $bulunan;
	}
}

/**
 * Dışa aktarılacak ürün post type'ları.
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_urun_tipleri' ) ) {
	function rma_ceviri_urun_tipleri() {
		$secili = get_option( 'rma_ceviri_urun_tipleri', null );

		if ( null === $secili ) {
			return rma_ceviri_tahmini_urun_tipleri();
		}
		if ( ! is_array( $secili ) ) {
			return array();
		}

		return array_values( array_intersect( $secili, array_keys( rma_ceviri_uygun_urun_tipleri() ) ) );
	}
}

/**
 * Site genelindeki taxonomy'leri tahmine göre ayır.
 *
 * @param string $tur 'category' ya da 'allergen'.
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_tahmini_taksonomiler' ) ) {
	function rma_ceviri_tahmini_taksonomiler( $tur ) {
		if ( 'allergen' === $tur ) {
			if ( taxonomy_exists( 'rma_allergen' ) ) {
				return array( 'rma_allergen' );
			}
			$desen = RMA_CEVIRI_ALERJEN_DESEN;
		} else {
			if ( taxonomy_exists( 'rma_category' ) ) {
				return array( 'rma_category' );
			}
			$desen = '/(categor|kategori)/i';
		}

		$bulunan = array();

		foreach ( array_keys( rma_ceviri_uygun_taksonomiler() ) as $slug ) {
			if ( preg_match( $desen, $slug ) ) {
				$bulunan[] = $slug;
			}
		}

		return $bulunan;
	}
}

/**
 * Kategori ve alerjen listelerini ayrık hâle getir.
 *
 * Aynı taxonomy iki kovada da seçiliyse TÜM terimleri hem `category` hem
 * `allergen` tipiyle dışa aktarılırdı — item_type benzersiz anahtarın
 * parçası olduğu için ikisi de içe aktarılır ve yanlış tipteki kopya ölü
 * veri olarak kalırdı. Çakışma adına göre çözülür: alerjen deseni tutuyorsa
 * alerjen kovasında kalır, tutmuyorsa kategoride.
 *
 * WordPress'e bağımsız — test edilebilir.
 *
 * @param string[] $kategori Kategori taxonomy'leri.
 * @param string[] $alerjen  Alerjen taxonomy'leri.
 * @return array{0:string[],1:string[]} Ayrık [kategori, alerjen].
 */
if ( ! function_exists( 'rma_ceviri_taks_ayir' ) ) {
	function rma_ceviri_taks_ayir( $kategori, $alerjen ) {
		$kategori = array_values( array_unique( (array) $kategori ) );
		$alerjen  = array_values( array_unique( (array) $alerjen ) );

		foreach ( array_intersect( $kategori, $alerjen ) as $slug ) {
			if ( preg_match( RMA_CEVIRI_ALERJEN_DESEN, $slug ) ) {
				$kategori = array_diff( $kategori, array( $slug ) );
			} else {
				$alerjen = array_diff( $alerjen, array( $slug ) );
			}
		}

		return array( array_values( $kategori ), array_values( $alerjen ) );
	}
}

/**
 * Dışa aktarılacak taxonomy'ler.
 *
 * @param string $tur 'category' ya da 'allergen'.
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_taksonomiler' ) ) {
	function rma_ceviri_taksonomiler( $tur ) {
		$uygun = array_keys( rma_ceviri_uygun_taksonomiler() );

		$oku = function ( $secenek, $varsayilan_turu ) use ( $uygun ) {
			$secili = get_option( $secenek, null );

			if ( null === $secili ) {
				return rma_ceviri_tahmini_taksonomiler( $varsayilan_turu );
			}
			if ( ! is_array( $secili ) ) {
				return array();
			}

			return array_values( array_intersect( $secili, $uygun ) );
		};

		list( $kategori, $alerjen ) = rma_ceviri_taks_ayir(
			$oku( 'rma_ceviri_kategori_taks', 'category' ),
			$oku( 'rma_ceviri_alerjen_taks', 'allergen' )
		);

		return 'allergen' === $tur ? $alerjen : $kategori;
	}
}

/**
 * Post içeriği Elementor ile mi düzenlenmiş?
 *
 * Öyleyse post_content otomatik üretilmiş işaretleme taşır; onu çeviri
 * satırı olarak çıkarmak anlamsız (metinler _elementor_data'da).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_elementor_postu_mu' ) ) {
	function rma_ceviri_elementor_postu_mu( $post_id ) {
		return (bool) get_post_meta( (int) $post_id, '_elementor_edit_mode', true );
	}
}

/**
 * Ürün satırları (başlık, özet, içerik).
 *
 * Bellek için 200'lük gruplar hâlinde okunur ve generator ile akıtılır.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_urun_satirlari' ) ) {
	function rma_ceviri_urun_satirlari() {
		$tipler = rma_ceviri_urun_tipleri();

		if ( empty( $tipler ) ) {
			return;
		}

		$sayfa  = 1;
		$boyut  = 200;

		do {
			$sorgu = new WP_Query(
				array(
					'post_type'           => $tipler,
					'post_status'         => array( 'publish', 'draft', 'private' ),
					'posts_per_page'      => $boyut,
					'paged'               => $sayfa,
					'orderby'             => 'ID',
					'order'               => 'ASC',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			);

			if ( empty( $sorgu->posts ) ) {
				return;
			}

			foreach ( $sorgu->posts as $post ) {
				$alanlar = array(
					'title'   => $post->post_title,
					'excerpt' => $post->post_excerpt,
				);

				if ( ! rma_ceviri_elementor_postu_mu( $post->ID ) ) {
					$alanlar['content'] = $post->post_content;
				}

				foreach ( $alanlar as $alan => $metin ) {
					if ( '' === trim( (string) $metin ) ) {
						continue;
					}

					yield array(
						'item_id'   => (int) $post->ID,
						'item_type' => 'product',
						'field'     => $alan,
						'original'  => (string) $metin,
					);
				}
			}

			$adet = count( $sorgu->posts );
			++$sayfa;
		} while ( $adet === $boyut );
	}
}

/**
 * Taxonomy terim satırları.
 *
 * @param string $tur       'category' ya da 'allergen'.
 * @param string $item_type CSV'ye yazılacak tip.
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_terim_satirlari' ) ) {
	function rma_ceviri_terim_satirlari( $tur, $item_type ) {
		$taksonomiler = rma_ceviri_taksonomiler( $tur );

		if ( empty( $taksonomiler ) ) {
			return;
		}

		$terimler = get_terms(
			array(
				'taxonomy'   => $taksonomiler,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terimler ) ) {
			return;
		}

		foreach ( $terimler as $terim ) {
			if ( '' === trim( (string) $terim->name ) ) {
				continue;
			}

			yield array(
				'item_id'   => (int) $terim->term_id,
				'item_type' => $item_type,
				'field'     => 'name',
				'original'  => (string) $terim->name,
			);
		}
	}
}

/**
 * WordPress menü öğesi satırları (footer/header menü etiketleri).
 *
 * Menü etiketleri yapısal veri — tahmine gerek yok. Her öğe kendi ID'siyle
 * çevrildiği için aynı etiket sayfanın başka yerinde geçse bozulmaz.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_menu_satirlari' ) ) {
	function rma_ceviri_menu_satirlari() {
		$menuler = wp_get_nav_menus();

		if ( is_wp_error( $menuler ) || empty( $menuler ) ) {
			return;
		}

		foreach ( $menuler as $menu ) {
			$ogeler = wp_get_nav_menu_items( $menu->term_id );

			if ( empty( $ogeler ) || is_wp_error( $ogeler ) ) {
				continue;
			}

			foreach ( $ogeler as $oge ) {
				$alanlar = array(
					'title'       => isset( $oge->title ) ? (string) $oge->title : '',
					'description' => isset( $oge->description ) ? (string) $oge->description : '',
				);

				foreach ( $alanlar as $alan => $metin ) {
					if ( '' === trim( $metin ) ) {
						continue;
					}

					yield array(
						'item_id'   => (int) $oge->ID,
						'item_type' => 'nav_menu',
						'field'     => $alan,
						'original'  => $metin,
					);
				}
			}
		}
	}
}

/**
 * Sabit UI metin satırları.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_ui_satirlari' ) ) {
	function rma_ceviri_ui_satirlari() {
		foreach ( rma_ceviri_ui_stringleri() as $anahtar => $metin ) {
			yield array(
				'item_id'   => 0,
				'item_type' => 'ui_string',
				'field'     => $anahtar,
				'original'  => $metin,
			);
		}
	}
}

/**
 * Seçili Elementor sayfalarının satırları.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_elementor_satirlari' ) ) {
	function rma_ceviri_elementor_satirlari() {
		foreach ( rma_ceviri_secili_elementor_sayfalari() as $post_id ) {
			if ( ! get_post_status( $post_id ) ) {
				continue;
			}

			foreach ( rma_ceviri_elementor_alan_haritasi( $post_id ) as $alan => $orijinal ) {
				yield array(
					'item_id'   => (int) $post_id,
					'item_type' => 'elementor',
					'field'     => $alan,
					'original'  => $orijinal,
				);
			}
		}
	}
}

/**
 * Bir Elementor sayfasının çevrilebilir alanları: alan => orijinal metin.
 *
 * Dışa aktarım ve içe aktarımdaki bayatlık kontrolü AYNI haritayı kullanır;
 * ayrılırlarsa export'un ürettiği bir alan import'ta "hedef bulunamadı"
 * diye atlanır.
 *
 * `post_title` de buraya dahil: sayfa/yazı post type'ları artık `product`
 * kaynağı olmadığı için sayfa başlıkları buradan çevriliyor. Nokta
 * içermediği için Elementor widget alanlarıyla ({widget_id}.{key})
 * karışmaz.
 *
 * @param int $post_id Post ID.
 * @return array<string,string>
 */
if ( ! function_exists( 'rma_ceviri_elementor_alan_haritasi' ) ) {
	function rma_ceviri_elementor_alan_haritasi( $post_id ) {
		$harita = array();

		$baslik = (string) get_post_field( 'post_title', (int) $post_id );
		if ( '' !== trim( $baslik ) ) {
			$harita['post_title'] = $baslik;
		}

		foreach ( rma_ceviri_elementor_tara( $post_id ) as $satir ) {
			$harita[ $satir['field'] ] = $satir['original'];
		}

		return $harita;
	}
}

/**
 * Satır akışını tekilleştir.
 *
 * Aynı item_type|item_id|field üçlüsü iki kez üretilirse (bir terim iki
 * seçili taxonomy'de olabilir, bir sayfa iki kez seçilebilir) CSV'de
 * mükerrer satır oluşur ve içe aktarımda biri diğerini ezer.
 *
 * WordPress'e bağımsız — test edilebilir.
 *
 * @param iterable $satirlar Ham satırlar.
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_satirlari_tekille' ) ) {
	function rma_ceviri_satirlari_tekille( $satirlar ) {
		$gorulen = array();

		foreach ( $satirlar as $satir ) {
			$anahtar = $satir['item_type'] . '|' . (int) $satir['item_id'] . '|' . $satir['field'];

			if ( isset( $gorulen[ $anahtar ] ) ) {
				continue;
			}

			$gorulen[ $anahtar ] = true;
			yield $satir;
		}
	}
}

/**
 * Dışa aktarılacak ham satırlar (tekilleştirilmemiş).
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_ham_kaynak_satirlari' ) ) {
	function rma_ceviri_ham_kaynak_satirlari() {
		yield from rma_ceviri_urun_satirlari();
		yield from rma_ceviri_terim_satirlari( 'category', 'category' );
		yield from rma_ceviri_terim_satirlari( 'allergen', 'allergen' );
		yield from rma_ceviri_menu_satirlari();
		yield from rma_ceviri_ui_satirlari();
		yield from rma_ceviri_elementor_satirlari();
	}
}

/**
 * Dışa aktarılacak tüm satırlar, tek akış hâlinde.
 *
 * @return Generator<array{item_id:int,item_type:string,field:string,original:string}>
 */
if ( ! function_exists( 'rma_ceviri_kaynak_satirlari' ) ) {
	function rma_ceviri_kaynak_satirlari() {
		yield from rma_ceviri_satirlari_tekille( rma_ceviri_ham_kaynak_satirlari() );
	}
}

/**
 * Bir satırın ŞU ANKİ orijinal metni (bayatlık kontrolü için).
 *
 * Elementor taramaları post başına bir kez yapılıp bellekte tutulur.
 *
 * @param int    $item_id   Öğe ID.
 * @param string $item_type Öğe tipi.
 * @param string $field     Alan.
 * @return string|null Bulunamazsa null.
 */
if ( ! function_exists( 'rma_ceviri_guncel_orijinal' ) ) {
	function rma_ceviri_guncel_orijinal( $item_id, $item_type, $field ) {
		static $elementor_onbellek = array();

		switch ( $item_type ) {
			case 'product':
				$post = get_post( (int) $item_id );
				if ( ! $post ) {
					return null;
				}
				$harita = array(
					'title'   => $post->post_title,
					'excerpt' => $post->post_excerpt,
					'content' => $post->post_content,
				);
				return isset( $harita[ $field ] ) ? (string) $harita[ $field ] : null;

			case 'category':
			case 'allergen':
				$terim = get_term( (int) $item_id );
				if ( ! $terim || is_wp_error( $terim ) ) {
					return null;
				}
				return 'name' === $field ? (string) $terim->name : null;

			case 'nav_menu':
				$oge = get_post( (int) $item_id );
				if ( ! $oge || 'nav_menu_item' !== $oge->post_type ) {
					return null;
				}
				// Etiket post_title'da değil; wp_setup_nav_menu_item() onu
				// bağlı nesneden (sayfa/kategori) çözüp ->title'a yazıyor.
				$oge = wp_setup_nav_menu_item( $oge );
				if ( 'title' === $field ) {
					return isset( $oge->title ) ? (string) $oge->title : null;
				}
				if ( 'description' === $field ) {
					return isset( $oge->description ) ? (string) $oge->description : null;
				}
				return null;

			case 'ui_string':
				$metinler = rma_ceviri_ui_stringleri();
				return isset( $metinler[ $field ] ) ? $metinler[ $field ] : null;

			case 'elementor':
				$id = (int) $item_id;
				if ( ! isset( $elementor_onbellek[ $id ] ) ) {
					// Export ile aynı harita — alanlar birebir örtüşsün.
					$elementor_onbellek[ $id ] = rma_ceviri_elementor_alan_haritasi( $id );
				}
				return isset( $elementor_onbellek[ $id ][ $field ] ) ? $elementor_onbellek[ $id ][ $field ] : null;
		}

		return null;
	}
}
