<?php

return [

    // The default gateway to use
    'default' => 'custom',

    // Add in each gateway here
    'gateways' => [
        'paypal' => [
            'driver'  => 'PayPal_Express',
            'options' => [
                'solutionType'   => '',
                'landingPage'    => '',
                'headerImageUrl' => ''
            ]
        ],
        'custom' => [
            'driver'  => 'Custom',
            'options' => [
                'solutionType'   => '',
                'landingPage'    => '',
                'headerImageUrl' => ''
            ]
        ]
    ]

];
