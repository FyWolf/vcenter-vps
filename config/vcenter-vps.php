<?php

return [
    'host'              => env('VCENTER_HOST'),
    'user'              => env('VCENTER_USER'),
    'password'          => env('VCENTER_PASSWORD'),
    'insecure'          => (bool) env('VCENTER_INSECURE', false),
    'upload_library_id' => env('VCENTER_UPLOAD_LIBRARY_ID'),
    'iso_datastore_id'  => env('VCENTER_ISO_DATASTORE_ID'),
];
