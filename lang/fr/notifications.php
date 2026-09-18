<?php

declare(strict_types=1);

return [
    'title' => 'Notifications',
    'empty' => 'Aucune notification pour le moment',
    'all_marked_as_read' => 'Toutes les notifications ont été marquées comme lues.',
    'deleted' => 'Notification supprimée.',
    'preferences_updated' => 'Préférences de notification mises à jour avec succès.',

    'types' => [
        'security' => 'Sécurité',
        'user_management' => 'Gestion des utilisateurs',
        'system' => 'Système',
    ],

    'password_changed' => [
        'title' => 'Mot de passe modifié',
        'body' => 'Votre mot de passe a été modifié récemment. Si vous n\'êtes pas à l\'origine de ce changement, veuillez contacter le support immédiatement.',
    ],

    'user_activated' => [
        'title' => 'Compte activé',
        'body' => 'Votre compte a été activé par un administrateur. Vous avez désormais un accès complet au système.',
    ],

    'user_deactivated' => [
        'title' => 'Compte désactivé',
        'body' => 'Votre compte a été désactivé par un administrateur. Veuillez contacter le support si vous pensez qu\'il s\'agit d\'une erreur.',
    ],
];
