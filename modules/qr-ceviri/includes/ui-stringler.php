<?php
/**
 * Sabit arayüz metinleri (item_type = ui_string, item_id = 0).
 *
 * Bunlar bir posta/terime bağlı olmayan, tema ya da menü eklentisi tarafından
 * doğrudan basılan Türkçe metinlerdir; eşleşme ID ile değil `field` alanındaki
 * sabit anahtarla yapılır.
 *
 * Aşağıdaki liste bir BAŞLANGIÇ setidir. Sitede geçen her sabit metni kod
 * değişikliği olmadan ekleyebilmek için yönetim ekranındaki "Ek sabit metinler"
 * kutusu var — oraya her satıra bir metin yazmak yeterli.
 *
 * Sepet metinleri item_type=cart; yorum/form sabitleri item_type=review.
 * Yorum option/DB etiketleri (kriter adları, field_label) P1 / adım 7-2.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eklentiyle gelen varsayılan sabit metinler.
 *
 * Bu liste TAHMİN DEĞİL — QR MENÜ (RMA) eklentisinin kaynağından birebir
 * çıkarıldı. Metinler orada $this->t( … ) ile bu listedeki hâlleriyle
 * çağrılıyor; birini değiştirirsen RMA tarafındaki karşılığını da güncelle,
 * yoksa anahtar tutmaz ve çeviri düşer (Türkçeye fallback eder).
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_varsayilan_ui_metinleri' ) ) {
	function rma_ceviri_varsayilan_ui_metinleri() {
		return array(
			/* Araç çubuğu ve filtre paneli — trait-frontend.php: shortcode_menu() */
			'Ürün ara…',
			'Menüde ara',
			'Filtrele',
			'Filtrele ve Sırala',
			'Filtrele & Sırala',
			'Menü kategorileri',
			'Menü yükleniyor',
			'Yükleniyor',
			'Kapat',
			'Sıralama',
			'Varsayılan',
			'Önerilen sıra',
			'A → Z',
			'Alfabetik',
			'Ucuzdan Pahalıya',
			'Fiyat artan',
			'Pahalıdan Ucuya',
			'Fiyat azalan',
			'En Proteinli',
			'Protein yüksek',
			'En Az Karb',
			'Karbonhidrat azalan',
			'Diyet & Alerjen',
			'Alerjen Hariç Tut',
			'Sıfırla',
			'Uygula',
			'Ürün detayı',

			/* Kart ve modal — trait-frontend.php: render_card(), trait-ajax.php */
			'Önerilen',
			'Popüler',
			'Yeni',
			'Hazırlanış süresi',
			'dk',
			'Vegan',
			'Vejetaryen',
			'Glütensiz',
			'Acı',
			'kcal',
			'g',
			'g protein',
			'g karb',
			'g yağ',
			'Alkol İçerir',
			'Domuz Türevi İçerir',
			'Alerjen Uyarısı:',
			'Öneriler',
			'Diğer',
			'Ürün bulunamadı.',
			'Tükendi',
			'Bu ürün şu an tükendi',

			/* Yalnızca JS'te basılanlar — RMA_I18N köprüsüyle taşınır */
			'Yükleme hatası oluştu. Lütfen sayfayı yenileyin.',
			'Sayfa yenileniyor…',

			/* Et menşei seçenekleri — trait-helpers.php: get_meat_origin_options() */
			'Belirtilmemiş / Uygulanmaz',
			'Dana Eti',
			'Kuzu / Koyun Eti',
			'Tavuk Eti',
			'Hindi Eti',
			'Balık',
			'Deniz Ürünü',
			'Karışık (Birden Fazla Et Türü)',

			/* QR bilgilendirme kısa kodu — trait-frontend.php: shortcode_qr_notice() */
			'Bu bilgilere karekod ile ulaşabilirsiniz. Karekod kullanamayan tüketiciler talep ederse bilgi kendilerine ayrıca sunulur.',

			/* Header Footer Builder chrome — trait-frontend.php (aria + sütun varsayılanı) */
			'Ana menü',
			'Menüyü aç',
			'Mobil menü',
			'Menüyü kapat',
			'Lütfen QR kodunu okutarak masanızdan erişin',
			'Hızlı Menü',
			'Çalışma Saatlerimiz',
			'İletişim',
		);
	}
}

