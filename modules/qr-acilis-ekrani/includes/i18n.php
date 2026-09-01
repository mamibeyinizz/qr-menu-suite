<?php
/**
 * Açılış ekranı sabit metin çevirileri.
 *
 * Bayrak seçici (QR Çeviri) veya TR/EN düğmesi açıkken buton etiketleri,
 * ayraç metni ve modal başlıkları bu haritadan okunur. Sunucu çıktısı her
 * ziyaretçide aynı kalır; tüm diller data-sp-{kod} nitelikleriyle taşınır,
 * hangisinin görüneceğine istemci karar verir.
 *
 * @package QR_Menu_Suite
 */

defined( 'ABSPATH' ) || exit;

trait QRMS_AE_I18n {

	/**
	 * Metin anahtarı → dil kodu → çeviri.
	 *
	 * TR/EN/DE önceliklidir; diğer diller için EN, o da yoksa TR'ye düşülür.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function i18n_catalog() {
		return array(
			'btn1' => array(
				'tr' => 'Menüye Git',
				'en' => 'View Menu',
				'de' => 'Zum Menü',
				'fr' => 'Voir le menu',
				'es' => 'Ver menú',
				'it' => 'Vai al menu',
				'ru' => 'К меню',
				'ar' => 'انتقل إلى القائمة',
				'nl' => 'Naar menu',
				'pl' => 'Przejdź do menu',
				'pt' => 'Ver cardápio',
			),
			'btn2' => array(
				'tr' => 'İletişim',
				'en' => 'Contact',
				'de' => 'Kontakt',
				'fr' => 'Contact',
				'es' => 'Contacto',
				'it' => 'Contatti',
				'ru' => 'Контакты',
				'ar' => 'اتصل بنا',
				'nl' => 'Contact',
				'pl' => 'Kontakt',
				'pt' => 'Contato',
			),
			'btn3' => array(
				'tr' => 'Rezervasyon İste',
				'en' => 'Request Reservation',
				'de' => 'Reservierung anfragen',
				'fr' => 'Demander une réservation',
				'es' => 'Solicitar reserva',
				'it' => 'Richiedi prenotazione',
				'ru' => 'Запросить бронь',
				'ar' => 'طلب حجز',
				'nl' => 'Reservering aanvragen',
				'pl' => 'Poproś o rezerwację',
				'pt' => 'Solicitar reserva',
			),
			'btn4' => array(
				'tr' => 'Yorum Yap',
				'en' => 'Leave a Review',
				'de' => 'Bewertung schreiben',
				'fr' => 'Laisser un avis',
				'es' => 'Dejar reseña',
				'it' => 'Lascia una recensione',
				'ru' => 'Оставить отзыв',
				'ar' => 'اترك تعليقاً',
				'nl' => 'Review schrijven',
				'pl' => 'Napisz opinię',
				'pt' => 'Deixar avaliação',
			),
			'btn5' => array(
				'tr' => 'Wifi Şifresi',
				'en' => 'Wi-Fi Password',
				'de' => 'WLAN-Passwort',
				'fr' => 'Mot de passe Wi-Fi',
				'es' => 'Contraseña Wi-Fi',
				'it' => 'Password Wi-Fi',
				'ru' => 'Пароль Wi-Fi',
				'ar' => 'كلمة مرور الواي فاي',
				'nl' => 'Wifi-wachtwoord',
				'pl' => 'Hasło Wi-Fi',
				'pt' => 'Senha Wi-Fi',
			),
			'btn6' => array(
				'tr' => 'Sosyal Medya',
				'en' => 'Social Media',
				'de' => 'Soziale Medien',
				'fr' => 'Réseaux sociaux',
				'es' => 'Redes sociales',
				'it' => 'Social media',
				'ru' => 'Соцсети',
				'ar' => 'وسائل التواصل',
				'nl' => 'Sociale media',
				'pl' => 'Media społecznościowe',
				'pt' => 'Redes sociais',
			),
			'divider' => array(
				'tr' => 'Bizi takip edin',
				'en' => 'Follow us',
				'de' => 'Folgen Sie uns',
				'fr' => 'Suivez-nous',
				'es' => 'Síguenos',
				'it' => 'Seguici',
				'ru' => 'Подписывайтесь',
				'ar' => 'تابعنا',
				'nl' => 'Volg ons',
				'pl' => 'Obserwuj nas',
				'pt' => 'Siga-nos',
			),
			'wifi_title' => array(
				'tr' => 'Wifi Şifresi',
				'en' => 'Wi-Fi Password',
				'de' => 'WLAN-Passwort',
				'fr' => 'Mot de passe Wi-Fi',
				'es' => 'Contraseña Wi-Fi',
				'it' => 'Password Wi-Fi',
				'ru' => 'Пароль Wi-Fi',
				'ar' => 'كلمة مرور الواي فاي',
				'nl' => 'Wifi-wachtwoord',
				'pl' => 'Hasło Wi-Fi',
				'pt' => 'Senha Wi-Fi',
			),
			'wifi_empty' => array(
				'tr' => 'Henüz bir şifre girilmedi.',
				'en' => 'No password has been set yet.',
				'de' => 'Noch kein Passwort hinterlegt.',
				'fr' => 'Aucun mot de passe défini.',
				'es' => 'Aún no se ha establecido una contraseña.',
				'it' => 'Nessuna password impostata.',
				'ru' => 'Пароль ещё не задан.',
				'ar' => 'لم يتم تعيين كلمة مرور بعد.',
				'nl' => 'Er is nog geen wachtwoord ingesteld.',
				'pl' => 'Hasło nie zostało jeszcze ustawione.',
				'pt' => 'Nenhuma senha definida ainda.',
			),
			'close' => array(
				'tr' => 'Kapat',
				'en' => 'Close',
				'de' => 'Schließen',
				'fr' => 'Fermer',
				'es' => 'Cerrar',
				'it' => 'Chiudi',
				'ru' => 'Закрыть',
				'ar' => 'إغلاق',
				'nl' => 'Sluiten',
				'pl' => 'Zamknij',
				'pt' => 'Fechar',
			),
			'loading' => array(
				'tr' => 'Yükleniyor',
				'en' => 'Loading',
				'de' => 'Wird geladen',
				'fr' => 'Chargement',
				'es' => 'Cargando',
				'it' => 'Caricamento',
				'ru' => 'Загрузка',
				'ar' => 'جارٍ التحميل',
				'nl' => 'Laden',
				'pl' => 'Ładowanie',
				'pt' => 'Carregando',
			),
			'lang_group' => array(
				'tr' => 'Dil',
				'en' => 'Language',
				'de' => 'Sprache',
				'fr' => 'Langue',
				'es' => 'Idioma',
				'it' => 'Lingua',
				'ru' => 'Язык',
				'ar' => 'اللغة',
				'nl' => 'Taal',
				'pl' => 'Język',
				'pt' => 'Idioma',
			),
			'lang_select' => array(
				'tr' => 'Dil seç (%s)',
				'en' => 'Select language (%s)',
				'de' => 'Sprache wählen (%s)',
				'fr' => 'Choisir la langue (%s)',
				'es' => 'Seleccionar idioma (%s)',
				'it' => 'Seleziona lingua (%s)',
				'ru' => 'Выбрать язык (%s)',
				'ar' => 'اختر اللغة (%s)',
				'nl' => 'Taal kiezen (%s)',
				'pl' => 'Wybierz język (%s)',
				'pt' => 'Selecionar idioma (%s)',
			),
			'pay_nakit' => array(
				'tr' => 'Nakit',
				'en' => 'Cash',
				'de' => 'Bar',
				'fr' => 'Espèces',
				'es' => 'Efectivo',
				'it' => 'Contanti',
				'ru' => 'Наличные',
				'ar' => 'نقداً',
				'nl' => 'Contant',
				'pl' => 'Gotówka',
				'pt' => 'Dinheiro',
			),
			'pay_kart' => array(
				'tr' => 'Kart',
				'en' => 'Card',
				'de' => 'Karte',
				'fr' => 'Carte',
				'es' => 'Tarjeta',
				'it' => 'Carta',
				'ru' => 'Карта',
				'ar' => 'بطاقة',
				'nl' => 'Kaart',
				'pl' => 'Karta',
				'pt' => 'Cartão',
			),
		);
	}

	/**
	 * Çeviri aktif mi? (Bayrak seçici veya TR/EN düğmesi.)
	 *
	 * @param array $opts Ayarlar.
	 * @return bool
	 */
	private function i18n_active( $opts ) {
		return $this->lang_toggle_active( $opts ) || $this->ceviri_selector_active( $opts );
	}

