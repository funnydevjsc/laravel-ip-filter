<?php

return [
    'credentials' => [
        'ip-api' => env('IP_FILTER_IP_API', ''),
        'barracudacentral' => env('IP_FILTER_BARRACUDACENTRAL', 'ON'),
        'getipintel' => env('IP_FILTER_GETIPINTEL', 'ON'),
        'easydmarc' => env('IP_FILTER_EASYDMARC', 'ON'),
        'valli' => env('IP_FILTER_VALLI', 'ON'),
        'uceprotect' => env('IP_FILTER_UCEPROTECT', 'ON'),
        'projecthoneypot' => env('IP_FILTER_PROJECTHONEYPOT', 'ON'),
        'team-cymru' => env('IP_FILTER_TALOSINTELLIGENCE', 'ON'),
        'fortiguard' => env('IP_FILTER_SCAMALYTICS', 'ON'),
        'talosintelligence' => env('IP_FILTER_FORTIGUARD', 'ON'),
        'scamalytics' => env('IP_FILTER_FORTIGUARD', 'ON'),
        'maxmind' => [
            'account' => env('IP_FILTER_MAXMIND_ACCOUNT', ''),
            'license' => env('IP_FILTER_MAXMIND_LICENSE', ''),
        ],
        'cleantalk' => env('IP_FILTER_CLEANTALK_KEY', ''),
        'apivoid' => env('IP_FILTER_APIVOID_KEY', ''),
        'ipqualityscore' => env('IP_FILTER_IPQUALITYSCORE_KEY', '')
    ]
];