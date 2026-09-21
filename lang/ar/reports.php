<?php

declare(strict_types=1);

/* @chisel-reporting */

return [
    'title' => 'التقارير',
    'description' => 'عرض تقارير وتحليلات النظام والعمليات الإدارية.',

    'categories' => [
        'users' => 'المستخدمون',
        'security' => 'الأمان وسجل التدقيق',
    ],

    'types' => [
        'user_activity' => [
            'title' => 'نشاط ونمو المستخدمين',
            'description' => 'تحليل تسجيلات المستخدمين وحالات النشاط واتجاهات الحسابات.',
        ],
        'audit_activity' => [
            'title' => 'نشاط سجل التدقيق',
            'description' => 'مراقبة الإجراءات الإدارية وأحداث الأمان وحجم التدقيق.',
        ],
    ],

    'metrics' => [
        'total_users' => 'إجمالي المستخدمين',
        'total_users_description' => 'جميع الحسابات المسجلة',
        'active_users' => 'المستخدمون النشطون',
        'active_users_description' => 'الحسابات المفعلة',
        'inactive_users' => 'المستخدمون غير النشطين',
        'inactive_users_description' => 'الحسابات المعطلة',
        'new_users_in_period' => 'التسجيلات الجديدة',
        'new_users_in_period_description' => 'المنشأة خلال الفترة المحددة',
        'total_audit_events' => 'إجمالي أحداث التدقيق',
        'total_audit_events_description' => 'المسجلة خلال الفترة المحددة',
        'unique_actors' => 'الفاعلون النشطون',
        'unique_actors_description' => 'المستخدمون الفعليون المحدثون للأحداث',
        'top_event' => 'الحدث الأكثر تكراراً',
        'top_resource' => 'المورد الأكثر استهدافاً',
    ],

    'common' => [
        'none' => 'لا يوجد',
        'occurrences' => 'حدث',
        'no_events_recorded' => 'لم تسجل أي أحداث',
        'no_resources_recorded' => 'لم تسجل أي موارد',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'system' => 'النظام',
        'export_csv' => 'تصدير كملف CSV',
        'reset' => 'إعادة تعيين',
        'filter' => 'تصفية',
        'search_placeholder' => 'بحث...',
        'date_from' => 'من تاريخ',
        'date_to' => 'إلى تاريخ',
        'status' => 'الحالة',
        'all' => 'الكل',
        'all_events' => 'جميع الأحداث',
        'view_report' => 'عرض التقرير',
        'presets' => [
            'last_7_days' => 'آخر 7 أيام',
            'last_30_days' => 'آخر 30 يوماً',
            'last_90_days' => 'آخر 90 يوماً',
            'last_365_days' => 'آخر 365 يوماً',
            'custom' => 'فترة مخصصة',
        ],
        'empty_title' => 'لم يتم العثور على بيانات',
        'empty_description' => 'لا توجد سجلات تطابق النطاق الزمني ومعايير التصفية المحددة.',
        'chart_view' => 'المخطط البياني',
        'table_view' => 'جدول البيانات',
        'distribution' => 'توزيع الأحداث',
    ],

    'export' => [
        'columns' => [
            'id' => 'المعرف',
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'status' => 'الحالة',
            'roles' => 'الأدوار',
            'event' => 'الحدث',
            'actor_name' => 'اسم الفاعل',
            'actor_email' => 'بريد الفاعل',
            'target_type' => 'نوع المورد',
            'target_id' => 'معرف المورد',
            'ip_address' => 'عنوان IP',
            'created_at' => 'الوقت والتاريخ',
        ],
    ],
];

/* @end-chisel-reporting */
