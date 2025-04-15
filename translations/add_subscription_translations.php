<?php
/**
 * Script to add subscription-related translations for all languages
 * 
 * This script adds translations for subscription-related terms that appear
 * in the subscription pages and student dashboard.
 */

// Database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Get all active languages
$stmt = $pdo->query("SELECT * FROM languages WHERE is_active = 1");
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Subscription-related translations
$translations = [
    // Subscription info card
    'subscription_info' => [
        'ar' => 'معلومات الاشتراك',
        'en' => 'Subscription Info',
        'fr' => 'Informations d\'abonnement',
        'es' => 'Información de suscripción',
        'tr' => 'Abonelik Bilgisi',
        'ur' => 'سبسکرپشن کی معلومات',
        'id' => 'Informasi Langganan',
        'ms' => 'Maklumat Langganan'
    ],
    'active_subscription' => [
        'ar' => 'الاشتراك النشط',
        'en' => 'Active Subscription',
        'fr' => 'Abonnement actif',
        'es' => 'Suscripción activa',
        'tr' => 'Aktif Abonelik',
        'ur' => 'فعال سبسکرپشن',
        'id' => 'Langganan Aktif',
        'ms' => 'Langganan Aktif'
    ],
    'my_subscriptions' => [
        'ar' => 'اشتراكاتي',
        'en' => 'My Subscriptions',
        'fr' => 'Mes abonnements',
        'es' => 'Mis suscripciones',
        'tr' => 'Aboneliklerim',
        'ur' => 'میری سبسکرپشنز',
        'id' => 'Langganan Saya',
        'ms' => 'Langganan Saya'
    ],
    'plan' => [
        'ar' => 'الخطة',
        'en' => 'Plan',
        'fr' => 'Plan',
        'es' => 'Plan',
        'tr' => 'Plan',
        'ur' => 'پلان',
        'id' => 'Paket',
        'ms' => 'Pelan'
    ],
    'lessons_per_month' => [
        'ar' => 'دروس في الشهر',
        'en' => 'lessons per month',
        'fr' => 'leçons par mois',
        'es' => 'lecciones por mes',
        'tr' => 'aylık ders',
        'ur' => 'ماہانہ اسباق',
        'id' => 'pelajaran per bulan',
        'ms' => 'pelajaran sebulan'
    ],
    'lessons' => [
        'ar' => 'دروس',
        'en' => 'lessons',
        'fr' => 'leçons',
        'es' => 'lecciones',
        'tr' => 'dersler',
        'ur' => 'اسباق',
        'id' => 'pelajaran',
        'ms' => 'pelajaran'
    ],
    'start_date' => [
        'ar' => 'تاريخ البدء',
        'en' => 'start date',
        'fr' => 'date de début',
        'es' => 'fecha de inicio',
        'tr' => 'başlangıç tarihi',
        'ur' => 'شروع کی تاریخ',
        'id' => 'tanggal mulai',
        'ms' => 'tarikh mula'
    ],
    'end_date' => [
        'ar' => 'تاريخ الانتهاء',
        'en' => 'end date',
        'fr' => 'date de fin',
        'es' => 'fecha de finalización',
        'tr' => 'bitiş tarihi',
        'ur' => 'ختم ہونے کی تاریخ',
        'id' => 'tanggal berakhir',
        'ms' => 'tarikh tamat'
    ],
    'status' => [
        'ar' => 'الحالة',
        'en' => 'status',
        'fr' => 'statut',
        'es' => 'estado',
        'tr' => 'durum',
        'ur' => 'حالت',
        'id' => 'status',
        'ms' => 'status'
    ],
    'price' => [
        'ar' => 'السعر',
        'en' => 'price',
        'fr' => 'prix',
        'es' => 'precio',
        'tr' => 'fiyat',
        'ur' => 'قیمت',
        'id' => 'harga',
        'ms' => 'harga'
    ],
    'currency' => [
        'ar' => 'عملة',
        'en' => 'currency',
        'fr' => 'devise',
        'es' => 'moneda',
        'tr' => 'para birimi',
        'ur' => 'کرنسی',
        'id' => 'mata uang',
        'ms' => 'mata wang'
    ],
    'subscription_days_remaining' => [
        'ar' => 'الأيام المتبقية للاشتراك',
        'en' => 'subscription days remaining',
        'fr' => 'jours d\'abonnement restants',
        'es' => 'días restantes de suscripción',
        'tr' => 'kalan abonelik günleri',
        'ur' => 'سبسکرپشن کے باقی دن',
        'id' => 'sisa hari langganan',
        'ms' => 'baki hari langganan'
    ],
    'days_remaining' => [
        'ar' => 'الأيام المتبقية',
        'en' => 'days remaining',
        'fr' => 'jours restants',
        'es' => 'días restantes',
        'tr' => 'kalan günler',
        'ur' => 'باقی دن',
        'id' => 'sisa hari',
        'ms' => 'baki hari'
    ],
    'days' => [
        'ar' => 'أيام',
        'en' => 'days',
        'fr' => 'jours',
        'es' => 'días',
        'tr' => 'gün',
        'ur' => 'دن',
        'id' => 'hari',
        'ms' => 'hari'
    ],
    'subscription_history' => [
        'ar' => 'سجل الاشتراكات',
        'en' => 'subscription history',
        'fr' => 'historique des abonnements',
        'es' => 'historial de suscripciones',
        'tr' => 'abonelik geçmişi',
        'ur' => 'سبسکرپشن کی تاریخ',
        'id' => 'riwayat langganan',
        'ms' => 'sejarah langganan'
    ],
    'actions' => [
        'ar' => 'إجراءات',
        'en' => 'actions',
        'fr' => 'actions',
        'es' => 'acciones',
        'tr' => 'işlemler',
        'ur' => 'اقدامات',
        'id' => 'tindakan',
        'ms' => 'tindakan'
    ],
    'amount' => [
        'ar' => 'المبلغ',
        'en' => 'amount',
        'fr' => 'montant',
        'es' => 'cantidad',
        'tr' => 'miktar',
        'ur' => 'رقم',
        'id' => 'jumlah',
        'ms' => 'jumlah'
    ],
    'dates' => [
        'ar' => 'التواريخ',
        'en' => 'dates',
        'fr' => 'dates',
        'es' => 'fechas',
        'tr' => 'tarihler',
        'ur' => 'تاریخیں',
        'id' => 'tanggal',
        'ms' => 'tarikh'
    ],
    'duration' => [
        'ar' => 'المدة',
        'en' => 'duration',
        'fr' => 'durée',
        'es' => 'duración',
        'tr' => 'süre',
        'ur' => 'مدت',
        'id' => 'durasi',
        'ms' => 'tempoh'
    ],
    'month' => [
        'ar' => 'شهر',
        'en' => 'month',
        'fr' => 'mois',
        'es' => 'mes',
        'tr' => 'ay',
        'ur' => 'مہینہ',
        'id' => 'bulan',
        'ms' => 'bulan'
    ],
    'months' => [
        'ar' => 'أشهر',
        'en' => 'months',
        'fr' => 'mois',
        'es' => 'meses',
        'tr' => 'ay',
        'ur' => 'مہینے',
        'id' => 'bulan',
        'ms' => 'bulan'
    ],
    'paid' => [
        'ar' => 'مدفوع',
        'en' => 'paid',
        'fr' => 'payé',
        'es' => 'pagado',
        'tr' => 'ödendi',
        'ur' => 'ادا کیا گیا',
        'id' => 'dibayar',
        'ms' => 'dibayar'
    ],
    'pending' => [
        'ar' => 'قيد الانتظار',
        'en' => 'pending',
        'fr' => 'en attente',
        'es' => 'pendiente',
        'tr' => 'beklemede',
        'ur' => 'زیر التواء',
        'id' => 'tertunda',
        'ms' => 'tertunda'
    ],
    'payment_status' => [
        'ar' => 'حالة الدفع',
        'en' => 'payment status',
        'fr' => 'statut du paiement',
        'es' => 'estado del pago',
        'tr' => 'ödeme durumu',
        'ur' => 'ادائیگی کی حالت',
        'id' => 'status pembayaran',
        'ms' => 'status pembayaran'
    ],
    'no_active_subscription' => [
        'ar' => 'لا يوجد اشتراك نشط',
        'en' => 'No active subscription',
        'fr' => 'Aucun abonnement actif',
        'es' => 'Sin suscripción activa',
        'tr' => 'Aktif abonelik yok',
        'ur' => 'کوئی فعال سبسکرپشن نہیں',
        'id' => 'Tidak ada langganan aktif',
        'ms' => 'Tiada langganan aktif'
    ],
    'please_subscribe_to_continue' => [
        'ar' => 'يرجى الاشتراك للمتابعة',
        'en' => 'Please subscribe to continue',
        'fr' => 'Veuillez vous abonner pour continuer',
        'es' => 'Por favor suscríbase para continuar',
        'tr' => 'Devam etmek için lütfen abone olun',
        'ur' => 'جاری رکھنے کے لیے براہ کرم سبسکرائب کریں',
        'id' => 'Silakan berlangganan untuk melanjutkan',
        'ms' => 'Sila langgan untuk meneruskan'
    ],
    'available_plans' => [
        'ar' => 'الخطط المتاحة',
        'en' => 'Available Plans',
        'fr' => 'Plans disponibles',
        'es' => 'Planes disponibles',
        'tr' => 'Mevcut Planlar',
        'ur' => 'دستیاب پلانز',
        'id' => 'Paket Tersedia',
        'ms' => 'Pelan Tersedia'
    ],
    'subscribe_now' => [
        'ar' => 'اشترك الآن',
        'en' => 'Subscribe Now',
        'fr' => 'S\'abonner maintenant',
        'es' => 'Suscríbase ahora',
        'tr' => 'Şimdi Abone Ol',
        'ur' => 'ابھی سبسکرائب کریں',
        'id' => 'Berlangganan Sekarang',
        'ms' => 'Langgan Sekarang'
    ],
    'view_subscription_details' => [
        'ar' => 'عرض تفاصيل الاشتراك',
        'en' => 'View Subscription Details',
        'fr' => 'Voir les détails de l\'abonnement',
        'es' => 'Ver detalles de la suscripción',
        'tr' => 'Abonelik Detaylarını Görüntüle',
        'ur' => 'سبسکرپشن کی تفصیلات دیکھیں',
        'id' => 'Lihat Detail Langganan',
        'ms' => 'Lihat Butiran Langganan'
    ],
    'view_all_subscription_options' => [
        'ar' => 'عرض جميع خيارات الاشتراك',
        'en' => 'View All Subscription Options',
        'fr' => 'Voir toutes les options d\'abonnement',
        'es' => 'Ver todas las opciones de suscripción',
        'tr' => 'Tüm Abonelik Seçeneklerini Görüntüle',
        'ur' => 'تمام سبسکرپشن آپشنز دیکھیں',
        'id' => 'Lihat Semua Opsi Langganan',
        'ms' => 'Lihat Semua Pilihan Langganan'
    ],
    'renew_subscription' => [
        'ar' => 'تجديد الاشتراك',
        'en' => 'Renew Subscription',
        'fr' => 'Renouveler l\'abonnement',
        'es' => 'Renovar suscripción',
        'tr' => 'Aboneliği Yenile',
        'ur' => 'سبسکرپشن کی تجدید کریں',
        'id' => 'Perpanjang Langganan',
        'ms' => 'Perbaharui Langganan'
    ],
    'expiring_soon' => [
        'ar' => 'ينتهي قريبًا',
        'en' => 'Expiring Soon',
        'fr' => 'Expire bientôt',
        'es' => 'Vence pronto',
        'tr' => 'Yakında Sona Eriyor',
        'ur' => 'جلد ختم ہو رہا ہے',
        'id' => 'Segera Berakhir',
        'ms' => 'Akan Tamat'
    ],
    'renewal_not_available_yet' => [
        'ar' => 'التجديد غير متاح بعد',
        'en' => 'Renewal not available yet',
        'fr' => 'Renouvellement pas encore disponible',
        'es' => 'Renovación aún no disponible',
        'tr' => 'Yenileme henüz mevcut değil',
        'ur' => 'تجدید ابھی دستیاب نہیں ہے',
        'id' => 'Perpanjangan belum tersedia',
        'ms' => 'Pembaharuan belum tersedia'
    ],
    'can_renew_when_5_days_remaining' => [
        'ar' => 'يمكن التجديد عندما يتبقى 5 أيام',
        'en' => 'You can renew when 5 days remaining',
        'fr' => 'Vous pouvez renouveler quand il reste 5 jours',
        'es' => 'Puede renovar cuando quedan 5 días',
        'tr' => '5 gün kaldığında yenileyebilirsiniz',
        'ur' => '5 دن باقی رہنے پر آپ تجدید کر سکتے ہیں',
        'id' => 'Anda dapat memperpanjang ketika tersisa 5 hari',
        'ms' => 'Anda boleh memperbaharui apabila baki 5 hari'
    ],
    'current_subscription_ends' => [
        'ar' => 'ينتهي الاشتراك الحالي',
        'en' => 'Current subscription ends',
        'fr' => 'L\'abonnement actuel se termine',
        'es' => 'La suscripción actual termina',
        'tr' => 'Mevcut abonelik sona eriyor',
        'ur' => 'موجودہ سبسکرپشن ختم ہوتی ہے',
        'id' => 'Langganan saat ini berakhir',
        'ms' => 'Langganan semasa tamat'
    ],
    'select_subscription_plan' => [
        'ar' => 'اختر خطة الاشتراك',
        'en' => 'Select Subscription Plan',
        'fr' => 'Sélectionnez le plan d\'abonnement',
        'es' => 'Seleccione el plan de suscripción',
        'tr' => 'Abonelik Planı Seçin',
        'ur' => 'سبسکرپشن پلان منتخب کریں',
        'id' => 'Pilih Paket Langganan',
        'ms' => 'Pilih Pelan Langganan'
    ],
    'select_plan' => [
        'ar' => 'اختر خطة',
        'en' => 'Select Plan',
        'fr' => 'Sélectionner un plan',
        'es' => 'Seleccionar plan',
        'tr' => 'Plan Seç',
        'ur' => 'پلان منتخب کریں',
        'id' => 'Pilih Paket',
        'ms' => 'Pilih Pelan'
    ],
    'subscription_duration' => [
        'ar' => 'مدة الاشتراك',
        'en' => 'Subscription Duration',
        'fr' => 'Durée de l\'abonnement',
        'es' => 'Duración de la suscripción',
        'tr' => 'Abonelik Süresi',
        'ur' => 'سبسکرپشن کی مدت',
        'id' => 'Durasi Langganan',
        'ms' => 'Tempoh Langganan'
    ],
    '1_month' => [
        'ar' => 'شهر واحد',
        'en' => '1 Month',
        'fr' => '1 Mois',
        'es' => '1 Mes',
        'tr' => '1 Ay',
        'ur' => '1 مہینہ',
        'id' => '1 Bulan',
        'ms' => '1 Bulan'
    ],
    '3_months' => [
        'ar' => '3 أشهر',
        'en' => '3 Months',
        'fr' => '3 Mois',
        'es' => '3 Meses',
        'tr' => '3 Ay',
        'ur' => '3 مہینے',
        'id' => '3 Bulan',
        'ms' => '3 Bulan'
    ],
    '6_months' => [
        'ar' => '6 أشهر',
        'en' => '6 Months',
        'fr' => '6 Mois',
        'es' => '6 Meses',
        'tr' => '6 Ay',
        'ur' => '6 مہینے',
        'id' => '6 Bulan',
        'ms' => '6 Bulan'
    ],
    '12_months' => [
        'ar' => '12 شهر',
        'en' => '12 Months',
        'fr' => '12 Mois',
        'es' => '12 Meses',
        'tr' => '12 Ay',
        'ur' => '12 مہینے',
        'id' => '12 Bulan',
        'ms' => '12 Bulan'
    ],
    'payment_method' => [
        'ar' => 'طريقة الدفع',
        'en' => 'Payment Method',
        'fr' => 'Méthode de paiement',
        'es' => 'Método de pago',
        'tr' => 'Ödeme Yöntemi',
        'ur' => 'ادائیگی کا طریقہ',
        'id' => 'Metode Pembayaran',
        'ms' => 'Kaedah Pembayaran'
    ],
    'cash' => [
        'ar' => 'نقدًا',
        'en' => 'Cash',
        'fr' => 'Espèces',
        'es' => 'Efectivo',
        'tr' => 'Nakit',
        'ur' => 'نقد',
        'id' => 'Tunai',
        'ms' => 'Tunai'
    ],
    'online_payment' => [
        'ar' => 'الدفع عبر الإنترنت',
        'en' => 'Online Payment',
        'fr' => 'Paiement en ligne',
        'es' => 'Pago en línea',
        'tr' => 'Çevrimiçi Ödeme',
        'ur' => 'آن لائن ادائیگی',
        'id' => 'Pembayaran Online',
        'ms' => 'Pembayaran Dalam Talian'
    ],
    'payment_type' => [
        'ar' => 'نوع الدفع',
        'en' => 'Payment Type',
        'fr' => 'Type de paiement',
        'es' => 'Tipo de pago',
        'tr' => 'Ödeme Türü',
        'ur' => 'ادائیگی کی قسم',
        'id' => 'Jenis Pembayaran',
        'ms' => 'Jenis Pembayaran'
    ],
    'credit_card' => [
        'ar' => 'بطاقة ائتمان',
        'en' => 'Credit Card',
        'fr' => 'Carte de crédit',
        'es' => 'Tarjeta de crédito',
        'tr' => 'Kredi Kartı',
        'ur' => 'کریڈٹ کارڈ',
        'id' => 'Kartu Kredit',
        'ms' => 'Kad Kredit'
    ],
    'mobile_wallet' => [
        'ar' => 'محفظة الجوال',
        'en' => 'Mobile Wallet',
        'fr' => 'Portefeuille mobile',
        'es' => 'Monedero móvil',
        'tr' => 'Mobil Cüzdan',
        'ur' => 'موبائل والیٹ',
        'id' => 'Dompet Digital',
        'ms' => 'Dompet Mudah Alih'
    ],
    'wallet_phone_number' => [
        'ar' => 'رقم هاتف المحفظة',
        'en' => 'Wallet Phone Number',
        'fr' => 'Numéro de téléphone du portefeuille',
        'es' => 'Número de teléfono del monedero',
        'tr' => 'Cüzdan Telefon Numarası',
        'ur' => 'والیٹ فون نمبر',
        'id' => 'Nomor Telepon Dompet',
        'ms' => 'Nombor Telefon Dompet'
    ],
    'enter_wallet_phone' => [
        'ar' => 'أدخل رقم هاتف المحفظة',
        'en' => 'Enter wallet phone number',
        'fr' => 'Entrez le numéro de téléphone du portefeuille',
        'es' => 'Ingrese el número de teléfono del monedero',
        'tr' => 'Cüzdan telefon numarasını girin',
        'ur' => 'والیٹ فون نمبر درج کریں',
        'id' => 'Masukkan nomor telepon dompet',
        'ms' => 'Masukkan nombor telefon dompet'
    ],
    'wallet_phone_format_hint' => [
        'ar' => 'مثال: 01xxxxxxxxx',
        'en' => 'Example: 01xxxxxxxxx',
        'fr' => 'Exemple: 01xxxxxxxxx',
        'es' => 'Ejemplo: 01xxxxxxxxx',
        'tr' => 'Örnek: 01xxxxxxxxx',
        'ur' => 'مثال: 01xxxxxxxxx',
        'id' => 'Contoh: 01xxxxxxxxx',
        'ms' => 'Contoh: 01xxxxxxxxx'
    ],
    'total_amount' => [
        'ar' => 'المبلغ الإجمالي',
        'en' => 'Total Amount',
        'fr' => 'Montant total',
        'es' => 'Importe total',
        'tr' => 'Toplam Tutar',
        'ur' => 'کل رقم',
        'id' => 'Jumlah Total',
        'ms' => 'Jumlah Keseluruhan'
    ]
];

