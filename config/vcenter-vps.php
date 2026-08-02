<?php

return [
    'host'              => env('VCENTER_HOST'),
    'user'              => env('VCENTER_USER'),
    'password'          => env('VCENTER_PASSWORD'),
    'insecure'          => (bool) env('VCENTER_INSECURE', false),
    'upload_library_id' => env('VCENTER_UPLOAD_LIBRARY_ID'),
    'iso_datastore_id'  => env('VCENTER_ISO_DATASTORE_ID'),

    /*
     * The billing app, which used to be a plugin in this same panel and is now a
     * separate service.
     *
     * These credentials are for the outbound direction only — pack lookups for
     * the admin form and audit events. The inbound direction (billing telling
     * the panel to provision or terminate) is authenticated the way every other
     * billing→panel call is: an application API key scoped to the `vps` ACL
     * resource. There is no shared secret to configure for it.
     */
    'billing' => [
        'url'     => env('VCENTER_BILLING_URL'),
        'token'   => env('VCENTER_BILLING_TOKEN'),
        'timeout' => (int) env('VCENTER_BILLING_TIMEOUT', 10),
    ],
];
