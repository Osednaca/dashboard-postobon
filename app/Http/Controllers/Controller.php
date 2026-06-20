<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Redirect browser requests from API endpoints to the web view.
     */
    protected function redirectBrowserToWeb(string $webRoute): ?\Illuminate\Http\RedirectResponse
    {
        $request = request();

        if ($request->isMethod('GET') && str_contains($request->header('Accept', ''), 'text/html')) {
            return redirect()->route($webRoute);
        }

        return null;
    }
}
