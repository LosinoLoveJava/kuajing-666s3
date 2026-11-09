<?php

return [
    'domain' => env('PORTAL_DOMAIN', 'pddglobalmerchanthub.com'),
    'aliases' => array_filter(array_map('trim', explode(',', env('PORTAL_DOMAIN_ALIASES', 'pddsellercenter.ae,pddsellercenter.com')))),
];
