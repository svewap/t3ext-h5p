<?php
return [
    'frontend' => [
        'michielroos/h5p' => [
            'target' => \MichielRoos\H5p\Middleware\H5pViewer::class,
            'after' => [
                #'typo3/cms-frontend/maintenance-mode'
                'typo3/cms-frontend/static-route-resolver'
            ],
            'before' => [
                #'typo3/cms-frontend/authentication'
                'typo3/cms-frontend/page-resolver'
            ]
        ],
        'michielroos/h5p-route-resolver' => [
            'target' => \MichielRoos\H5p\Middleware\H5pRouteResolver::class,
            'after' => [
                'typo3/cms-frontend/maintenance-mode'
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
                'typo3/cms-frontend/static-route-resolver',
            ]
        ],
    ]
];
