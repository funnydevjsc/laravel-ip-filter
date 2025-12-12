<?php

namespace App\Http\Controllers;

use FunnyDev\IpFilter\IpFilterSdk;
use Illuminate\Http\Request;

class IpFilterController
{
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        $instance = new IpFilterSdk();
        $result = $instance->validate(email: $request->input('email'), fast: false, score: true);

        /*
         * You could handle the response of validator here like:
         * if ($result['recommend']) {approve account action...} else {notice them}
         */

        return $result;
    }
}