/**
 * Metinden kararlı bir anahtar üret ("Sepete Ekle" → ui_sepete_ekle).
 *
 * @param string $metin Türkçe metin.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_ui_anahtari' ) ) {
	function rma_ceviri_ui_anahtari( $metin ) {
		$slug = sanitize_title( $metin );
		$slug = str_replace( '-', '_', $slug );
		$slug = preg_replace( '/[^a-z0-9_]/', '', $slug );
		$slug = trim( (string) $slug, '_' );

		if ( '' === $slug ) {
			$slug = substr( md5( $metin ), 0, 8 );
		}

		// field sütunu 191 karakter; uzun metinlerde anahtarı kısalt.
		if ( strlen( $slug ) > 60 ) {
			$slug = substr( $slug, 0, 52 ) . '_' . substr( md5( $metin ), 0, 6 );
		}

		return 'ui_' . $slug;
	}
}

/**
 * Yöneticinin eklediği ek metinler (her satır bir metin).
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_ek_ui_metinleri' ) ) {
	function rma_ceviri_ek_ui_metinleri() {
		$ham = (string) get_option( 'rma_ceviri_ek_metinler', '' );
		if ( '' === trim( $ham ) ) {
			return array();
		}

		$satirlar = preg_split( '/\r\n|\r|\n/', $ham );
		$metinler = array();

		foreach ( (array) $satirlar as $satir ) {
			$satir = trim( $satir );
			if ( '' !== $satir ) {
				$metinler[] = $satir;
			}
		}

		return $metinler;
	}
}

/**
 * Tüm sabit metinler: anahtar => Türkçe metin.
 *
 * Aynı anahtara düşen iki farklı metin olursa ikincisine kısa bir hash eki
 * verilir; böylece CSV satırları birbirini ezmez.
 *
 * @return array<string,string>
 */
if ( ! function_exists( 'rma_ceviri_ui_stringleri' ) ) {
	function rma_ceviri_ui_stringleri() {
		$metinler = array_merge(
			rma_ceviri_varsayilan_ui_metinleri(),
			rma_ceviri_ek_ui_metinleri()
		);

		/**
		 * Sabit metin listesini süz/genişlet.
		 *
		 * @param string[] $metinler Metinler.
		 */
		$metinler = apply_filters( 'rma_ceviri_ui_metinleri', $metinler );

		return rma_ceviri_metinleri_anahtarli( $metinler );
	}
}

/**
 * Metin listesini field anahtarı => orijinal haritasına çevir.
 *
 * ui_string ve modül tipleri aynı anahtar kuralını paylaşır; ayrılırlarsa
 * CSV'deki field eşleşmesi bozulur.
 *
 * @param string[] $metinler Metinler.
 * @return array<string,string>
 */
if ( ! function_exists( 'rma_ceviri_metinleri_anahtarli' ) ) {
	function rma_ceviri_metinleri_anahtarli( $metinler ) {
		$sonuc = array();

		foreach ( (array) $metinler as $metin ) {
			$metin = trim( (string) $metin );
			if ( '' === $metin ) {
				continue;
			}

			$anahtar = rma_ceviri_ui_anahtari( $metin );

			if ( isset( $sonuc[ $anahtar ] ) && $sonuc[ $anahtar ] !== $metin ) {
				$anahtar .= '_' . substr( md5( $metin ), 0, 6 );
			}

			$sonuc[ $anahtar ] = $metin;
		}

		return $sonuc;
	}
}

/**
 * Sabit metnin geçerli dildeki karşılığı.
 *
 * Kısa yol: rma_translate_field()'in ui_string sarmalayıcısı.
 *
 * @param string $metin Türkçe metin.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_ui' ) ) {
	function rma_ceviri_ui( $metin ) {
		return rma_ceviri_modul( 'ui_string', $metin );
	}
}

/**
 * Modül bazlı sabit metin tipleri (item_id = 0).
 *
 * CSV sütunları değişmez; yalnızca item_type hücresi. varchar(20) sınırına uyar.
 *
 * @return array<string,string> tip => Sistem Durumu etiketi.
 */
if ( ! function_exists( 'rma_ceviri_modul_tipleri' ) ) {
	function rma_ceviri_modul_tipleri() {
		return array(
			'splash'  => 'Açılış ekranı',
			'hours'   => 'Çalışma saatleri',
			'chat'    => 'Chatbot ve çağrı',
			'cart'    => 'Sepet',
			'review'  => 'Yorum ve formlar',
			'gallery' => 'Galeri',
			'lock'    => 'Oturum kilidi',
		);
	}
}

/**
 * Bu tip item_id=0 sabit metin mi? (ui_string + modül tipleri)
 *
 * @param string $tip item_type.
 * @return bool
 */
if ( ! function_exists( 'rma_ceviri_modul_sabit_mi' ) ) {
	function rma_ceviri_modul_sabit_mi( $tip ) {
		return 'ui_string' === $tip || isset( rma_ceviri_modul_tipleri()[ $tip ] );
	}
}

