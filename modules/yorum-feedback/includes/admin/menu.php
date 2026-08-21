<?php
if (!defined('ABSPATH')) exit;

// 2. YÖNETİM SAYFALARI — KAYIT DEFTERİ, ADRESLER, ESKİ ADRES YÖNLENDİRMELERİ
//
// Suite'e taşınırken modülün kendi "QR Yorumlar" üst menüsü kaldırıldı: sol
// menüde yalnızca "Yorum & Feedback" satırı var, yedi ekrana o satırın açtığı
// hub ekranındaki kartlardan gidiliyor. Sayfa kaydının kendisi module.php'de
// yapılır (suite entegrasyonu orada durur); burada yalnızca sayfaların TANIMI,
// adres üretimi ve eski adres haritası vardır.
//
// Sayfa tanımı tek yerde (qrm_pro_admin_pages) durur; sayfa kayıtları, hub
// kartları ve varlık kuyruğunun "bu benim sayfam mı" kontrolü aynı listeden
// beslenir — restoran-menu modülündeki get_subpages() deseninin aynısı.
//
// ÖNEMLİ (v4.2.0'daki 403 hatasının dersi): Bir admin sayfası ASLA `admin_menu`
// sırasında add_submenu_page() ile kaydedilip remove_submenu_page() ile menüden
// çıkarılmamalı. WordPress, admin.php?page=X isteklerinde üst menüyü $submenu
// dizisini tarayarak bulur (get_admin_page_parent()); satır o anda kaldırılmışsa
// parent boş kalır, hook adı hesaplanamaz ve WordPress "bu sayfaya erişmenize
// izin verilmiyor" der. Bu yüzden her satır gerçek bir sayfa olarak kayıtlıdır;
// düzenleyici gibi ara ekranlar ise kayıtlı bir sayfanın görünümüdür (view).
//
// Sayfaların sol menüde GÖRÜNMEMESİ ayrı bir iştir ve suite tarafından, route
// çözüldükten sonra (`admin_head`) yapılır — bkz.
// QRMS_Admin::hide_module_subpages(). Modül burada hiçbir şey gizlemez.

/**
 * Modülün yönetim sayfaları — tek kaynak.
 *
 * Sıra, hub ekranındaki kart sırasını belirler. `render` her sayfanın tek
 * callback'idir; `desc` ve `icon` hub kartlarında kullanılır.
 *
 * @return array<string,array{title:string,menu_title:string,render:string,desc:string,icon:string}>
 */
function qrm_pro_admin_pages() {
    return [
        'qrms-yf-yorumlar' => [
            'title'      => 'Tüm Yorumlar',
            'menu_title' => 'Tüm Yorumlar',
            'render'     => 'qrm_pro_admin_dashboard',
            'desc'       => 'Gelen değerlendirmeleri okuyun; yayınlayın, yayından kaldırın ya da silin.',
            'icon'       => 'dashicons-testimonial',
        ],
        'qrms-yf-icgoruler' => [
            'title'      => 'Detaylı İçgörüler',
            'menu_title' => 'Detaylı İçgörüler',
            'render'     => 'qrm_pro_admin_insights',
            'desc'       => 'Genel ortalamanız ve her puanlama kriterinin ayrı performansı.',
            'icon'       => 'dashicons-chart-bar',
        ],
        'qrms-yf-form-alanlari' => [
            'title'      => 'Müşteri Bilgileri Formu',
            'menu_title' => 'Müşteri Bilgileri Formu',
            'render'     => 'qrm_pro_admin_form_builder',
            'desc'       => 'Yorum formunda müşteriden istenecek bilgi alanları ve sıraları.',
            'icon'       => 'dashicons-id-alt',
        ],
        'qrms-yf-ayarlar' => [
            'title'      => 'Ayarlar & Puanlama',
            'menu_title' => 'Ayarlar & Puanlama',
            'render'     => 'qrm_pro_admin_settings',
            'desc'       => 'Puanlama kriterleri, form görünümü, otomatik onay ve spam koruması.',
            'icon'       => 'dashicons-admin-settings',
        ],
        'qrms-yf-iletisim' => [
            'title'      => 'İletişim',
            'menu_title' => 'İletişim',
            'render'     => 'qrm_pro_admin_contact',
            'desc'       => 'İletişim sayfanıza koyacağınız form ve kısa kodu.',
            'icon'       => 'dashicons-email-alt',
        ],
        'qrms-yf-odul' => [
            'title'      => 'Google & Ödül Sistemi',
            'menu_title' => 'Google & Ödül Sistemi',
            'render'     => 'qrm_reward_admin_page',
            'desc'       => 'Google yorum yönlendirmesi, indirim kodu popup\'ı ve kod yönetimi.',
            'icon'       => 'dashicons-tickets-alt',
        ],
        'qrms-yf-formlar' => [
            'title'      => 'Formlar',
            'menu_title' => 'Formlar',
            'render'     => 'qrm_cf_admin_forms_page',
            'desc'       => 'Şikayet, rezervasyon, anket… Kendi formlarınız ve gelen gönderiler.',
            'icon'       => 'dashicons-feedback',
        ],
    ];
}

