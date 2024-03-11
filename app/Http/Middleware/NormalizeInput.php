<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $fields = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

        foreach ($fields as $field) {
            if ($request->has($field) && $request->get($field) == 'x') {
                $request->merge([$field => null]);
            }
        }

        return $next($request);
    }
}
