<?php

namespace FunnyDev\IpFilter;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use MaxMind\MinFraud;

class IpFilterSdk
{
    private array $credentials;

    public function __construct(array $credentials = [])
    {
        if (empty($credentials)) {
            $this->credentials = $this->getConfigValue($credentials, 'credentials');
        } else {
            $this->credentials = $credentials;
        }
    }

    private function getConfigValue($value, $configKey) {
        return $value ? $value : Config::get('ip-filter.'.$configKey);
    }

    public function fetch_value(string $input = '', string $start = '', string $end = ''): string
    {
        if (! $input) {
            return '';
        }
        if ($start) {
            $s_start = stripos($input, $start);
            if ($s_start === false) {
                return '';
            }
            $length = strlen($start);
            $str = substr($input, $s_start + $length);
        } else {
            $str = $input;
        }
        if (! $end) {
            return $str;
        }
        if (stripos($str, $end) === false) {
            return $str;
        }

        return substr($str, 0, stripos($str, $end));
    }

    public function convert_array($data): array
    {
        // Fast, safe conversions without double encoding
        if (empty($data)) {
            return [];
        }
        if (is_array($data)) {
            return $data;
        }
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }
        // Attempt to cast simple objects
        if (is_object($data)) {
            return json_decode(json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR), true) ?? [];
        }
        return [];
    }

    public function request(string $method = 'GET', string $url = '', array $param = [], string $response = 'body', bool $verify = false, array $header = ['Connection' => 'keep-alive', 'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36'], array $authentication = [], array $options = [], string $proxy = ''): array|string
    {
        if ((! $url) || (! $response)) {
            return '';
        }
        // Merge options, prefer caller-provided values
        $option = ['verify' => $verify];
        if ($proxy) {
            // Preserve provided scheme if present
            if (! Str::startsWith($proxy, ['http://', 'https://'])) {
                $proxy = 'http://' . $proxy;
            }
            $option['proxy'] = $proxy;
        }
        $options = $options + $option;
        if (! $header) {
            $header = ['Connection' => 'keep-alive', 'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36'];
        }
        $timeout = (int) ($options['timeout'] ?? 10);
        $instance = Http::withHeaders($header)
            ->timeout($timeout)
            ->withOptions($options);
        if (!empty($authentication['username']) && !empty($authentication['password'])) {
            $instance = $instance->withBasicAuth($authentication['username'], $authentication['password']);
        }
        if ($method === 'GET') {
            $res = $instance->get($url);
        } else {
            $res = $instance->post($url, $param);
        }
        if ($response === 'json') {
            try {
                $json = $res->json();
            } catch (Exception) {
                $json = null;
            }
            return is_array($json) ? $json : $this->convert_array($res->body());
        }

        return $res->body();
    }

    public function init_result(string $ip): array
    {
        return [
            'query' => $ip,
            'recommend' => true,
            'reason' => '',
            'trustable' => [
                'mobile' => false,
                'proxy' => false,
                'hosting' => false,
                'botnet' => false,
                'total_server' => 0,
                'blacklist' => -1,
                'fraud_score' => -1,
                'reputation' => 'Unknown',
                'spam_ip' => false,
            ],
            'location' => [
                'country' => 'Unknown',
                'countryCode' => 'Unknown',
                'region' => 'Unknown',
                'regionName' => 'Unknown',
                'city' => 'Unknown',
                'zip' => 'Unknown',
                'lat' => 'Unknown',
                'lon' => 'Unknown',
                'timezone' => 'Unknown',
            ],
            'dns' => [
                'isp' => 'Unknown',
                'org' => 'Unknown',
                'as' => 'Unknown',
                'asname' => 'Unknown',
            ],
        ];
    }

    public function handle(string $ip, bool $fast = true, bool $score = false): array
    {
        // Force ip lowercase per policy
        $ip = strtolower(trim($ip));
        $result = $this->init_result($ip);

        // Basic format validation first
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $result['recommend'] = false;
            $result['reason'] = 'Invalid ip format';
            if ($fast) return $result;
        }

        // Perform default information checking from IP-API
        try {
            $ip_api_key = $this->credentials['ip-api'] ?? '';
            if (!empty($ip_api_key)) {
                $url = 'https://pro.ip-api.com/json/' . $ip . '?fields=status,message,continent,continentCode,country,countryCode,countryCode3,region,regionName,city,district,zip,lat,lon,timezone,offset,currentTime,currency,callingCode,isp,org,as,asname,reverse,mobile,proxy,hosting,query&key='.$ip_api_key;
            } else {
                $url = 'http://ip-api.com/json/' . $ip . '?fields=status,message,continent,continentCode,country,countryCode,countryCode3,region,regionName,city,district,zip,lat,lon,timezone,offset,currentTime,currency,callingCode,isp,org,as,asname,reverse,mobile,proxy,hosting,query';
            }
            $ip_api = $this->request('GET', $url, [], 'json', false, [], [], ['timeout' => $fast ? 3 : 10]);
            if ($ip_api['status'] === 'success') {
                $result['location']['country'] = $ip_api['country'];
                $result['location']['countryCode'] = $ip_api['countryCode'];
                $result['location']['region'] = $ip_api['region'];
                $result['location']['regionName'] = $ip_api['regionName'];
                $result['location']['city'] = $ip_api['city'];
                $result['location']['zip'] = $ip_api['zip'];
                $result['location']['lat'] = $ip_api['lat'];
                $result['location']['lon'] = $ip_api['lon'];
                $result['location']['timezone'] = $ip_api['timezone'];

                if ($ip_api['mobile']) {
                    $result['trustable']['mobile'] = true;
                }

                if ($ip_api['proxy'] || $ip_api['hosting']) {
                    $result['trustable']['proxy'] = true;
                }
                if ($fast && ($result['trustable']['proxy'])) {
                    $result['reason'] = 'This ip was marked as proxy';
                    $result['recommend'] = false;
                    return $result;
                }
            }
        } catch (Exception) {}

        if ($score) {// Perform quality checking from Maxmind
            try {
                $mmAccount = $this->credentials['maxmind']['account'] ?? null;
                $mmLicense = $this->credentials['maxmind']['license'] ?? null;
                if ($mmAccount && $mmLicense) {
                    $mindfraud = new MinFraud($mmAccount, $mmLicense);
                    $response = $mindfraud->withDevice([
                        'ip' => $ip,
                    ])->score();

                    $maxmind = $this->convert_array($response);

                    if ($maxmind['ip_address']['risk']) {
                        $result['trustable']['fraud_score'] = max($result['trustable']['fraud_score'], round($maxmind['ip_address']['risk']));
                    }
                }
            } catch (Exception) {}
        }

        $black = 0;
        $total = 0;

        // Perform quality checking from apivoid
        try {
            if (!empty($this->credentials['apivoid'])) {
                $apivoid = $this->request('GET', 'https://endpoint.apivoid.com/ipverify/v1/pay-as-you-go/?key=' . $this->credentials['apivoid'] . '&ip=' . urlencode($ip), [], 'json', false, [], [], ['timeout' => $fast ? 3 : 10]);

                $black += $apivoid['data']['report']['blacklists']['detections'];
                $total += $apivoid['data']['report']['blacklists']['engines_count'];
                if ($result['trustable']['fraud_score'] < $apivoid['data']['report']['risk_score']['result']) {
                    $result['trustable']['fraud_score'] = $apivoid['data']['report']['risk_score']['result'];
                }

                if ($fast && $score && ($result['trustable']['fraud_score'] >= 75)) {
                    $result['reason'] = 'This ip was marked as fraudulent';
                    $result['recommend'] = false;
                    return $result;
                }

                if (! $result['trustable']['proxy']) {
                    if ($apivoid['data']['report']['anonymity']['is_proxy']) {
                        $result['trustable']['proxy'] = true;
                    } elseif ($apivoid['data']['report']['anonymity']['is_webproxy']) {
                        $result['trustable']['proxy'] = true;
                    } elseif ($apivoid['data']['report']['anonymity']['is_tor']) {
                        $result['trustable']['proxy'] = true;
                    }
                }

                if ($result['trustable']['proxy'] && $fast) {
                    $result['reason'] = 'This ip was marked as proxy';
                    $result['recommend'] = false;
                    return $result;
                }

                if (! $result['trustable']['hosting']) {
                    if ($apivoid['data']['report']['anonymity']['is_vpn']) {
                        $result['trustable']['hosting'] = true;
                    } elseif ($apivoid['data']['report']['anonymity']['is_hosting']) {
                        $result['trustable']['hosting'] = true;
                    }
                }

                if ($result['trustable']['hosting'] && $fast) {
                    $result['reason'] = 'This ip was marked as hosting';
                    $result['recommend'] = false;
                    return $result;
                }
            }
        } catch (Exception) {}

        // Check blacklist from barracudacentral
        try {
            if (!empty($this->credentials['barracudacentral']) && (strtolower($this->credentials['barracudacentral']) !== 'off')) {
                $response = $this->request('GET', 'https://www.barracudacentral.org/lookups/lookup-reputation');
                $cid = $this->fetch_value($response, '<input type="hidden" name="cid" value="', '"');
                $param = [
                    'lookup_entry' => $ip,
                    'cid' => $cid,
                    'submit' => 'Check Reputation',
                ];
                $response = $this->request('POST', 'https://www.barracudacentral.org/lookups/lookup-reputation', $param);
                if (strpos($response, 'failure-message') > 0) {
                    $black += 1;
                }
                $total += 1;
            }
        } catch (Exception) {}

        // Check blacklist from getipintel
        try {
            if (!empty($this->credentials['getipintel']) && (strtolower($this->credentials['getipintel']) !== 'off')) {
                $response = $this->request('GET', 'https://check.getipintel.net/check.php?ip=' . $ip . '&contact=ceo@funnydev.vn&format=json&oflags=b', [], 'json');
                if ($response['BadIP'] == 1) {
                    $black += 1;
                }
                $total += 1;
            }
        } catch (Exception) {}

        // Check blacklist from easydmarc
        try {
            if (!empty($this->credentials['easydmarc']) && (strtolower($this->credentials['easydmarc']) !== 'off')) {
                $response = $this->request('GET', 'https://easydmarc.com/tools/ip-domain-reputation-check?term=' . $ip);
                $tmp_black = substr_count($response, '<td>Listed</td>');
                $tmp_clean = substr_count($response, '<td>Not listed</td>');
                $black += $tmp_black;
                $total += $tmp_clean + $tmp_black;
            }
        } catch (Exception) {}

        // Check blacklist from valli
        try {
            if (!empty($this->credentials['valli']) && (strtolower($this->credentials['valli']) !== 'off')) {
                $response = $this->request('GET', 'https://multirbl.valli.org/lookup/' . $ip . '.html');
                $black += intval($this->fetch_value($response, '<span class="global_data_cntBlacklisted_DNSBLBlacklistTest">', '<'));
                $total += intval($this->fetch_value($response, '<span class="global_data_cnt_DNSBLBlacklistTest">', '<'));
            }
        } catch (Exception) {}

        // Check blacklist from uceprotect
        try {
            if (!empty($this->credentials['uceprotect']) && (strtolower($this->credentials['uceprotect']) !== 'off')) {
                $param = [
                    'whattocheck' => 'IP',
                    'ipr' => $ip,
                    'subchannel' => '5756107b7be',
                ];
                $response = $this->request('POST', 'https://www.uceprotect.net/en/rblcheck.php', $param);
                if (str_contains($response, '<strong>LISTED</strong>')) {
                    $black += 1;
                }
                $total += 1;
            }
        } catch (Exception) {}

        // Check blacklist from projecthoneypot
        try {
            if (!empty($this->credentials['projecthoneypot']) && (strtolower($this->credentials['projecthoneypot']) !== 'off')) {
                $response = $this->request('GET', 'https://www.projecthoneypot.org/ip_' . $ip);
                if (!str_contains($response, 't have data on this IP currently')) {
                    $black += 1;
                }
                $total += 1;
            }
        } catch (Exception) {}

        // Check blacklist from team-cymru
        try {
            if (!empty($this->credentials['team-cymru']) && (strtolower($this->credentials['team-cymru']) !== 'off')) {
                $param = [
                    'ips' => $ip,
                ];
                $response = $this->request('POST', 'https://reputation.team-cymru.com/script/search.php', $param);
                if (!str_contains($response, '<tbody><tr class="low">')) {
                    $black += 1;
                }
                if (!$result['trustable']['proxy']) {
                    if (str_contains($response, '<td>proxy</td>')) {
                        $result['trustable']['proxy'] = true;
                    }
                }
                if (!$result['trustable']['botnet']) {
                    if ((str_contains($response, '<td>bot</td>')) || (str_contains($response, '<td>controller</td>')) || (str_contains($response, '<td>darknet</td>')) || (str_contains($response, '<td>phishing</td>')) || (str_contains($response, '<td>scanner</td>'))) {
                        $result['trustable']['botnet'] = true;
                    }
                }
                if ($result['trustable']['spam_email'] === 'Unknown') {
                    if (str_contains($response, '<td>spam</td>')) {
                        $result['trustable']['spam_email'] = 'Critical';
                    }
                }
                $total += 1;
            }
        } catch (Exception) {}

        // Check botnet from fortiguard
        try {
            if (!empty($this->credentials['fortiguard']) && (strtolower($this->credentials['fortiguard']) !== 'off')) {
                $param = [
                    'value' => $ip,
                ];
                $response = $this->request('POST', 'https://www.fortiguard.com/learnmore/botnetip', $param);
                if (! $result['trustable']['botnet']) {
                    if (str_contains($response, '<strong>not been found</strong>')) {
                        $result['trustable']['botnet'] = true;
                    }
                }
            }
        } catch (Exception) {}

        // Check spam email from talosintelligence
        try {
            if (!empty($this->credentials['talosintelligence']) && (strtolower($this->credentials['talosintelligence']) !== 'off')) {
                $response = $this->request('GET', 'https://talosintelligence.com/cloud_intel/ip_reputation?ip=' . $ip, [], 'json');
                $result['trustable']['spam_email'] = $response['reputation']['spam_level'];
                if (strtolower($response['reputation']['threat_level_mnemonic']) === 'trusted') {
                    $result['trustable']['reputation'] = 'Good';
                } elseif (strtolower($response['reputation']['threat_level_mnemonic']) === 'untrusted') {
                    $result['trustable']['reputation'] = 'Bad';
                } elseif (strtolower($response['reputation']['threat_level_mnemonic']) === 'unknown') {
                    $result['trustable']['reputation'] = 'Unknown';
                } else {
                    $result['trustable']['reputation'] = 'Normal';
                }
            }
        } catch (Exception) {}

        // Check fraud score from scamalytics
        try {
            if (!empty($this->credentials['scamalytics']) && (strtolower($this->credentials['scamalytics']) !== 'off')) {
                $response = $this->request('GET', 'https://scamalytics.com/ip/' . $ip);
                $scamalytics = (int)$this->fetch_value($response, 'Fraud Score: ', '<');
                if ($result['trustable']['fraud_score'] < $scamalytics) {
                    $result['trustable']['fraud_score'] = $scamalytics;
                }
            }
        } catch (Exception) {}

        // Perform quality checking from cleantalk
        try {
            if (!empty($this->credentials['cleantalk'])) {
                $cleantalk = $this->request('GET', 'https://api.cleantalk.org/?method_name=spam_check&auth_key=' . $this->credentials['cleantalk'] . '&ip=' . urlencode($ip), [], 'json', false, [], [], ['timeout' => $fast ? 3 : 10]);

                if (! $result['trustable']['spam']) {
                    $result['trustable']['spam'] = (isset($cleantalk['data'][$ip]['in_antispam']) && ($cleantalk['data'][$ip]['in_antispam'] === 0));
                }

                if (! $result['trustable']['spam']) {
                    $result['trustable']['in_security'] = (isset($cleantalk['data'][$ip]['in_security']) && ($cleantalk['data'][$ip]['in_security'] === 0));
                }

                if ($fast && $result['trustable']['spam']) {
                    $result['reason'] = 'This ip was marked as spam';
                    $result['recommend'] = false;
                    return $result;
                }

                if (isset($cleantalk['data'][$ip]['spam_rate'])) {
                    $result['trustable']['fraud_score'] = max($result['trustable']['fraud_score'], (int) round($cleantalk['data'][$ip]['spam_rate'] * 100));
                }
                if ($fast && $score && ($result['trustable']['fraud_score'] >= 75)) {
                    $result['reason'] = 'This ip was marked as fraudulent';
                    $result['recommend'] = false;
                    return $result;
                }
            }
        } catch (Exception) {}

        // Perform quality checking from iphub
        try {
            if (!empty($this->credentials['iphub'])) {
                $iphub = $this->request('GET', 'http://v2.api.iphub.info/ip/' . $ip, [], 'json', false, ['x-key' => $this->credentials['iphub']], [], ['timeout' => $fast ? 3 : 10]);

                if (! $result['trustable']['proxy']) {
                    $result['trustable']['proxy'] = $iphub['block'] === 1;
                }

                if ($result['trustable']['proxy'] && $fast) {
                    $result['reason'] = 'This ip was marked as proxy';
                    $result['recommend'] = false;
                    return $result;
                }
            }
        } catch (Exception) {}

        // Perform quality checking from ipqualityscore
        try {
            if (!empty($this->credentials['ipqualityscore'])) {
                $ipqualityscore = $this->request('GET', 'https://ipqualityscore.com/api/json/ip/' . $this->credentials['ipqualityscore'] . '/' . urlencode($ip) . '?strictness=1', [], 'json', false, [], [], ['timeout' => $fast ? 3 : 10]);

                if (! $result['trustable']['mobile']) {
                    $result['trustable']['mobile'] = (bool) ($ipqualityscore['mobile'] ?? false);
                }

                if (! $result['trustable']['proxy']) {
                    $result['trustable']['proxy'] = $ipqualityscore['proxy'] || $ipqualityscore['vpn'] || $ipqualityscore['active_vpn'] || $ipqualityscore['is_crawler'];
                }
                if ($fast && $result['trustable']['proxy']) {
                    $result['reason'] = 'This ip was marked as proxy';
                    $result['recommend'] = false;
                    return $result;
                }

                if (! $result['trustable']['botnet']) {
                    $result['trustable']['botnet'] = $ipqualityscore['active_tor'] || $ipqualityscore['tor'];
                }
                if ($fast && $result['trustable']['botnet']) {
                    $result['reason'] = 'This ip was marked as botnet';
                    $result['recommend'] = false;
                    return $result;
                }

                if ($result['trustable']['spam_ip']) {
                    $result['trustable']['spam_ip'] = (bool) ($ipqualityscore['recent_abuse'] ?? false);
                }
                if ($fast && $result['trustable']['spam_ip']) {
                    $result['reason'] = 'This ip was marked as spam';
                    $result['recommend'] = false;
                    return $result;
                }
            }
        } catch (Exception) {}

        try {
            if ($result['trustable']['fraud_score'] > 100) {
                $result['trustable']['fraud_score'] = 100;
            }
            if ($result['trustable']['fraud_score'] < 0) {
                $result['trustable']['fraud_score'] = 0;
            }
            if ($fast && $score && ($result['trustable']['fraud_score'] >= 75)) {
                $result['reason'] = 'This ip was marked as fraudulent';
                $result['recommend'] = false;
            }
        } catch (Exception) {
            $result['trustable']['fraud_score'] = 0;
        }

        if (($total === 0) || ($black === 0)) {
            $result['trustable']['blacklist'] = 0;
        } else {
            $result['trustable']['blacklist'] = round(($black / $total) * 100);
        }

        if ($fast && ($result['trustable']['blacklist'] >= 75)) {
            $result['reason'] = 'This ip was marked as blacklisted';
            $result['recommend'] = false;
            return $result;
        }

        return $result;
    }

    public function validate(string $ip, bool $fast = true, bool $score = false) {
        if ($fast) {
            // Cache for burst protection (fast repeated checks)
            $cacheKey = 'ip_filter:' . md5(implode('|', [$ip, (int)$fast, (int)$score]));
            if ($fast) {
                try {
                    $cached = Cache::get($cacheKey);
                    if (is_array($cached)) {
                        return $cached;
                    }
                } catch (Exception) {
                    // Ignore cache errors
                }
            }
        }

        $result = $this->handle($ip, $fast, $bool);

        // Cache only final results
        try { Cache::put($cacheKey, $result, 3600); } catch (Exception) {}

        return $result;
    }
}
