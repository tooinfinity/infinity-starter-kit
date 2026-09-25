<?php

declare(strict_types=1);

return [
    'title' => 'Pistes d\'audit',
    'description' => 'Suivre et surveiller les événements administratifs et de sécurité.',

    'events' => [
        'user_created' => 'Utilisateur créé',
        'user_updated' => 'Utilisateur modifié',
        'user_activated' => 'Utilisateur activé',
        'user_deactivated' => 'Utilisateur désactivé',
        'user_deleted' => 'Utilisateur supprimé',
        'user_password_changed' => 'Mot de passe modifié',
        'settings_updated' => 'Paramètres modifiés',
    ],

    'filters' => [
        'all_events' => 'Tous les événements',
        'search_placeholder' => 'Rechercher par IP, URL ou utilisateur...',
        'date_from' => 'Date de début',
        'date_to' => 'Date de fin',
        'reset' => 'Réinitialiser les filtres',
    ],

    'table' => [
        'event' => 'Événement',
        'user' => 'Utilisateur',
        'auditable' => 'Cible',
        'ip_address' => 'Adresse IP',
        'date' => 'Date',
        'actions' => 'Actions',
        'system' => 'Système',
        'view_details' => 'Voir les détails',
        'empty' => 'Aucune piste d\'audit trouvée.',
    ],

    'detail' => [
        'title' => 'Détails de la piste d\'audit',
        'description' => 'Informations détaillées sur cet événement enregistré.',
        'event' => 'Événement',
        'actor' => 'Auteur',
        'target' => 'Cible',
        'ip_address' => 'Adresse IP',
        'user_agent' => 'Agent utilisateur',
        'url' => 'URL de la requête',
        'date' => 'Date et heure',
        'tags' => 'Étiquettes',
        'changes' => 'Modifications',
        'field' => 'Champ',
        'old_value' => 'Ancienne valeur',
        'new_value' => 'Nouvelle valeur',
        'no_changes' => 'Aucune modification enregistrée pour cet événement.',
        'close' => 'Fermer',
    ],
];