	/**
	 * HTML'e basılacak dil kodları.
	 *
	 * @param array $opts Ayarlar.
	 * @return string[]
	 */
	private function i18n_langs( $opts ) {
		$langs = array( 'tr' );

		if ( $this->ceviri_selector_active( $opts ) ) {
			foreach ( $this->ceviri_selector_langs( $opts ) as $kod ) {
				if ( ! in_array( $kod, $langs, true ) ) {
					$langs[] = $kod;
				}
			}
		} elseif ( $this->lang_toggle_active( $opts ) ) {
			if ( ! in_array( 'en', $langs, true ) ) {
				$langs[] = 'en';
			}
		}

		return $langs;
	}

	/**
	 * Bir anahtarın belirli dildeki karşılığı.
	 *
	 * @param array  $opts Ayarlar.
	 * @param string $key  Metin anahtarı.
	 * @param string $tr   Türkçe kaynak (yönetici metni).
	 * @param string $lang Dil kodu.
	 * @return string
	 */
	private function text_for_lang( $opts, $key, $tr, $lang ) {
		if ( 'tr' === $lang ) {
			return $tr;
		}

		// 1) QR Çeviri tablosu (item_type=splash). Yoksa veya boşsa kataloğa düş.
		$tablo = $this->splash_tablo_cevirisi( $tr, $lang );
		if ( '' !== $tablo ) {
			return $tablo;
		}

		// 2) Eski texts_en (yalnız TR/EN düğmesi + EN).
		if ( 'en' === $lang && $this->lang_toggle_active( $opts ) ) {
			$legacy = isset( $opts['texts_en'][ $key ] ) ? trim( (string) $opts['texts_en'][ $key ] ) : '';
			if ( '' !== $legacy ) {
				return $legacy;
			}
		}

		// 3) i18n_catalog(), o da yoksa Türkçe.
		return $this->i18n_translate( $key, $lang, $tr );
	}

