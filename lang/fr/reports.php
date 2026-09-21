<?php

declare(strict_types=1);

/* @chisel-reporting */

return [
    'title' => 'Rapports',
    'description' => 'Consultez les rapports et analyses système et administratifs.',

    'categories' => [
        'users' => 'Utilisateurs',
        'security' => 'Sécurité et Audit',
    ],

    'types' => [
        'user_activity' => [
            'title' => 'Activité et croissance des utilisateurs',
            'description' => 'Analysez les inscriptions, statuts et tendances des comptes utilisateurs.',
        ],
        'audit_activity' => [
            'title' => 'Activité du journal d’audit',
            'description' => 'Surveillez les actions administratives, événements de sécurité et volumes d’audit.',
        ],
    ],

    'metrics' => [
        'total_users' => 'Total des utilisateurs',
        'total_users_description' => 'Tous les comptes enregistrés',
        'active_users' => 'Utilisateurs actifs',
        'active_users_description' => 'Comptes activés',
        'inactive_users' => 'Utilisateurs inactifs',
        'inactive_users_description' => 'Comptes désactivés',
        'new_users_in_period' => 'Nouvelles inscriptions',
        'new_users_in_period_description' => 'Créés durant la période sélectionnée',
        'total_audit_events' => 'Événements d’audit totaux',
        'total_audit_events_description' => 'Enregistrés durant la période',
        'unique_actors' => 'Acteurs actifs',
        'unique_actors_description' => 'Utilisateurs uniques ayant déclenché des événements',
        'top_event' => 'Événement principal',
        'top_resource' => 'Ressource principale',
    ],

    'common' => [
        'none' => 'Aucun',
        'occurrences' => 'événements',
        'no_events_recorded' => 'Aucun événement enregistré',
        'no_resources_recorded' => 'Aucune ressource enregistrée',
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'system' => 'Système',
        'export_csv' => 'Exporter en CSV',
        'reset' => 'Réinitialiser',
        'filter' => 'Filtrer',
        'search_placeholder' => 'Rechercher...',
        'date_from' => 'Date de début',
        'date_to' => 'Date de fin',
        'status' => 'Statut',
        'all' => 'Tous',
        'all_events' => 'Tous les événements',
        'view_report' => 'Consulter le rapport',
        'presets' => [
            'last_7_days' => '7 derniers jours',
            'last_30_days' => '30 derniers jours',
            'last_90_days' => '90 derniers jours',
            'last_365_days' => '365 derniers jours',
            'custom' => 'Période personnalisée',
        ],
        'empty_title' => 'Aucune donnée trouvée',
        'empty_description' => 'Aucun enregistrement ne correspond aux critères sélectionnés.',
        'chart_view' => 'Tendance visuelle',
        'table_view' => 'Tableau de données',
        'distribution' => 'Répartition des événements',
    ],

    'export' => [
        'columns' => [
            'id' => 'Identifiant',
            'name' => 'Nom',
            'email' => 'E-mail',
            'status' => 'Statut',
            'roles' => 'Rôles',
            'event' => 'Événement',
            'actor_name' => 'Nom de l’acteur',
            'actor_email' => 'E-mail de l’acteur',
            'target_type' => 'Type de ressource',
            'target_id' => 'ID de ressource',
            'ip_address' => 'Adresse IP',
            'created_at' => 'Horodatage',
        ],
    ],
];

/* @end-chisel-reporting */
