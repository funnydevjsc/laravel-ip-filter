<?php

namespace App\Http\Controllers;

use FunnyDev\IpFilter\IpFilterSdk;
use Illuminate\Http\Request;

class IpFilterController
{
    public function store(Request $request)
    {
        $instance = new IpFilterSdk();
        $result = $instance->validate(ip: $request->ip(), fast: false, score: true);

        /*
         * You could handle the response of validator here like:
         * if ($result['recommend']) {approve account action...} else {notice them}
         */

        return $result;
    }
}