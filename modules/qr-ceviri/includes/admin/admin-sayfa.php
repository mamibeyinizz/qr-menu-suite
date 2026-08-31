<?php
/**
 * "QR Çeviri" yönetim sayfası.
 *
 * v1.7'deki renk ayarları ve aktif dil kutuları AYNEN korunuyor (aynı sayfa
 * slug'ı, aynı nonce, aynı seçenek adları). Altına CSV dışa/içe aktarma,
 * kaynak ayarları ve Elementor sayfa seçimi eklendi.
 *
 * @package QRMenu_Ceviri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ayar formunu kaydet.
 */
if ( ! function_exists( 'rma_ceviri_ayarlari_kaydet' ) ) {
	function rma_ceviri_ayarlari_kaydet() {
		check_admin_referer( 'qrmenu_save_action', 'qrmenu_nonce' );

		$langs         = isset( $_POST['qrmenu_langs'] ) ? array_map( 'sanitize_text_field', (array) $_POST['qrmenu_langs'] ) : array();
		$bg_color_text = isset( $_POST['qrmenu_bg_color_text'] ) ? sanitize_hex_color( $_POST['qrmenu_bg_color_text'] ) : '#111111';
		$bg_color_only = isset( $_POST['qrmenu_bg_color_only'] ) ? sanitize_hex_color( $_POST['qrmenu_bg_color_only'] ) : '#111111';

		update_option( 'qrmenu_active_langs', $langs );
		update_option( 'qrmenu_bg_color_text', $bg_color_text );
		update_option( 'qrmenu_bg_color_only', $bg_color_only );

		/* Kaynak ayarları — yalnızca uygun slug'lar, kategori/alerjen ayrık */
		$gonderilen = function ( $ad ) {
			return isset( $_POST[ $ad ] ) ? array_map( 'sanitize_key', (array) $_POST[ $ad ] ) : array();
		};

		update_option(
			'rma_ceviri_urun_tipleri',
			array_values( array_intersect( $gonderilen( 'rma_urun_tipleri' ), array_keys( rma_ceviri_uygun_urun_tipleri() ) ) ),
			false
		);

		$uygun_taks = array_keys( rma_ceviri_uygun_taksonomiler() );

		list( $kategori_taks, $alerjen_taks ) = rma_ceviri_taks_ayir(
			array_intersect( $gonderilen( 'rma_kategori_taks' ), $uygun_taks ),
			array_intersect( $gonderilen( 'rma_alerjen_taks' ), $uygun_taks )
		);

		update_option( 'rma_ceviri_kategori_taks', $kategori_taks, false );
		update_option( 'rma_ceviri_alerjen_taks', $alerjen_taks, false );

		update_option(
			'rma_ceviri_ek_metinler',
			isset( $_POST['rma_ek_metinler'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rma_ek_metinler'] ) ) : '',
			false
		);

		update_option( 'rma_ceviri_url_yonlendir', empty( $_POST['rma_url_yonlendir'] ) ? 0 : 1, false );
		update_option( 'rma_ceviri_tampon_acik', empty( $_POST['rma_tampon_acik'] ) ? 0 : 1, false );
		update_option( 'rma_ceviri_toplama_acik', empty( $_POST['rma_toplama_acik'] ) ? 0 : 1, false );

		/* Bulunan metinler: seçilenler ek metinlere taşınır (ek metinler
		   yukarıda kaydedildi, aktarım onun üstüne yazar). */
		$aktarilan = 0;
		if ( ! empty( $_POST['rma_bulunan'] ) ) {
			$secilenler = array_map(
				function ( $m ) {
					return sanitize_text_field( wp_unslash( $m ) );
				},
				(array) $_POST['rma_bulunan']
			);
			$aktarilan = rma_ceviri_bulunanlari_aktar( $secilenler );
		}

		if ( ! empty( $_POST['rma_bulunanlari_temizle'] ) ) {
			rma_ceviri_bulunanlari_yaz( array() );
		}

		// Sabit metinler ve diller değişmiş olabilir.
		rma_ceviri_onbellek_temizle();

		$mesaj = 'Ayarlar kaydedildi.';
		if ( $aktarilan > 0 ) {
			$mesaj .= sprintf( ' %d metin listeye eklendi — bir sonraki CSV dışa aktarımında çıkacak.', $aktarilan );
		}

		echo '<div class="updated"><p>' . esc_html( $mesaj ) . '</p></div>';
	}
}

/**
 * İçe aktarma bildirimleri ve son rapor.
 */
if ( ! function_exists( 'rma_ceviri_import_bildirimleri' ) ) {
	function rma_ceviri_import_bildirimleri() {
		$durum = isset( $_GET['ice'] ) ? sanitize_key( wp_unslash( $_GET['ice'] ) ) : '';

		if ( 'hata' === $durum ) {
			$kod     = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
			$hatalar = array(
				'nofile'     => 'Dosya seçilmedi veya yüklenirken hata oluştu.',
				'type'       => 'Sadece .csv dosyaları yükleyebilirsiniz.',
				'size'       => 'Dosya boyutu 20 MB sınırını aşıyor.',
				'invalidcsv' => 'CSV okunamadı (başlık satırı bulunamadı).',
				'sutun'      => 'Başlık satırında item_type/field sütunları ya da hiçbir dil sütunu bulunamadı.',
			);
			$metin   = isset( $hatalar[ $kod ] ) ? $hatalar[ $kod ] : 'Bilinmeyen bir hata oluştu.';

			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $metin ) . '</p></div>';
			return;
		}

		if ( 'ok' !== $durum ) {
			return;
		}

		$rapor = get_transient( 'rma_ceviri_rapor_' . get_current_user_id() );
		delete_transient( 'rma_ceviri_rapor_' . get_current_user_id() );

		if ( ! is_array( $rapor ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>İçe aktarma tamamlandı.</p></div>';
			return;
		}

		echo '<div class="notice notice-success"><p><strong>İçe aktarma tamamlandı.</strong> ';
		printf(
			esc_html( '%1$d satır okundu · %2$d çeviri yazıldı · %3$d satır atlandı' ),
			(int) $rapor['toplam'],
			(int) $rapor['guncellendi'],
			(int) $rapor['atlandi']
		);
		if ( ! empty( $rapor['temizle'] ) ) {
			printf( esc_html( ' · %d çeviri silindi' ), (int) $rapor['silindi'] );
		}
		echo '</p></div>';

		if ( ! empty( $rapor['atlanan'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>Atlanan satırlar</strong> (ilk ' . count( $rapor['atlanan'] ) . '):</p><ul style="margin-left:18px;list-style:disc;">';
			foreach ( $rapor['atlanan'] as $satir ) {
				printf(
					'<li>Satır %1$d — %2$s</li>',
					(int) $satir['satir'],
					esc_html( $satir['neden'] )
				);
			}
			echo '</ul></div>';
		}

		if ( ! empty( $rapor['bayat'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>Orijinal metni değişmiş satırlar</strong> — çeviriler bayat olabilir, bu satırları yeniden çevirmeyi düşünün:</p><ul style="margin-left:18px;list-style:disc;">';
			foreach ( $rapor['bayat'] as $satir ) {
				printf(
					'<li>Satır %1$d — %2$s #%3$d / %4$s → güncel metin: “%5$s”</li>',
					(int) $satir['satir'],
					esc_html( $satir['tip'] ),
					(int) $satir['id'],
					esc_html( $satir['field'] ),
					esc_html( $satir['orijinal'] )
				);
			}
			echo '</ul></div>';
		}
	}
}

/**
 * Sistem durumu — "içe aktardım ama çevrilmiyor" için ilk bakılacak yer.
 *
 * Menü metinleri QR MENÜ eklentisinin İÇİNDEKİ sarmalayıcılarla çevriliyor;
 * o eklenti güncel değilse çeviri tablosu dolu olsa bile kartlar Türkçe
 * kalır. Bu panel iki soruyu tek bakışta yanıtlar: satırlar yazıldı mı,
 * menü entegrasyonu devrede mi?
 */
if ( ! function_exists( 'rma_ceviri_durum_paneli' ) ) {
	function rma_ceviri_durum_paneli() {
		$entegre  = rma_ceviri_rma_entegre_mi();
		$rma_var  = class_exists( 'Restaurant_Menu_Automation' );
		$sayilar  = RMA_Ceviri_Tablo::tip_dil_sayilari();
		$hedefler = rma_ceviri_hedef_diller();
		$tipler   = rma_ceviri_gecerli_tipler();
		$katalog  = qrmenu_get_langs();
		?>
		<h2 class="title">🔍 Sistem Durumu</h2>

		<?php if ( ! $rma_var ) : ?>
			<div class="notice notice-warning inline"><p>
				<strong>QR MENÜ eklentisi bulunamadı.</strong> Menü çevirisi için o eklentinin
				etkin olması gerekiyor. Elementor/tema metinleri çıktı tamponuyla çevrilmeye
				devam eder.
			</p></div>
		<?php elseif ( ! $entegre ) : ?>
			<div class="notice notice-error inline"><p>
				<strong>QR MENÜ eklentisi çeviri entegrasyonu içermiyor.</strong>
				Sitedeki sürüm güncellenmemiş görünüyor (<code>t_field()</code> sarmalayıcısı yok).
				Menü kartları çıktı tamponuyla çevrilmeye devam eder, ama en doğru sonuç için
				<code>qr-menu/</code> eklentisini de bu depodaki sürümle güncelleyin.
			</p></div>
		<?php else : ?>
			<div class="notice notice-success inline"><p>
				<strong>QR MENÜ entegrasyonu etkin.</strong> Kart, kategori ve modal metinleri
				doğrudan çeviri tablosundan basılıyor.
			</p></div>
		<?php endif; ?>

		<?php
		/*
		 * Dar ekranda bu tablo KART görünümüne döner (bkz. assets/css/admin.css):
		 * her içerik satırı kendi kartı, her dil hücresi "Dil: adet" satırı olur.
		 * Sütun sayısı aktif hedef dile bağlı olduğu ve 30'a kadar çıkabildiği
		 * için yatay kaydırma değil kart tercih edildi. Hücrelerdeki data-label
		 * kart görünümünde başlık yerine geçer — thead orada gizlenir.
		 */
		?>
		<table class="widefat striped qrc-stats">
			<thead>
				<tr>
					<th scope="col">İçerik</th>
					<?php foreach ( $hedefler as $dil ) : ?>
						<th scope="col" class="qrc-stats-num"><?php echo esc_html( $dil ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tipler as $tip ) : ?>
					<tr>
						<th scope="row" class="qrc-stats-row-head"><?php echo esc_html( rma_ceviri_tip_etiketi( $tip ) ); ?></th>
						<?php foreach ( $hedefler as $dil ) : ?>
							<?php
							$adet = isset( $sayilar[ $tip ][ $dil ] ) ? (int) $sayilar[ $tip ][ $dil ] : 0;
							// Sütun başlığı dar kalsın diye kod; kart görünümündeki
							// etiket ise bayrak + dil adı (orada yer var, "en" yerine
							// "İngilizce" okunur).
							$etiket = isset( $katalog[ $dil ] )
								? $katalog[ $dil ]['flag'] . ' ' . $katalog[ $dil ]['name']
								: $dil;
							?>
							<td class="qrc-stats-num<?php echo 0 === $adet ? ' is-empty' : ''; ?>" data-label="<?php echo esc_attr( $etiket ); ?>">
								<?php echo 0 === $adet ? '—' : (int) $adet; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<details class="qrc-limit qrc-details">
			<summary style="cursor:pointer;color:#2271b1;">ⓘ Bu tablo ne anlatıyor?</summary>
			<p class="description" style="margin-top:8px;">
				Her hücre, o içeriğin o dilde kaç çevirisinin kayıtlı olduğunu gösterir.
				<strong>—</strong> görüyorsanız o içerik o dile hiç çevrilmemiş: CSV'yi dışa
				aktarıp ilgili dil sütununu doldurun ve geri yükleyin. Satırlar hâlâ boşsa
				CSV'deki <code>item_id</code>, <code>item_type</code> ve <code>field</code>
				sütunlarına dokunulmadığından emin olun — eşleştirme onlara dayanıyor.
			</p>
			<p class="description">
				Kaynaklar — ürün: <code><?php echo esc_html( implode( ', ', rma_ceviri_urun_tipleri() ) ?: '—' ); ?></code> ·
				kategori: <code><?php echo esc_html( implode( ', ', rma_ceviri_taksonomiler( 'category' ) ) ?: '—' ); ?></code> ·
				alerjen: <code><?php echo esc_html( implode( ', ', rma_ceviri_taksonomiler( 'allergen' ) ) ?: '—' ); ?></code>
			</p>
		</details>
		<?php
	}
}

/**
 * Öğe tipinin kullanıcıya gösterilecek adı.
 *
 * @param string $tip Öğe tipi.
 * @return string
 */
if ( ! function_exists( 'rma_ceviri_tip_etiketi' ) ) {
	function rma_ceviri_tip_etiketi( $tip ) {
		$etiketler = array(
			'product'   => 'Menü ürünleri',
			'category'  => 'Kategoriler',
			'allergen'  => 'Alerjenler',
			'nav_menu'  => 'Menü linkleri (header/footer)',
			'ui_string' => 'Sabit metinler (buton, etiket)',
			'elementor' => 'Sayfa içerikleri (Elementor)',
		);

		if ( function_exists( 'rma_ceviri_modul_tipleri' ) ) {
			$etiketler = array_merge( $etiketler, rma_ceviri_modul_tipleri() );
		}

		return isset( $etiketler[ $tip ] ) ? $etiketler[ $tip ] : $tip;
	}
}

/**
 * Post type / taxonomy seçim kutuları.
 *
 * @param string   $ad      input adı.
 * @param array    $nesneler Seçenek nesneleri (slug => etiket).
 * @param string[] $secili   Seçili slug'lar.
 */
if ( ! function_exists( 'rma_ceviri_secim_kutulari' ) ) {
	function rma_ceviri_secim_kutulari( $ad, $nesneler, $secili ) {
		if ( empty( $nesneler ) ) {
			echo '<em>Uygun kayıt bulunamadı.</em>';
			return;
		}

		// Izgara sabit üç sütun DEĞİLDİR: geniş ekranda sığdığı kadar sütun,
		// dokunmatik/dar ekranda tek sütun ve 48px dokunma yüksekliği.
		echo '<div class="qrc-check-grid">';
		foreach ( $nesneler as $slug => $etiket ) {
			printf(
				'<label class="qrc-check"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> <span>%4$s <code>%2$s</code></span></label>',
				esc_attr( $ad ),
				esc_attr( $slug ),
				checked( in_array( $slug, $secili, true ), true, false ),
				esc_html( $etiket )
			);
		}
		echo '</div>';
	}
}

/**
 * Ayar sayfası.
 */
if ( ! function_exists( 'qrmenu_trans_page' ) ) {
	function qrmenu_trans_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		if ( isset( $_POST['qrmenu_trans_save'] ) ) {
			rma_ceviri_ayarlari_kaydet();
		}

		rma_ceviri_import_bildirimleri();

		$all_langs     = qrmenu_get_langs();
		$active_langs  = get_option( 'qrmenu_active_langs', rma_ceviri_varsayilan_diller() );
		$bg_color_text = get_option( 'qrmenu_bg_color_text', '#111111' );
		$bg_color_only = get_option( 'qrmenu_bg_color_only', '#111111' );

		if ( ! is_array( $active_langs ) ) {
			$active_langs = rma_ceviri_varsayilan_diller();
		}

		// Uygun tipler tek kaynaktan (kaynaklar.php): burada listelenmeyen bir
		// slug seçili kalsa bile dışa aktarıma giremez.
		$post_tipleri = rma_ceviri_uygun_urun_tipleri();
		$taksonomiler = rma_ceviri_uygun_taksonomiler();

		$elementor_sayfalari = rma_ceviri_elementor_sayfalari();
		$elementor_secili    = rma_ceviri_secili_elementor_sayfalari();
		$dil_sayilari        = RMA_Ceviri_Tablo::dil_sayilari();
		?>
		<div class="wrap qrc-wrap">
			<h1>QR Çeviri</h1>
			<p class="description qrc-limit">
				Çeviriler Excel/CSV ile toplu yönetilir: dosyayı indirin, çevirileri yazın,
				geri yükleyin. Ziyaretçi dil seçtiğinde sayfa doğrudan o dilde açılır.
			</p>

			<?php if ( ! RMA_Ceviri_Tablo::tablo_var_mi() ) : ?>
				<div class="notice notice-error"><p>
					Çeviri tablosu bulunamadı. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.
				</p></div>
			<?php endif; ?>

			<?php rma_ceviri_durum_paneli(); ?>

			<form method="POST">
				<?php wp_nonce_field( 'qrmenu_save_action', 'qrmenu_nonce' ); ?>

				<h2 class="title">Diller</h2>
				<table class="form-table">
					<tr>
						<th>Menüde gösterilecek diller</th>
						<td>
							<div class="qrc-check-grid qrc-lang-grid">
								<?php foreach ( $all_langs as $code => $data ) : ?>
									<label class="qrc-check">
										<input type="checkbox" name="qrmenu_langs[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $active_langs, true ) ); ?>>
										<span class="qrc-flag"><?php echo $data['flag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="qrc-lang-name"><?php echo esc_html( $data['name'] ); ?></span>
										<?php if ( isset( $dil_sayilari[ $code ] ) ) : ?>
											<small class="qrc-muted">(<?php echo (int) $dil_sayilari[ $code ]; ?> çeviri)</small>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description">Türkçe orijinal dildir; her zaman listede kalır.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Çevrilmeyen metinler</h2>
				<p class="description qrc-limit">
					Sitede çevrilmeyen bir metin mi var? Türkçe hâlini aşağıdaki kutuya
					ekleyip Kaydet'e basın — sonraki CSV dışa aktarımında otomatik çıkar.
					Tek tek aramak istemiyorsanız <strong>Metin Toplama</strong>'yı açıp
					siteyi bir kez gezin, eklenti çevrilmemiş metinleri kendisi bulsun.
				</p>

				<table class="form-table">
					<tr>
						<th>Metin Toplama</th>
						<td>
							<label>
								<input type="checkbox" name="rma_toplama_acik" value="1" <?php checked( rma_ceviri_toplama_acik_mi() ); ?>>
								Siteyi gezerken çevrilmemiş metinleri topla
							</label>
							<p class="description">
								Açıkken siteyi Türkçe gezin (footer, formlar, iletişim sayfası…);
								gördüğü metinler aşağıda listelenir. İşiniz bitince kapatın.
							</p>
							<details style="margin-top:6px;">
								<summary style="cursor:pointer;color:#2271b1;">ⓘ Nasıl çalışır?</summary>
								<p class="description" style="margin-top:6px;">
									Yalnızca siz (giriş yapmış yönetici) siteyi Türkçe görüntülerken
									çalışır; ziyaretçilere hiçbir ek yük binmez ve sayfa çıktısı
									değiştirilmez. Metinler yalnızca okunur, en fazla
									<?php echo (int) RMA_CEVIRI_TOPLAMA_LIMIT; ?> aday saklanır.
								</p>
							</details>
						</td>
					</tr>

					<?php $bulunanlar = rma_ceviri_bulunan_metinler(); ?>
					<?php if ( ! empty( $bulunanlar ) ) : ?>
					<tr>
						<th>Bulunan metinler (<?php echo count( $bulunanlar ); ?>)</th>
						<td>
							<div class="qrc-scrollbox">
								<?php foreach ( array_keys( $bulunanlar ) as $metin ) : ?>
									<label class="qrc-check qrc-check-block">
										<input type="checkbox" name="rma_bulunan[]" value="<?php echo esc_attr( $metin ); ?>">
										<span><?php echo esc_html( $metin ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description">
								Çevrilmesini istediklerinizi işaretleyip Kaydet'e basın; aşağıdaki
								listeye eklenir ve buradan kalkar.
							</p>
							<label>
								<input type="checkbox" name="rma_bulunanlari_temizle" value="1">
								Listeyi tamamen temizle
							</label>
						</td>
					</tr>
					<?php endif; ?>

					<tr>
						<th>Çevrilecek sabit metinler</th>
						<td>
							<textarea name="rma_ek_metinler" rows="8" class="large-text code" placeholder="Her satıra bir metin&#10;Bize Ulaşın&#10;Yemek Lezzeti"><?php echo esc_textarea( get_option( 'rma_ceviri_ek_metinler', '' ) ); ?></textarea>
							<p class="description">
								Her satır bir metin. Menünün kendi metinleri (Sepete Ekle, Filtrele…)
								zaten dahildir, onları eklemenize gerek yok.
							</p>
						</td>
					</tr>
				</table>

				<details style="margin:24px 0;">
					<summary style="cursor:pointer;font-size:1.1em;font-weight:600;padding:8px 0;">
						⚙️ Gelişmiş Ayarlar
					</summary>

					<p class="description qrc-limit">
						Bunlar genelde bir kez ayarlanır; emin değilseniz olduğu gibi bırakın.
					</p>

					<h3>Görünüm</h3>
					<table class="form-table">
						<tr>
							<th>"Sadece Bayrak" butonu rengi</th>
							<td><input type="color" name="qrmenu_bg_color_only" value="<?php echo esc_attr( $bg_color_only ); ?>"></td>
						</tr>
						<tr>
							<th>"Bayrak + Metin" butonu rengi</th>
							<td><input type="color" name="qrmenu_bg_color_text" value="<?php echo esc_attr( $bg_color_text ); ?>"></td>
						</tr>
					</table>

					<h3>Davranış</h3>
					<table class="form-table">
						<tr>
							<th>Dili adrese ekle</th>
							<td>
								<label>
									<input type="checkbox" name="rma_url_yonlendir" value="1" <?php checked( (bool) get_option( 'rma_ceviri_url_yonlendir', 1 ) ); ?>>
									Ziyaretçi dil seçtiyse adreste göster
								</label>
								<p class="description">Açık tutun — arama motorlarının çevrilmiş sayfaları görmesi buna bağlı.</p>
								<details style="margin-top:6px;">
									<summary style="cursor:pointer;color:#2271b1;">ⓘ Detay</summary>
									<p class="description" style="margin-top:6px;">
										Dil URL'de görünmezse sayfa önbelleği ve arama motorları çevrilmiş
										sayfayı ayrı bir adres olarak göremez. Türkçe ziyaretçi hiçbir zaman
										yönlendirilmez.
									</p>
								</details>
							</td>
						</tr>
						<tr>
							<th>Tema ve eklenti metinleri</th>
							<td>
								<label>
									<input type="checkbox" name="rma_tampon_acik" value="1" <?php checked( (bool) get_option( 'rma_ceviri_tampon_acik', 1 ) ); ?>>
									Temanın ve diğer eklentilerin bastığı sabit metinleri de çevir
								</label>
								<p class="description">Menü dışındaki metinler (footer, formlar, sayfa içerikleri) için gerekli.</p>
								<details style="margin-top:6px;">
									<summary style="cursor:pointer;color:#2271b1;">ⓘ Detay</summary>
									<p class="description" style="margin-top:6px;">
										Sayfa çıktısı tamamlandıktan sonra metinler çeviri tablosundan
										geçirilir. QR Menü'nün kendi metinleri buna ihtiyaç duymaz —
										onlar kaynağında çevriliyor. Yalnızca menüyü çeviriyorsanız
										kapatabilirsiniz.
									</p>
								</details>
							</td>
						</tr>
					</table>

					<h3>Çeviri Kaynakları</h3>
					<p class="description">
						Hangi içeriklerin CSV'ye çıkacağını belirler. Varsayılanlar sitenizden
						otomatik bulunur.
					</p>
					<table class="form-table">
						<tr>
							<th>Menü ürünleri</th>
							<td>
								<?php rma_ceviri_secim_kutulari( 'rma_urun_tipleri', $post_tipleri, rma_ceviri_urun_tipleri() ); ?>
								<details style="margin-top:6px;">
									<summary style="cursor:pointer;color:#2271b1;">ⓘ Sayfalar neden burada yok?</summary>
									<p class="description" style="margin-top:6px;">
										Yazı, sayfa ve Elementor şablonları menü ürünü değildir; onların
										metinleri aşağıdaki "Ayrıca şu sayfaları dahil et" bölümünden,
										sayfa başlıkları dahil çevrilir.
									</p>
								</details>
							</td>
						</tr>
						<tr>
							<th>Kategoriler</th>
							<td><?php rma_ceviri_secim_kutulari( 'rma_kategori_taks', $taksonomiler, rma_ceviri_taksonomiler( 'category' ) ); ?></td>
						</tr>
						<tr>
							<th>Alerjenler</th>
							<td>
								<?php rma_ceviri_secim_kutulari( 'rma_alerjen_taks', $taksonomiler, rma_ceviri_taksonomiler( 'allergen' ) ); ?>
								<details style="margin-top:6px;">
									<summary style="cursor:pointer;color:#2271b1;">ⓘ Detay</summary>
									<p class="description" style="margin-top:6px;">
										Aynı liste iki yerde işaretlenirse kaydederken tek yerde bırakılır;
										aksi hâlde aynı isimler CSV'ye iki kez çıkardı.
									</p>
								</details>
							</td>
						</tr>
					</table>
				</details>

				<p class="submit" style="border-top:1px solid #dcdcde;padding-top:16px;">
					<input type="submit" name="qrmenu_trans_save" class="button button-primary button-large" value="Ayarları Kaydet">
					<span class="description" style="margin-left:10px;">
						Bu buton yukarıdaki tüm ayarları (Gelişmiş Ayarlar dahil) kaydeder.
					</span>
				</p>
			</form>

			<hr>

			<h2 class="title">📤 Çeviri CSV'sini Dışa Aktar</h2>
			<p class="description qrc-limit">
				Menü ürünleri, kategoriler, menü linkleri ve sabit metinler her zaman
				dahildir. Mevcut çeviriler dolu gelir — sıfırdan başlamanız gerekmez.
				<strong>Sadece dil sütunlarını doldurun</strong>, ilk sütunlara dokunmayın.
			</p>
			<details class="qrc-limit qrc-details">
				<summary style="cursor:pointer;color:#2271b1;">ⓘ Neden ilk sütunlara dokunmamalıyım?</summary>
				<p class="description" style="margin-top:6px;">
					<code>item_id</code>, <code>item_type</code> ve <code>field</code> sütunları
					her satırın sitedeki hangi metne ait olduğunu tutar; değişirlerse satır
					eşleşmez ve içe aktarımda atlanır. <code>original_hash</code> ise orijinal
					metin sonradan değiştiyse sizi uyarmak için kullanılır.
				</p>
			</details>
			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rma_ceviri_export">
				<?php wp_nonce_field( 'rma_ceviri_export_action', 'rma_ceviri_export_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th>Sütun ayracı</th>
						<td>
							<label><input type="radio" name="ayrac" value=";" checked> Noktalı virgül <code>;</code> (Excel Türkçe)</label><br>
							<label><input type="radio" name="ayrac" value=","> Virgül <code>,</code> (Google Sheets)</label>
						</td>
					</tr>
					<tr>
						<th>Ayrıca şu sayfaları dahil et</th>
						<td>
							<?php if ( empty( $elementor_sayfalari ) ) : ?>
								<em>Elementor ile düzenlenmiş içerik bulunamadı.</em>
							<?php else : ?>
								<div class="qrc-scrollbox">
									<?php foreach ( $elementor_sayfalari as $id => $baslik ) : ?>
										<label class="qrc-check qrc-check-block">
											<input type="checkbox" name="elementor_sayfalar[]" value="<?php echo (int) $id; ?>" <?php checked( in_array( (int) $id, $elementor_secili, true ) ); ?>>
											<span><?php echo esc_html( $baslik ); ?> <small class="qrc-muted">#<?php echo (int) $id; ?></small></span>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description">
									Sayfa başlıkları ve içindeki metinler CSV'ye eklenir. Çevirmek
									istediklerinizi seçin.
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<button type="submit" class="button button-primary">Çeviri CSV'sini Dışa Aktar</button>
			</form>

			<hr>

			<h2 class="title">📥 Çeviri CSV'sini İçe Aktar</h2>
			<p class="description">
				Dışa aktardığınız dosyanın aynı yapıda olması yeterlidir; ayraç
				(<code>,</code> / <code>;</code>) otomatik algılanır.
			</p>
			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="rma_ceviri_import">
				<?php wp_nonce_field( 'rma_ceviri_import_action', 'rma_ceviri_import_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th>CSV dosyası</th>
						<td><input type="file" name="rma_ceviri_dosya" accept=".csv,text/csv" required></td>
					</tr>
					<tr>
						<th>Boş hücreler</th>
						<td>
							<label>
								<input type="checkbox" name="bos_hucreleri_temizle" value="1">
								Boş hücreleri temizle (o dildeki mevcut çeviriyi sil)
							</label>
							<p class="description">
								İşaretli değilse boş hücreler atlanır ve mevcut çeviri korunur —
								kısmi güncelleme için güvenli olan budur.
							</p>
						</td>
					</tr>
				</table>

				<button type="submit" class="button button-primary">Çeviri CSV'sini İçe Aktar</button>
			</form>

			<hr>

			<h3>Kullanım Kısa Kodları</h3>
			<p><b>Sadece Bayrak (Kare Dropdown):</b> <code>[qrmenu_flags_only]</code></p>
			<p><b>Bayrak ve Dil İsmi (Dropdown):</b> <code>[qrmenu_flags_text]</code></p>
		</div>
		<?php
	}
}
