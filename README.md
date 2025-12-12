# IP Filter Laravel

The free Laravel package to help you filter your users ip with multiple services

## Use Cases

- Perform a powerful checking for user ip before approving registration or invoice making.
- Parse result from validator
- Example use case

## Features

- Get information from ip-api.com
- Check blacklist from barracudacentral, getipintel, easydmarc, valli, uceprotect, projecthoneypot, team-cymru, fortiguard, talosintelligence, scamalytics
- Check quality and e-commerce fraud from maxmind, apivoid, ipqualityscore, cleantalk, iphub
- Easy to validate with a simple line code

## Requirements

- **PHP**: 8.1 or higher
- **Laravel** 9.0 or higher

## Quick Start

If you prefer to install this package into your own Laravel application, please follow the installation steps below

## Installation

#### Step 1. Install a Laravel project if you don't have one already

https://laravel.com/docs/installation

#### Step 2. Require the current package using composer:

```bash
composer require funnydevjsc/laravel-ip-filter
```

#### Step 3. Publish the controller file and config file

```bash
php artisan vendor:publish --provider="FunnyDev\IPFilter\IPFilterServiceProvider" --tag="ip-filter"
```

If publishing files fails, please create corresponding files at the path `config/ip-filter.php` and `app\Http\Controllers\IPFilterControllers.php` from this package. And you can also further customize the IPFilterControllers.php file to suit your project.

#### Step 4. Update the various config settings in the published config file:

After publishing the package assets a configuration file will be located at <code>config/ip-filter.php</code>.

<!--- ## Usage --->

## Testing

``` php
<?php

namespace App\Console\Commands;

use FunnyDev\IPFilter\IPFilterSdk;
use Illuminate\Console\Command;

class IpFilterTestCommand extends Command
{
    protected $signature = 'ip-filter:test';

    protected $description = 'Test Ip Filter SDK';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $instance = new IPFilterSdk();
        
        // Perform checking with fast mode turned on and only use $result['recommended'] as signal (true/false)
        $result = $instance->validate(ip: '127.0.0.1', fast: true, score: false);
        
        // Perform a full checking
        $result = $instance->validate(ip: '127.0.0.1', fast: false, score: true);
        
        // Explanation of results
        $result = [
            'query' => $ip,
            'recommend' => true, // Recommended value of whether to accept this ip or not
            'reason' => '', // Reason why the ip is not recommended
            'trustable' => [
                'exist' => true, // Does the ip exist
                'disposable' => false, // Is the ip spam
                'blacklist' => 0, // Percentage of blacklists as a float
                'fraud_score' => 0, // Fraud score on a 100-point scale
                'suspicious' => false, // Is the ip suspicious of maliciousness
                'high_risk' => false, // Is the ip considered high risk of payment
                'domain_type' => 'popular',
                'domain_trust' => true, // Is the domain name trustworthy?
                'domain_age' => '',
                'dns_valid' => false, // Does DNS match between domain name and SMTP server?
                'username' => true // Is the ip address username trustworthy?
            ]
        ];
    }
}
```

## Feedback

Respect us in the [Laravel Việt Nam](https://www.facebook.com/groups/167363136987053)

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security-related issues, please ip contact@funnydev.vn or use the issue tracker.

## Credits

- [Funny Dev., Jsc](https://github.com/funnydevjsc)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