/**
 * Modülün başlangıç (hub) ekranının slug'ı.
 *
 * Suite'in modül satırı budur; QRMS_Admin bu slug'ı MODULE_PAGE_PREFIX ile
 * üretir, burada tek bir yerden okunur.
 *
 * @return string
 */
function qrm_pro_hub_slug() {
    return class_exists('QRMS_Admin')
        ? QRMS_Admin::get_module_page_slug('yorum-feedback')
        : 'qrms-module-yorum-feedback';
}

/**
 * Modülün bir yönetim sayfasının tam adresi.
 *
 * Bağlantılar TEK yerden üretilir: sayfa slug'ı ile bağlantılar arasında kayma
 * olmasın diye (v4.2.0'daki 403 hatasının kaynağı buydu). Göreli "?page=..."
 * bağlantıları bilinçli olarak kullanılmaz — suite menüsündeki bir sayfadan
 * basıldığında hangi ekrana gittikleri belirsizdi.
 *
 * @param string $slug Sayfa slug'ı (qrm_pro_admin_pages anahtarı) ya da hub slug'ı.
 * @param array  $args Ek sorgu parametreleri.
 * @return string
 */
function qrm_pro_admin_url($slug, $args = []) {
    $args = array_merge(['page' => $slug], $args);
    return admin_url('admin.php?' . http_build_query($args));
}

/**
 * Suite'e taşınmadan önceki sayfa adresine (yer imi, eski link, eski e-posta
 * bildirimi) gelenler yeni adreslerine yönlendirilir.
 *
 * Kanca seçimi önemli: wp-admin/admin.php, menüyü kuran wp-admin/menu.php'yi
 * do_action('admin_init')'ten ÖNCE yükler ve erişim kontrolünü orada yapar. Bu
 * yüzden admin_init çok geç kalır; WordPress'in 403'ten hemen önce tetiklediği
 * admin_page_access_denied kancası kullanılır (henüz çıktı basılmadığı için
 * yönlendirme güvenle yapılabilir).
 */
add_action('admin_page_access_denied', 'qrm_pro_redirect_legacy_pages');
function qrm_pro_redirect_legacy_pages() {
    if (empty($_GET['page'])) return;

    $target = qrm_pro_legacy_page_target(sanitize_key($_GET['page']), wp_unslash($_GET));
    if ($target === '') return;

    wp_safe_redirect($target);
    exit;
}

/**
 * Eski sayfa slug'ının yeni adresi. Bilinmeyen slug için boş string döner.
 *
 * İki kuşak adres birden karşılanır:
 *   - v4.1.x'in ayrı menü maddeleri (qrm-pro-form, qrm-pro-insights, …)
 *   - v4.2.x'in sekmeli tekil sayfaları (qrm-pro-main&tab=insights, …)
 * Sekme/görünüm parametreleri artık ayrı birer sayfa olduğu için, adreste
 * taşınan tab/sub değerleri hedef sayfayı belirlemekte kullanılır.
 *
 * Saf fonksiyon: yönlendirme ve exit çağıranın işi, böylece test edilebilir.
 *
 * @param string $page Eski sayfa slug'ı.
 * @param array  $args İstekteki diğer parametreler (tab, sub, view, form_id).
 * @return string Tam admin adresi ya da boş string.
 */