// Counter for tracking added translations
$added = 0;
$updated = 0;
$skipped = 0;

// Add translations for each language
foreach ($languages as $language) {
    $lang_code = $language['code'];
    
    echo "Processing translations for {$language['name']} ({$lang_code})...\n";
    
    // Process each translation
    foreach ($translations as $key => $translations_by_lang) {
        // Skip if translation for this language is not defined
        if (!isset($translations_by_lang[$lang_code])) {
            echo "  - Skipping '{$key}' for {$lang_code} (translation not defined)\n";
            $skipped++;
            continue;
        }
        
        $translation = $translations_by_lang[$lang_code];
        
        // Check if translation already exists
        $stmt = $pdo->prepare("SELECT * FROM translations WHERE language_code = ? AND translation_key = ?");
        $stmt->execute([$lang_code, $key]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing translation
            $stmt = $pdo->prepare("UPDATE translations SET translation_value = ? WHERE language_code = ? AND translation_key = ?");
            $stmt->execute([$translation, $lang_code, $key]);
            echo "  - Updated '{$key}' for {$lang_code}\n";
            $updated++;
        } else {
            // Add new translation
            $stmt = $pdo->prepare("INSERT INTO translations (language_code, translation_key, translation_value) VALUES (?, ?, ?)");
            $stmt->execute([$lang_code, $key, $translation]);
            echo "  - Added '{$key}' for {$lang_code}\n";
            $added++;
        }
    }
}

echo "\nTranslation update complete!\n";
echo "Added: {$added} translations\n";
echo "Updated: {$updated} translations\n";
echo "Skipped: {$skipped} translations\n";