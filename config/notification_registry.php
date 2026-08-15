<?php

/**
 * What each notification is, in one place.
 *
 * Previously this mapped a key onto template NAMES held by an external
 * notification service — the app sent that service a name plus a data bag and
 * the service owned the wording. Mail is now rendered by this application, so a
 * template names a Blade view and a subject translation key instead.
 *
 * `required_data` is unchanged in meaning and still validated before anything is
 * sent: a template rendered with a missing field produces a mail with a hole in
 * it, and the recipient is the one who discovers it.
 *
 * The email body itself is bilingual inside the Blade view, driven by the
 * `language` the caller passes, so there is one view per notification rather
 * than one per language.
 */
return [
    NOTIFICATION_TEMPLATE_KEY_USER_REGISTRATION => [
        NOTIFICATION_TEMPLATE_REQUIRED_DATA_KEY => ['name', 'full_name', 'email', 'password'],
        NOTIFICATION_TEMPLATE_VIEW_KEY => 'emails.user-credentials',
        NOTIFICATION_TEMPLATE_SUBJECT_KEY => 'email_subject_user_credentials',
    ],

    NOTIFICATION_TEMPLATE_KEY_OTP => [
        NOTIFICATION_TEMPLATE_REQUIRED_DATA_KEY => ['name', 'otp', 'message', 'time'],
        NOTIFICATION_TEMPLATE_VIEW_KEY => 'emails.otp',
        NOTIFICATION_TEMPLATE_SUBJECT_KEY => 'email_subject_otp',
    ],
];
