<?php
/**
 * Yönetim arayüzü: porsiyon, ekstra, servis saati ve özel rozetler.
 *
 * İKİ EKRAN VAR:
 *  - Ürün düzenleme sayfasındaki "Seçenekler" meta kutusu (ürüne özel).
 *  - "Seçenek & Rozet" ayar sayfası: yeniden kullanılan ekstra listeleri ve
 *    özel rozet tanımları (tüm ürünlerin ortak kaynağı).
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait RMA_Secenek_Admin_Trait {

	/**
	 * Seçenek arayüzünün varlıkları.
	 *
	 * İki ayrı kuyruk noktasından çağrılır (modülün kendi admin_scripts'i ve
	 * suite'in module.php kuyruğu), bu yüzden temel adres ve sürüm çözücü
	 * dışarıdan verilir.
	 *
	 * @param string   $url    Modül kök adresi (sonunda / ile).
	 * @param callable $surum  Göreli yol alıp sürüm döndüren fonksiyon.
	 * @return void
	 */
	public function enqueue_secenek_assets( $url, $surum ) {
		wp_enqueue_style(
			'rma-urun-secenekler',
			$url . 'assets/css/urun-secenekler.css',
			array(),
			call_user_func( $surum, 'assets/css/urun-secenekler.css' )
		);

		wp_enqueue_script(
			'rma-urun-secenekler',
			$url . 'assets/js/urun-secenekler.js',
			array(),
			call_user_func( $surum, 'assets/js/urun-secenekler.js' ),
			true
		);
	}

	/* =============================================================
	   ÜRÜN META KUTUSU
	============================================================= */

	/**
	 * "Seçenekler" meta kutusunu kaydeder.
	 *
	 * @return void
	 */
	public function add_secenek_meta_box() {
		add_meta_box(
			'rma_secenekler',
			'Porsiyon, Ekstra ve Servis Saati',
			array( $this, 'render_secenek_meta_box' ),
			'rma_menu_item',
			'normal',
			'default'
		);
	}

	/**
	 * Meta kutusu içeriği.
	 *
	 * @param WP_Post $post Ürün.
	 * @return void
	 */
	public function render_secenek_meta_box( $post ) {
		wp_nonce_field( 'rma_save_secenek', 'rma_secenek_nonce' );

		$porsiyonlar = RMA_Porsiyon::oku( $post->ID );
		$manuel      = RMA_Ekstra::manuel( $post->ID );
		$listeler    = RMA_Ekstra::listeler();
		$secili      = RMA_Ekstra::liste_idleri( $post->ID );
		$rozetler    = RMA_Ozel_Rozet::tanimlar();
		$secili_roz  = RMA_Ozel_Rozet::urun_sluglari( $post->ID );
		$mod         = (string) get_post_meta( $post->ID, RMA_Servis_Saati::META_MOD, true );
		$mod         = in_array( $mod, array( 'kapali', 'ozel' ), true ) ? $mod : 'devral';
		$gunler      = RMA_Servis_Saati::gunleri_temizle( get_post_meta( $post->ID, RMA_Servis_Saati::META_GUNLER, true ) );
		$bas         = RMA_Servis_Saati::saati_temizle( get_post_meta( $post->ID, RMA_Servis_Saati::META_BAS, true ) );
		$bit         = RMA_Servis_Saati::saati_temizle( get_post_meta( $post->ID, RMA_Servis_Saati::META_BIT, true ) );
		$ayar_url    = $this->admin_page_url( 'qrms-rm-secenekler' );
		?>
		<div class="rma-secenek-kutu">

			<h4 class="rma-secenek-h">Porsiyon / Varyasyon</h4>
			<p class="rma-secenek-not">
				Fiyat, ürünün <strong>taban fiyatına eklenen fark</strong> olarak yazılır: "Büyük" için <code>40</code>,
				"Küçük" için <code>-20</code>. Fark yazmazsanız o porsiyon taban fiyattan satılır.
				Porsiyon eklemezseniz müşteri seçim ekranı görmez.
			</p>

			<table class="widefat rma-tekrar" data-rma-tekrar="porsiyon" data-azami="<?php echo (int) RMA_Porsiyon::AZAMI; ?>">
				<thead>
					<tr>
						<th style="width:60%;">Porsiyon adı</th>
						<th style="width:30%;">Fiyat farkı (₺)</th>
						<th style="width:10%;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $porsiyonlar as $i => $satir ) : ?>
					<tr class="rma-tekrar-satir">
						<td><input type="text" name="rma_porsiyon[<?php echo (int) $i; ?>][ad]" value="<?php echo esc_attr( $satir['ad'] ); ?>" placeholder="Büyük" class="widefat"></td>
						<td><input type="text" name="rma_porsiyon[<?php echo (int) $i; ?>][fark]" value="<?php echo esc_attr( $satir['fark'] ); ?>" placeholder="0" class="widefat"></td>
						<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<?php
				$this->render_tekrar_sablonu(
					array(
						array( 'ad', 'text', 'Büyük' ),
						array( 'fark', 'text', '0' ),
					),
					'rma_porsiyon'
				);
				?>
			</table>
			<p><button type="button" class="button rma-tekrar-ekle" data-hedef="porsiyon">+ Porsiyon ekle</button></p>

			<hr>

			<h4 class="rma-secenek-h">Yan Ürünler / Ekstralar</h4>
			<p class="rma-secenek-not">
				Müşteri ürün kartını açtığında en altta "Ekstra ekle" bölümü çıkar. Hiçbir şey seçmezseniz bu bölüm görünmez.
				Hazır listeler <a href="<?php echo esc_url( $ayar_url ); ?>">Seçenek &amp; Rozet</a> ekranından yönetilir.
			</p>

			<?php if ( ! empty( $listeler ) ) : ?>
			<div class="rma-secenek-listeler">
				<strong>Hazır listeler</strong>
				<div class="rma-secenek-kutucuklar">
					<?php foreach ( $listeler as $liste ) : ?>
					<label>
						<input type="checkbox" name="rma_ekstra_listeler[]" value="<?php echo esc_attr( $liste['id'] ); ?>"
							<?php checked( in_array( $liste['id'], $secili, true ) ); ?>>
						<?php echo esc_html( $liste['ad'] ); ?>
						<span class="rma-secenek-sayi"><?php echo (int) count( $liste['urunler'] ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php else : ?>
			<p class="rma-secenek-not">Henüz hazır liste yok — <a href="<?php echo esc_url( $ayar_url ); ?>">Seçenek &amp; Rozet</a> ekranından oluşturabilirsiniz.</p>
			<?php endif; ?>

			<p><strong>Yalnızca bu ürüne özel ekstralar</strong></p>
			<table class="widefat rma-tekrar" data-rma-tekrar="ekstra" data-azami="30">
				<thead>
					<tr>
						<th style="width:60%;">Ekstra adı</th>
						<th style="width:30%;">Fiyat (₺)</th>
						<th style="width:10%;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $manuel as $i => $satir ) : ?>
					<tr class="rma-tekrar-satir">
						<td><input type="text" name="rma_ekstra_manuel[<?php echo (int) $i; ?>][ad]" value="<?php echo esc_attr( $satir['ad'] ); ?>" placeholder="Sos" class="widefat"></td>
						<td><input type="text" name="rma_ekstra_manuel[<?php echo (int) $i; ?>][fiyat]" value="<?php echo esc_attr( $satir['fiyat'] ); ?>" placeholder="10" class="widefat"></td>
						<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<?php
				$this->render_tekrar_sablonu(
					array(
						array( 'ad', 'text', 'Sos' ),
						array( 'fiyat', 'text', '10' ),
					),
					'rma_ekstra_manuel'
				);
				?>
			</table>
			<p><button type="button" class="button rma-tekrar-ekle" data-hedef="ekstra">+ Ekstra ekle</button></p>

			<hr>

			<h4 class="rma-secenek-h">Servis Saati</h4>
			<p class="rma-secenek-not">
				Saat dışında ürün menüde kalır, üzerine "Servis dışı" etiketi basılır ve sepete eklenemez.
				Varsayılan olarak ürün, kategorisine tanımlanmış saati devralır (Kategoriler ekranından).
			</p>

			<p>
				<label><input type="radio" name="rma_servis_mod" value="devral" <?php checked( 'devral', $mod ); ?>> Kategoriden devral</label><br>
				<label><input type="radio" name="rma_servis_mod" value="kapali" <?php checked( 'kapali', $mod ); ?>> Kısıt yok (her zaman servis edilir)</label><br>
				<label><input type="radio" name="rma_servis_mod" value="ozel" <?php checked( 'ozel', $mod ); ?>> Bu ürüne özel saat</label>
			</p>

			<div class="rma-servis-alan">
				<?php $this->render_servis_saat_alanlari( 'rma_servis', $gunler, $bas, $bit ); ?>
			</div>

			<?php if ( ! empty( $rozetler ) ) : ?>
			<hr>
			<h4 class="rma-secenek-h">Özel Rozetler</h4>
			<div class="rma-secenek-kutucuklar">
				<?php foreach ( $rozetler as $rozet ) : ?>
				<label>
					<input type="checkbox" name="rma_ozel_rozetler[]" value="<?php echo esc_attr( $rozet['slug'] ); ?>"
						<?php checked( in_array( $rozet['slug'], $secili_roz, true ) ); ?>>
					<span class="rma-rozet-onizleme" style="background:<?php echo esc_attr( $rozet['renk'] ); ?>">
						<?php echo esc_html( trim( $rozet['ikon'] . ' ' . $rozet['ad'] ) ); ?>
					</span>
				</label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tekrarlı tablolar için satır şablonu.
	 *
	 * JS satırı klonlamak yerine bu şablonu kullanır: tablo boşken de
	 * satır eklenebilir ve alan adları tek yerde tanımlı kalır.
	 * `__i__` yer tutucusu istemcide sıradaki indisle değiştirilir.
	 *
	 * @param array  $sutunlar [ [anahtar, tip, placeholder], … ].
	 * @param string $onek     Alan adı öneki (rma_porsiyon gibi).
	 * @return void
	 */
	private function render_tekrar_sablonu( array $sutunlar, $onek ) {
		echo '<template class="rma-tekrar-sablon"><tr class="rma-tekrar-satir">';

		foreach ( $sutunlar as $sutun ) {
			list( $anahtar, $tip, $varsayilan ) = array_pad( $sutun, 3, '' );

			$ad = $onek . '[__i__][' . $anahtar . ']';

			printf(
				'<td><input type="%s" class="widefat" name="%s" %s="%s"></td>',
				esc_attr( $tip ),
				esc_attr( $ad ),
				'color' === $tip ? 'value' : 'placeholder',
				esc_attr( $varsayilan )
			);
		}

		echo '<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>';
		echo '</tr></template>';
	}

	/**
	 * Gün kutucukları + başlangıç/bitiş saati alanları.
	 *
	 * Ürün meta kutusu ve kategori formu aynı biçimi kullanır.
	 *
	 * @param string $onek   Alan adı öneki ('rma_servis' | 'rma_cat_servis').
	 * @param int[]  $gunler Seçili günler.
	 * @param string $bas    Başlangıç saati.
	 * @param string $bit    Bitiş saati.
	 * @return void
	 */
	private function render_servis_saat_alanlari( $onek, array $gunler, $bas, $bit ) {
		?>
		<div class="rma-servis-gunler">
			<?php foreach ( RMA_Servis_Saati::gunler() as $no => $ad ) : ?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $onek ); ?>_gunler[]" value="<?php echo (int) $no; ?>"
					<?php checked( in_array( $no, $gunler, true ) ); ?>>
				<?php echo esc_html( $ad ); ?>
			</label>
			<?php endforeach; ?>
		</div>
		<p class="rma-servis-saatler">
			<label>Başlangıç <input type="time" name="<?php echo esc_attr( $onek ); ?>_bas" value="<?php echo esc_attr( $bas ); ?>"></label>
			<label>Bitiş <input type="time" name="<?php echo esc_attr( $onek ); ?>_bit" value="<?php echo esc_attr( $bit ); ?>"></label>
		</p>
		<?php
	}

	/**
	 * Meta kutusu kaydı.
	 *
	 * Ürün Detayları kutusundan AYRI bir nonce kullanır: hızlı düzenleme
	 * (quick edit) bu alanları hiç göndermez, o yüzden nonce yoksa mevcut
	 * değerlere dokunulmaz.
	 *
	 * @param int $post_id Ürün ID.
	 * @return void
	 */
	public function save_secenek_meta( $post_id ) {
		if ( ! isset( $_POST['rma_secenek_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rma_secenek_nonce'] ) ), 'rma_save_secenek' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		RMA_Porsiyon::kaydet( $post_id, wp_unslash( $_POST['rma_porsiyon'] ?? array() ) );

		RMA_Ekstra::urun_kaydet(
			$post_id,
			wp_unslash( $_POST['rma_ekstra_manuel'] ?? array() ),
			wp_unslash( $_POST['rma_ekstra_listeler'] ?? array() )
		);

		RMA_Ozel_Rozet::urun_kaydet( $post_id, wp_unslash( $_POST['rma_ozel_rozetler'] ?? array() ) );

		$mod = sanitize_key( (string) ( $_POST['rma_servis_mod'] ?? 'devral' ) );
		$mod = in_array( $mod, array( 'kapali', 'ozel' ), true ) ? $mod : 'devral';

		if ( 'devral' === $mod ) {
			delete_post_meta( $post_id, RMA_Servis_Saati::META_MOD );
		} else {
			update_post_meta( $post_id, RMA_Servis_Saati::META_MOD, $mod );
		}

		update_post_meta( $post_id, RMA_Servis_Saati::META_GUNLER, RMA_Servis_Saati::gunleri_temizle( wp_unslash( $_POST['rma_servis_gunler'] ?? array() ) ) );
		update_post_meta( $post_id, RMA_Servis_Saati::META_BAS, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_servis_bas'] ?? '' ) ) );
		update_post_meta( $post_id, RMA_Servis_Saati::META_BIT, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_servis_bit'] ?? '' ) ) );
	}

	/* =============================================================
	   KATEGORİ ALANLARI (servis saati)
	============================================================= */

	/**
	 * Kategori DÜZENLEME formundaki servis saati alanları.
	 *
	 * @param WP_Term $term Kategori.
	 * @return void
	 */
	public function edit_category_servis_fields( $term ) {
		$aktif  = '1' === (string) get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_AKTIF, true );
		$gunler = RMA_Servis_Saati::gunleri_temizle( get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_GUNLER, true ) );
		$bas    = RMA_Servis_Saati::saati_temizle( get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_BAS, true ) );
		$bit    = RMA_Servis_Saati::saati_temizle( get_term_meta( $term->term_id, RMA_Servis_Saati::TERIM_BIT, true ) );
		?>
		<tr class="form-field">
			<th><label>Servis saati</label></th>
			<td>
				<label>
					<input type="checkbox" name="rma_cat_servis_aktif" value="1" <?php checked( $aktif ); ?>>
					Bu kategori yalnızca belirli gün ve saatlerde servis edilsin
				</label>
				<div class="rma-servis-alan" style="margin-top:10px;">
					<?php $this->render_servis_saat_alanlari( 'rma_cat_servis', $gunler, $bas, $bit ); ?>
				</div>
				<p class="description">
					Örnek: kahvaltı için Pazartesi–Cuma, 07:00–11:00. Saat dışında ürünler menüde kalır,
					"Servis dışı" etiketiyle gösterilir ve sepete eklenemez.
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Kategori servis saati kaydı.
	 *
	 * @param int $term_id Kategori ID.
	 * @return void
	 */
	public function save_category_servis_fields( $term_id ) {
		// Alanları basmayan formlardan (hızlı ekleme) gelen kayıtlarda
		// mevcut değere dokunulmaz.
		if ( ! isset( $_POST['rma_cat_servis_bas'] ) && ! isset( $_POST['rma_cat_servis_aktif'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_AKTIF, isset( $_POST['rma_cat_servis_aktif'] ) ? '1' : '0' );
		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_GUNLER, RMA_Servis_Saati::gunleri_temizle( wp_unslash( $_POST['rma_cat_servis_gunler'] ?? array() ) ) );
		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_BAS, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_cat_servis_bas'] ?? '' ) ) );
		update_term_meta( $term_id, RMA_Servis_Saati::TERIM_BIT, RMA_Servis_Saati::saati_temizle( wp_unslash( $_POST['rma_cat_servis_bit'] ?? '' ) ) );

		$this->bump_cache_version();
	}

	/* =============================================================
	   AYAR SAYFASI — ekstra listeleri + özel rozetler
	============================================================= */

	/**
	 * "Seçenek & Rozet" sayfası.
	 *
	 * @return void
	 */
	public function render_secenekler_page() {
		$this->page_header(
			'Seçenek &amp; Rozet',
			'Birden çok üründe kullanacağınız ekstra listelerini ve kendi rozetlerinizi burada tanımlayın.'
		);

		if ( isset( $_GET['secenek_msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Kaydedildi.</p></div>';
		}

		$listeler = RMA_Ekstra::listeler();
		$rozetler = RMA_Ozel_Rozet::tanimlar();
		?>
		<div class="rma-secenek-sayfa">

			<h2 id="rma-ekstra-listeleri">Ekstra Listeleri</h2>
			<p class="description">
				"Soslar", "İçecekler" gibi grupları bir kez tanımlayın, ürün ekranından işaretleyerek kullanın.
				Fiyatı burada değiştirdiğinizde listeyi kullanan tüm ürünlerde güncellenir.
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rma_ekstra_listeleri_kaydet">
				<?php wp_nonce_field( 'rma_ekstra_listeleri' ); ?>

				<div class="rma-liste-kaplar" data-rma-listeler>
					<?php foreach ( $listeler as $li => $liste ) : ?>
					<div class="rma-liste-kap">
						<input type="hidden" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][id]" value="<?php echo esc_attr( $liste['id'] ); ?>">
						<p>
							<input type="text" class="regular-text" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][ad]"
								value="<?php echo esc_attr( $liste['ad'] ); ?>" placeholder="Liste adı (Soslar)">
							<button type="button" class="button-link rma-liste-sil">Listeyi sil</button>
						</p>
						<table class="widefat rma-tekrar" data-rma-tekrar="liste-<?php echo (int) $li; ?>" data-azami="30">
							<thead><tr><th style="width:60%;">Ürün</th><th style="width:30%;">Fiyat (₺)</th><th></th></tr></thead>
							<tbody>
								<?php foreach ( $liste['urunler'] as $ui => $urun ) : ?>
								<tr class="rma-tekrar-satir">
									<td><input type="text" class="widefat" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][urunler][<?php echo (int) $ui; ?>][ad]" value="<?php echo esc_attr( $urun['ad'] ); ?>"></td>
									<td><input type="text" class="widefat" name="rma_ekstra_listeleri[<?php echo (int) $li; ?>][urunler][<?php echo (int) $ui; ?>][fiyat]" value="<?php echo esc_attr( $urun['fiyat'] ); ?>"></td>
									<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
							<?php
							$this->render_tekrar_sablonu(
								array(
									array( 'ad', 'text', 'Ketçap' ),
									array( 'fiyat', 'text', '10' ),
								),
								'rma_ekstra_listeleri[' . (int) $li . '][urunler]'
							);
							?>
						</table>
						<p><button type="button" class="button rma-tekrar-ekle" data-hedef="liste-<?php echo (int) $li; ?>">+ Ürün ekle</button></p>
					</div>
					<?php endforeach; ?>
				</div>

				<template id="rma-liste-sablon">
					<div class="rma-liste-kap">
						<p>
							<input type="text" class="regular-text" name="rma_ekstra_listeleri[__li__][ad]" placeholder="Liste adı (Soslar)">
							<button type="button" class="button-link rma-liste-sil">Listeyi sil</button>
						</p>
						<table class="widefat rma-tekrar" data-rma-tekrar="liste-__li__" data-azami="30">
							<thead><tr><th style="width:60%;">Ürün</th><th style="width:30%;">Fiyat (₺)</th><th></th></tr></thead>
							<tbody>
								<tr class="rma-tekrar-satir">
									<td><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][0][ad]" placeholder="Ketçap"></td>
									<td><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][0][fiyat]" placeholder="10"></td>
									<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
								</tr>
							</tbody>
							<template class="rma-tekrar-sablon">
								<tr class="rma-tekrar-satir">
									<td><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][__i__][ad]" placeholder="Ketçap"></td>
									<td><input type="text" class="widefat" name="rma_ekstra_listeleri[__li__][urunler][__i__][fiyat]" placeholder="10"></td>
									<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
								</tr>
							</template>
						</table>
						<p><button type="button" class="button rma-tekrar-ekle" data-hedef="liste-__li__">+ Ürün ekle</button></p>
					</div>
				</template>

				<p>
					<button type="button" class="button" data-rma-liste-ekle>+ Yeni liste</button>
					<button type="submit" class="button button-primary">Listeleri Kaydet</button>
				</p>
			</form>

			<hr>

			<h2 id="rma-ozel-rozetler">Özel Rozetler</h2>
			<p class="description">
				"Hızlı Servis", "Acı", "Yüksek Protein"… Tanımladığınız rozetler ürün ekranında kutucuk olarak çıkar,
				menü kartında ve ürün detayında gösterilir.
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rma_ozel_rozet_kaydet">
				<?php wp_nonce_field( 'rma_ozel_rozet' ); ?>

				<table class="widefat rma-tekrar" data-rma-tekrar="rozet" data-azami="<?php echo (int) RMA_Ozel_Rozet::AZAMI; ?>">
					<thead>
						<tr>
							<th style="width:45%;">Rozet adı</th>
							<th style="width:15%;">İkon</th>
							<th style="width:30%;">Renk</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rozetler as $i => $rozet ) : ?>
						<tr class="rma-tekrar-satir">
							<td>
								<input type="hidden" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $rozet['slug'] ); ?>">
								<input type="text" class="widefat" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][ad]" value="<?php echo esc_attr( $rozet['ad'] ); ?>">
							</td>
							<td><input type="text" class="widefat" maxlength="4" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][ikon]" value="<?php echo esc_attr( $rozet['ikon'] ); ?>" placeholder="⚡"></td>
							<td><input type="color" name="rma_ozel_rozetler[<?php echo (int) $i; ?>][renk]" value="<?php echo esc_attr( $rozet['renk'] ); ?>"></td>
							<td><button type="button" class="button-link rma-tekrar-sil" aria-label="Satırı sil">✕</button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
					<?php
					// slug sütunu şablonda yok: yeni rozetin slug'ı kaydederken
					// addan türetilir (bkz. RMA_Ozel_Rozet::temizle).
					$this->render_tekrar_sablonu(
						array(
							array( 'ad', 'text', 'Hızlı Servis' ),
							array( 'ikon', 'text', '⚡' ),
							array( 'renk', 'color', RMA_Ozel_Rozet::RENK ),
						),
						'rma_ozel_rozetler'
					);
					?>
				</table>

				<p>
					<button type="button" class="button rma-tekrar-ekle" data-hedef="rozet">+ Rozet ekle</button>
					<button type="submit" class="button button-primary">Rozetleri Kaydet</button>
				</p>
			</form>
		</div>
		<?php
		$this->page_footer();
	}

	/**
	 * Ekstra listelerini kaydeder (admin-post).
	 *
	 * @return void
	 */
	public function handle_ekstra_listeleri_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}
		check_admin_referer( 'rma_ekstra_listeleri' );

		RMA_Ekstra::listeleri_kaydet( wp_unslash( $_POST['rma_ekstra_listeleri'] ?? array() ) );
		$this->bump_cache_version();

		wp_safe_redirect( $this->admin_page_url( 'qrms-rm-secenekler', array( 'secenek_msg' => 'kaydedildi' ), 'rma-ekstra-listeleri' ) );
		exit;
	}

	/**
	 * Özel rozet tanımlarını kaydeder (admin-post).
	 *
	 * @return void
	 */
	public function handle_ozel_rozet_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}
		check_admin_referer( 'rma_ozel_rozet' );

		RMA_Ozel_Rozet::kaydet( wp_unslash( $_POST['rma_ozel_rozetler'] ?? array() ) );
		$this->bump_cache_version();

		wp_safe_redirect( $this->admin_page_url( 'qrms-rm-secenekler', array( 'secenek_msg' => 'kaydedildi' ), 'rma-ozel-rozetler' ) );
		exit;
	}
}
