<?php

return [
    'client' => [
        'hosts' => [env('OPEN_SEARCH_HOST') . ':443'],
        'BasicAuthentication' => [
            'username' => env('OPEN_SEARCH_USERNAME'),
            'password' => env('OPEN_SEARCH_PASSWORD'),
        ],
        'SSLVerification' => true,
    ],
//    'indices' => [
//        'default' => [
//            'settings' => [
//                'index' => [
//                    'number_of_shards' => 3,
//                ],
//            ],
//        ],
//        'table' => [
//            'mappings' => [
//                'properties' => [
//                    'id' => [
//                        'type' => 'keyword',
//                    ],
//                ],
//            ],
//        ],
//    ],
];
