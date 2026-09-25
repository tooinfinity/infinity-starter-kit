<?php

declare(strict_types=1);

return [
    'title' => 'سجلات التدقيق',
    'description' => 'تتبع ومراقبة الأحداث الإدارية والأمنية.',

    'events' => [
        'user_created' => 'تم إنشاء المستخدم',
        'user_updated' => 'تم تحديث المستخدم',
        'user_activated' => 'تم تفعيل المستخدم',
        'user_deactivated' => 'تم تعطيل المستخدم',
        'user_deleted' => 'تم حذف المستخدم',
        'user_password_changed' => 'تم تغيير كلمة المرور',
        'settings_updated' => 'تم تحديث الإعدادات',
    ],

    'filters' => [
        'all_events' => 'جميع الأحداث',
        'search_placeholder' => 'البحث حسب عنوان IP أو الرابط أو المستخدم...',
        'date_from' => 'من تاريخ',
        'date_to' => 'إلى تاريخ',
        'reset' => 'إعادة تعيين الفلاتر',
    ],

    'table' => [
        'event' => 'الحدث',
        'user' => 'المستخدم',
        'auditable' => 'الهدف',
        'ip_address' => 'عنوان IP',
        'date' => 'التاريخ',
        'actions' => 'الإجراءات',
        'system' => 'النظام',
        'view_details' => 'عرض التفاصيل',
        'empty' => 'لم يتم العثور على سجلات تدقيق.',
    ],

    'detail' => [
        'title' => 'تفاصيل سجل التدقيق',
        'description' => 'معلومات مفصلة حول هذا الحدث المسجل.',
        'event' => 'الحدث',
        'actor' => 'الفاعل',
        'target' => 'الهدف',
        'ip_address' => 'عنوان IP',
        'user_agent' => 'وكيل المستخدم',
        'url' => 'رابط الطلب',
        'date' => 'الوقت والتاريخ',
        'tags' => 'الوسوم',
        'changes' => 'التغييرات',
        'field' => 'الحقل',
        'old_value' => 'القيمة القديمة',
        'new_value' => 'القيمة الجديدة',
        'no_changes' => 'لا توجد تغييرات مسجلة لهذا الحدث.',
        'close' => 'إغلاق',
    ],
];