/**
 * CSV ve Sistem Durumu'nda kabul edilen tüm item_type değerleri.
 *
 * Yeni tip eklemek CSV sütunlarını değiştirmez; eski dışa aktarımlar
 * okunabilir kalır.
 *
 * @return string[]
 */
if ( ! function_exists( 'rma_ceviri_gecerli_tipler' ) ) {
	function rma_ceviri_gecerli_tipler() {
		return array_merge(
			array( 'product', 'category', 'allergen', 'nav_menu', 'ui_string', 'elementor' ),
			array_keys( rma_ceviri_modul_tipleri() )
		);
	}
}

/**
 * Modül tipine göre kaynak Türkçe metinler.
 *
 * P0 sırasıyla doldurulur. Boş dizi = henüz kaynak yok; Sistem Durumu
 * satırı yine görünür.
 *
 * @return array<string,string[]> tip => metin listesi.
 */
if ( ! function_exists( 'rma_ceviri_modul_kaynak_metinleri' ) ) {
	function rma_ceviri_modul_kaynak_metinleri() {
		$katalog = array(
			'splash'  => array(
				'Nakit',
				'Kart',
			),
			'hours'   => array(
				'Pazartesi',
				'Salı',
				'Çarşamba',
				'Perşembe',
				'Cuma',
				'Cumartesi',
				'Pazar',
				'Kapalı',
				'24 saat açık',
				'%1$s – %2$s',
			),
			'chat'    => array(
				'Asistanı kullanmak için masanızdaki QR kodu okutun.',
				'Çevrimiçi',
				'Kapat',
				'Gönder',
				'Çağrı butonlarını kullanmak için masanızdaki QR kodu okutun.',
				'Garson Çağır',
				'Hesap İste',
				'Garson çağrınız iletildi.',
				'Hesap talebiniz iletildi.',
				'İstek iletilemedi, lütfen tekrar deneyin.',
				'Bağlantı hatası oluştu.',
				'Yazıyor...',
				'Bir hata oluştu, lütfen tekrar deneyin.',
				'Bir hata oluştu.',
				'Siparişiniz iletilemedi, lütfen garsona bildirin.',
				'Asistan şu anda yanıt veremiyor, lütfen tekrar deneyin.',
				'Hata: Mesaj boş geldi.',
				'Oturum süreniz doldu. Devam etmek için masadaki QR kodu tekrar okutun.',
				'Bu oturum için mesaj limitine ulaştınız.',
				'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.',
				'Geçersiz sipariş',
				'Sipariş iletilemedi, lütfen garsona bildirin.',
				'Çağrı sistemi şu anda kullanılamıyor.',
				'Çağrınız iletildi, lütfen bekleyin.',
				'İletildi',
			),
			'cart'    => array(
				'Sepet',
				'Sepetiniz',
				'Toplam',
				'Siparişi Gönder',
				'Sepetiniz boş',
				'Ürün notu (isteğe bağlı)…',
				'Sepete eklendi',
				'Siparişiniz mutfağa iletildi ✓',
				'Gönderilemedi, tekrar deneyin',
				'Ödeme TL üzerinden alınır.',
				'Sepeti aç',
				'Sil',
				'Kapat',
			),
			'review'  => array(
				'Devam Et →',
				'← Geri',
				'Gönder',
				'Güvenlik sorusu:',
				'0 (5__) ___ __ __',
				'%d Değerlendirme',
				'Henüz yayınlanmış bir değerlendirme yok. İlk yorumu siz bırakın!',
				'Daha Fazla Göster',
				'Anonim Misafir',
				'Misafir',
				'Kapat',
				'Kod ayrıca e-posta adresinize gönderildi.',
				'Tamam',
				'Tekrar Dene',
				'Lütfen geçerli bir e-posta adresi girin.',
				'Seçiniz…',
				'Gönderiliyor…',
				'Gönderim tamamlanamadı, lütfen tekrar deneyin.',
				'Bağlantı hatası, lütfen tekrar deneyin.',
				'Bir şeyler ters gitti, lütfen tekrar deneyin.',
				'Devam etmek için lütfen tüm kriterleri puanlayın.',
				'Değerlendirmeniz için teşekkürler!',
				'Yükleniyor…',
				'Değerlendirmeniz alındı, teşekkürler.',
				'Lütfen en az bir kriteri puanlayın.',
				'Geçerli bir Türkiye cep numarası girin. Örn: 0 (5XX) XXX XX XX',
				'Değerlendirmeniz kaydedilemedi, lütfen tekrar deneyin.',
				'Değerlendirmeniz yayınlandı.',
				'Değerlendirmeniz alındı, onay sonrası yayınlanacaktır.',
				'Bu form şu anda gönderime kapalı.',
				'Bu formda tanımlı alan yok.',
				'Gönderiminiz kaydedilemedi, lütfen tekrar deneyin.',
				'Çok fazla gönderim algılandı, lütfen birkaç dakika sonra tekrar deneyin.',
				'Form çok hızlı gönderildi, lütfen birkaç saniye bekleyip tekrar deneyin.',
				'Güvenlik sorusunun cevabı hatalı.',
				'Çok sık gönderim yapıyorsunuz, lütfen %d dakika sonra tekrar deneyin.',
				'Çok fazla deneme yapıldı, lütfen birkaç dakika sonra tekrar deneyin.',
				'Ödül sistemi şu anda kapalı.',
				'Bu ödül talebi doğrulanamadı. Lütfen değerlendirmenizi yeniden gönderin.',
				'Değerlendirme bulunamadı.',
				'Bu değerlendirme ödül koşulunu karşılamıyor.',
				'Bu değerlendirme için zaten bir indirim kodu üretilmiş.',
				'Geçerli bir e-posta adresi girin.',
				'Bu e-posta adresi daha önce bir indirim kodu almış.',
				'Kullanılabilir bir indirim şablonu bulunamadı. Ödül Sistemi sayfasından en az bir aktif şablon tanımlayın.',
				'Kod kaydedilemedi, lütfen tekrar deneyin.',
				'"%s" alanı zorunludur.',
				'"%s" alanına geçerli bir e-posta adresi girin.',
				'"%s" alanına geçerli bir telefon numarası girin.',
				'"%s" alanına yalnızca sayı girin.',
				'"%s" alanı için 1-5 arası bir puan seçin.',
				'"%s" alanına geçerli bir tarih seçin.',
				'"%s" alanı için geçersiz bir seçenek gönderildi.',
			),
			'gallery' => array(
				'Tümü',
				'Galeri bulunamadı.',
			),
			'lock'    => array(
				'Oturum Gerekli',
				'Bu masa için geçerli bir QR kod bulunamadı. Lütfen masanızdaki QR kodu okutun.',
				'Oturumunuz sona erdi. Devam etmek için masadaki QR kodu tekrar okutun.',
				'Bu bölümü kullanmak için masanızdaki QR kodu okutun.',
			),
		);

		/**
		 * Modül sabit metin katalogunu süz/genişlet.
		 *
		 * @param array<string,string[]> $katalog Tip => metinler.
		 */
		return apply_filters( 'rma_ceviri_modul_kaynak_metinleri', $katalog );
	}
}

