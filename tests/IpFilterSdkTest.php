<?php

namespace FunnyDev\IpFilter\Tests;

use FunnyDev\IpFilter\IpFilterSdk;
use PHPUnit\Framework\TestCase;

class IpFilterSdkTest extends TestCase
{
    public function test_fetch_value_basic(): void
    {
        $credentials = [
            'stub' => true,
            'ip-api' => '',
            'barracudacentral' => 'ON',
            'getipintel' => 'ON',
            'easydmarc' => 'ON',
            'valli' => 'ON',
            'uceprotect' => 'ON',
            'projecthoneypot' => 'ON',
            'team-cymru' => 'ON',
            'fortiguard' => 'ON',
            'talosintelligence' => 'ON',
            'scamalytics' => 'ON',
            'maxmind' => [
                'account' => '',
                'license' => '',
            ],
            'cleantalk' => '',
            'apivoid' => '',
            'ipqualityscore' => ''
        ];
        $sdk = new IpFilterSdk($credentials);

        $input = 'Hello [World]!';
        $this->assertSame('World', $sdk->fetch_value($input, '[', ']'));

        // Without end sentinel returns rest of string after start
        $this->assertSame('World]!', $sdk->fetch_value($input, '[', ''));

        // No start provided returns input (or until end if provided)
        $this->assertSame('Hello [World', $sdk->fetch_value($input, '', ']'));

        // Start not found returns empty string
        $this->assertSame('', $sdk->fetch_value($input, '{', '}'));

        // Empty input returns empty string
        $this->assertSame('', $sdk->fetch_value('', '[', ']'));
    }

    public function test_convert_array_handles_various_inputs(): void
    {
        $credentials = [
            'stub' => true,
            'ip-api' => '',
            'barracudacentral' => 'ON',
            'getipintel' => 'ON',
            'easydmarc' => 'ON',
            'valli' => 'ON',
            'uceprotect' => 'ON',
            'projecthoneypot' => 'ON',
            'team-cymru' => 'ON',
            'fortiguard' => 'ON',
            'talosintelligence' => 'ON',
            'scamalytics' => 'ON',
            'maxmind' => [
                'account' => '',
                'license' => '',
            ],
            'cleantalk' => '',
            'apivoid' => '',
            'ipqualityscore' => ''
        ];
        $sdk = new IpFilterSdk($credentials);

        // Empty and null
        $this->assertSame([], $sdk->convert_array(null));
        $this->assertSame([], $sdk->convert_array([]));

        // Already array
        $arr = ['a' => 1];
        $this->assertSame($arr, $sdk->convert_array($arr));

        // JSON string
        $json = '{"a":1,"b":[2,3]}';
        $this->assertSame(['a' => 1, 'b' => [2, 3]], $sdk->convert_array($json));

        // Non-JSON string returns []
        $this->assertSame([], $sdk->convert_array('not-json'));

        // Object converted to array
        $obj = (object) ['x' => 10, 'y' => (object)['z' => 20]];
        $this->assertSame(['x' => 10, 'y' => ['z' => 20]], $sdk->convert_array($obj));
    }

    public function test_init_result_structure_defaults(): void
    {
        $credentials = [
            'stub' => true,
            'ip-api' => '',
            'barracudacentral' => 'ON',
            'getipintel' => 'ON',
            'easydmarc' => 'ON',
            'valli' => 'ON',
            'uceprotect' => 'ON',
            'projecthoneypot' => 'ON',
            'team-cymru' => 'ON',
            'fortiguard' => 'ON',
            'talosintelligence' => 'ON',
            'scamalytics' => 'ON',
            'maxmind' => [
                'account' => '',
                'license' => '',
            ],
            'cleantalk' => '',
            'apivoid' => '',
            'ipqualityscore' => ''
        ];
        $sdk = new IpFilterSdk($credentials);
        $ip = '137.184.121.249';
        $result = $sdk->init_result($ip);

        $this->assertSame($ip, $result['query']);
        $this->assertIsBool($result['recommend']);
        $this->assertIsString($result['reason']);

        $this->assertArrayHasKey('trustable', $result);
        $this->assertArrayHasKey('mobile', $result['trustable']);
        $this->assertArrayHasKey('proxy', $result['trustable']);
        $this->assertArrayHasKey('hosting', $result['trustable']);
        $this->assertArrayHasKey('botnet', $result['trustable']);
        $this->assertArrayHasKey('total_server', $result['trustable']);
        $this->assertArrayHasKey('blacklist', $result['trustable']);
        $this->assertArrayHasKey('fraud_score', $result['trustable']);
        $this->assertArrayHasKey('reputation', $result['trustable']);
        $this->assertArrayHasKey('spam_ip', $result['trustable']);

        $this->assertArrayHasKey('location', $result);
        $this->assertArrayHasKey('country', $result['location']);
        $this->assertArrayHasKey('countryCode', $result['location']);
        $this->assertArrayHasKey('region', $result['location']);
        $this->assertArrayHasKey('regionName', $result['location']);
        $this->assertArrayHasKey('city', $result['location']);
        $this->assertArrayHasKey('zip', $result['location']);
        $this->assertArrayHasKey('lat', $result['location']);
        $this->assertArrayHasKey('lon', $result['location']);
        $this->assertArrayHasKey('timezone', $result['location']);

        $this->assertArrayHasKey('dns', $result);
        $this->assertArrayHasKey('isp', $result['dns']);
        $this->assertArrayHasKey('org', $result['dns']);
        $this->assertArrayHasKey('as', $result['dns']);
        $this->assertArrayHasKey('asname', $result['dns']);
    }

    public function test_validate_returns_error_for_invalid_ipv4_fast(): void
    {
        $credentials = [
            'stub' => true,
            'ip-api' => '',
            'barracudacentral' => 'ON',
            'getipintel' => 'ON',
            'easydmarc' => 'ON',
            'valli' => 'ON',
            'uceprotect' => 'ON',
            'projecthoneypot' => 'ON',
            'team-cymru' => 'ON',
            'fortiguard' => 'ON',
            'talosintelligence' => 'ON',
            'scamalytics' => 'ON',
            'maxmind' => [
                'account' => '',
                'license' => '',
            ],
            'cleantalk' => '',
            'apivoid' => '',
            'ipqualityscore' => ''
        ];
        $sdk = new IpFilterSdk($credentials);
        $result = $sdk->validate('not-an-ip', true, false);

        $this->assertFalse($result['recommend']);
        $this->assertSame('Invalid ip format', $result['reason']);
        $this->assertSame('not-an-ip', $result['query']);
    }
}
