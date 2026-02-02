<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\CompanyTime;

class SetCompanyTimezone
{
    public function handle(Request $request, Closure $next)
    {
        $timezone = CompanyTime::DEFAULT_TIMEZONE;
        $company = $request->user()?->company;

        if ($company) {
            $timezone = CompanyTime::resolveTimezone($company);
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return $next($request);
    }
}