/**
 * Bir modül tipinin anahtar => orijinal haritası.
 *
 * @param string $tip item_type.
 * @return array<string,string>
 */
if ( ! function_exists( 'rma_ceviri_modul_stringleri' ) ) {
	function rma_ceviri_modul_stringleri( $tip ) {
		$katalog = rma_ceviri_modul_kaynak_metinleri();
		$metinler = isset( $katalog[ $tip ] ) ? $katalog[ $tip ] : array();

		return rma_ceviri_metinleri_anahtarli( $metinler );
	}
}

/**
 * Modül sabit metninin geçerli dildeki karşılığı.
 *
 * Fallback sırası çağıran taraftadır: tablo (bu fonksiyon) → textdomain
 * (`__( $turkce, 'qrms' )` ile sarmalanmış girdi) → Türkçe kaynak.
 * Çeviri yoksa veya boşsa Türkçe (girdi) döner; anahtar adı asla basılmaz.
 *
 * @param string      $tip   splash|hours|chat|cart|review|gallery|lock|ui_string.
 * @param string      $metin Türkçe kaynak (veya textdomain çıktısı — site dili TR iken aynı).
 * @param string|null $lang  Dil kodu; null ise rma_get_current_lang(). Kilit
 *                           ekranı Accept-Language'ı buradan geçirir — genel
 *                           dil zincirine eklenmez.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_modul' ) ) {
	function rma_ceviri_modul( $tip, $metin, $lang = null ) {
		$metin = (string) $metin;

		if ( '' === $metin ) {
			return $metin;
		}

		if ( ! function_exists( 'rma_translate_field' ) ) {
			return $metin;
		}

		$ceviri = rma_translate_field( 0, $tip, rma_ceviri_ui_anahtari( $metin ), $metin, $lang );

		return ( '' !== (string) $ceviri ) ? (string) $ceviri : $metin;
	}
}
