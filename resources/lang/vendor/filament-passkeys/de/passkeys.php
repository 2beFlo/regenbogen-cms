<?php

return [
    'provider' => [
        'label' => 'Passkey',
    ],

    'login' => [
        'button' => 'Mit Passkey anmelden',
        'heading' => 'Mit Passkey anmelden',
        'description' => 'Nutze Fingerabdruck, Gesichtserkennung, Geräte-PIN oder einen Sicherheitsschlüssel.',
        'authenticating' => 'Passkey wird geprüft …',
        'success' => 'Anmeldung mit Passkey erfolgreich.',
        'error' => 'Die Anmeldung mit Passkey ist fehlgeschlagen. Bitte versuche es erneut.',
        'failed' => 'Die Anmeldung mit Passkey ist fehlgeschlagen. Bitte versuche es erneut.',
    ],

    'management' => [
        'heading' => 'Passkeys',
        'description' => 'Mit einem Passkey meldest du dich mit Fingerabdruck, Gesichtserkennung, Geräte-PIN oder einem Sicherheitsschlüssel an – ohne ein zusätzliches Passwort eintippen zu müssen.',
        'enabled' => 'Eingerichtet',
        'waiting' => 'Warte auf Bestätigung …',
        'rename' => 'Umbenennen',
        'renamed' => 'Passkey wurde umbenannt.',
        'disabled' => 'Noch nicht eingerichtet',
        'empty' => 'Für dieses Konto ist noch kein Passkey hinterlegt.',
        'add' => 'Passkey hinzufügen',
        'default_name' => 'Passkey',
        'never_used' => 'Noch nie verwendet',
        'columns' => [
            'name' => 'Name',
            'last_used' => 'Zuletzt verwendet',
            'created_at' => 'Hinzugefügt am',
            'actions' => 'Aktionen',
        ],
        'actions' => [
            'edit' => 'Umbenennen',
            'delete' => 'Entfernen',
        ],
        'name' => 'Name',
        'created_at' => 'Hinzugefügt am',
        'last_used' => 'Zuletzt verwendet',
        'delete' => 'Entfernen',
        'delete_confirmation' => 'Diesen Passkey wirklich entfernen?',
        'registered' => 'Passkey wurde eingerichtet.',
        'deleted' => 'Passkey wurde entfernt.',
    ],

    'register' => [
        'name_label' => 'Name des Passkeys',
    ],

    'registration' => [
        'heading' => 'Passkey hinzufügen',
        'description' => 'Folge anschließend der Sicherheitsabfrage deines Geräts oder Browsers.',
        'name' => 'Name für diesen Passkey',
        'name_placeholder' => 'z. B. MacBook, iPhone oder Sicherheitsschlüssel',
        'submit' => 'Passkey einrichten',
        'success' => 'Passkey wurde eingerichtet.',
        'error' => 'Der Passkey konnte nicht eingerichtet werden. Bitte versuche es erneut.',
    ],

    'authentication' => [
        'heading' => 'Mit Passkey anmelden',
        'button' => 'Mit Passkey anmelden',
        'description' => 'Nutze Fingerabdruck, Gesichtserkennung, Geräte-PIN oder einen Sicherheitsschlüssel.',
        'success' => 'Passkey bestätigt.',
        'error' => 'Die Anmeldung mit Passkey ist fehlgeschlagen. Bitte versuche es erneut.',
    ],

    'challenge' => [
        'unsupported' => 'Dieser Browser oder dieses Gerät unterstützt Passkeys nicht.',
        'failed' => 'Der Passkey konnte nicht bestätigt werden. Bitte versuche es erneut.',
        'callout' => [
            'heading' => 'Passkey bestätigen',
            'description' => 'Bestätige die Anmeldung mit deinem Passkey auf diesem Gerät oder einem verbundenen Sicherheitsschlüssel.',
            'waiting' => 'Warte auf die Bestätigung deines Geräts …',
        ],
        'heading' => 'Passkey bestätigen',
        'description' => 'Bestätige die Anmeldung mit dem Passkey auf deinem Gerät.',
        'button' => 'Passkey verwenden',
        'error' => 'Der Passkey konnte nicht bestätigt werden. Bitte versuche es erneut.',
    ],
];