function qrm_pro_legacy_page_target($page, $args = []) {
    $tab     = isset($args['tab']) ? sanitize_key($args['tab']) : '';
    $sub     = isset($args['sub']) ? sanitize_key($args['sub']) : '';
    $view    = isset($args['view']) ? sanitize_key($args['view']) : '';
    $form_id = isset($args['form_id']) ? intval($args['form_id']) : 0;

    switch ($page) {
        // Tüm Yorumlar — "İçgörüler" sekmesi artık ayrı sayfa.
        case 'qrm-pro-main':
            return $tab === 'insights'
                ? qrm_pro_admin_url('qrms-yf-icgoruler')
                : qrm_pro_admin_url('qrms-yf-yorumlar');

        case 'qrm-pro-insights':
            return qrm_pro_admin_url('qrms-yf-icgoruler');

        // Ayarlar — "Müşteri Bilgileri Formu" sekmesi artık ayrı sayfa.
        case 'qrm-pro-settings':
            return $sub === 'alanlar'
                ? qrm_pro_admin_url('qrms-yf-form-alanlari')
                : qrm_pro_admin_url('qrms-yf-ayarlar');

        case 'qrm-pro-form':
            return qrm_pro_admin_url('qrms-yf-form-alanlari');

        case 'qrm-pro-contact':
            return qrm_pro_admin_url('qrms-yf-iletisim');

        case 'qrm-pro-rewards':
            $reward_args = [];
            if ($tab === 'codes') $reward_args['tab'] = 'codes';
            if ($sub !== '')      $reward_args['sub'] = $sub;
            return qrm_pro_admin_url('qrms-yf-odul', $reward_args);

        // Formlar — sekmeleri ve düzenleyici görünümü kendi sayfasında korunur.
        case 'qrm-forms':
            $form_args = [];
            if ($view === 'edit')        $form_args['view'] = 'edit';
            if ($tab === 'submissions')  $form_args['tab'] = 'submissions';
            if ($form_id > 0)            $form_args['form_id'] = $form_id;
            return qrm_pro_admin_url('qrms-yf-formlar', $form_args);

        case 'qrm-form-edit':
            $form_args = ['view' => 'edit'];
            if ($form_id > 0) $form_args['form_id'] = $form_id;
            return qrm_pro_admin_url('qrms-yf-formlar', $form_args);

        case 'qrm-form-submissions':
            $form_args = ['tab' => 'submissions'];
            if ($form_id > 0) $form_args['form_id'] = $form_id;
            return qrm_pro_admin_url('qrms-yf-formlar', $form_args);
    }

    return '';
}

/**
 * Menü rozetlerinin kaynağı — okunmamış form gönderimi ve eksik ödül kurulumu.
 *
 * Tek kaynak: hem sol menüdeki "Yorum & Feedback" satırı hem de hub
 * ekranındaki kartlar buradan beslenir.
 *
 * @return array{formlar:int,odul:bool}
 */
function qrm_pro_menu_badge_state() {
    // Menü kaydı sırasında her satır için bir kez sorulur; sayaç sorgusu istek
    // başına bir kez çalışsın diye sonuç saklanır.
    static $state = null;
    if ($state !== null) return $state;

    $settings = qrm_pro_get_settings();

    $state = [
        'formlar' => (int) qrm_cf_unread_total(),
        // Ödül modülü açık ama yapılandırması eksikse (Google linki boş ya da
        // yönlendirme kapalı) popup hiç gösterilmez. Sessizce fark edilmemesin.
        'odul'    => !empty($settings['qrm_reward_enabled']) && !qrm_reward_is_active($settings),
    ];

    return $state;
}

/**
 * Modülün sol menüdeki satırına eklenecek rozet HTML'i (yoksa boş string).
 *
 * Ekranların kendi menü satırları kaldırıldığı (sol menü tek seviyeye indi)
 * için rozet artık modülün TEK satırında toplanır: okunmamış form gönderimi
 * varsa sayısı, yoksa eksik ödül kurulumu için bir ünlem. Hangi ekranın
 * ilgilendiği hub kartlarındaki rozetlerden okunur.
 *
 * @return string
 */
function qrm_pro_menu_badge() {
    $state = qrm_pro_menu_badge_state();

    if ($state['formlar'] > 0) {
        return ' <span class="update-plugins count-' . intval($state['formlar']) . '" title="Okunmamış form gönderimi">'
            . '<span class="update-count">' . intval($state['formlar']) . '</span></span>';
    }

    if ($state['odul']) {
        return ' <span class="update-plugins count-1" title="Ödül popup modülü açık ama Google Yorum Linki/yönlendirme eksik">'
            . '<span class="update-count">!</span></span>';
    }

    return '';
}