	/**
	 * QR Çeviri tablosundan splash metni.
	 *
	 * Tablo yoksa, dil TR ise veya satır boşsa '' döner — çağıran kataloga düşer.
	 * rma_ceviri_modul() kaçırılan satırı Türkçe kaynakla doldurduğu için
	 * burada sözlük indeksine bakılır (miss ≠ kaynak metin).
	 *
	 * @param string $tr   Türkçe kaynak.
	 * @param string $lang Dil kodu.
	 * @return string
	 */
	private function splash_tablo_cevirisi( $tr, $lang ) {
		if ( 'tr' === $lang || '' === $tr ) {
			return '';
		}
		if ( ! function_exists( 'rma_ceviri_sozluk' ) || ! function_exists( 'rma_ceviri_anahtar' ) || ! function_exists( 'rma_ceviri_ui_anahtari' ) ) {
			return '';
		}

		$sozluk  = rma_ceviri_sozluk( $lang );
		$anahtar = rma_ceviri_anahtar( 'splash', 0, rma_ceviri_ui_anahtari( $tr ) );

		if ( isset( $sozluk['anahtar'][ $anahtar ] ) && '' !== (string) $sozluk['anahtar'][ $anahtar ] ) {
			return (string) $sozluk['anahtar'][ $anahtar ];
		}

		return '';
	}

	/**
	 * Katalogdan çeviri; yoksa EN, o da yoksa TR.
	 *
	 * @param string $key  Metin anahtarı.
	 * @param string $lang Dil kodu.
	 * @param string $tr   Türkçe geri düşüş.
	 * @return string
	 */
	private function i18n_translate( $key, $lang, $tr ) {
		$catalog = $this->i18n_catalog();

		if ( isset( $catalog[ $key ][ $lang ] ) ) {
			return $catalog[ $key ][ $lang ];
		}

		if ( isset( $catalog[ $key ]['en'] ) ) {
			return $catalog[ $key ]['en'];
		}

		return $tr;
	}
}
