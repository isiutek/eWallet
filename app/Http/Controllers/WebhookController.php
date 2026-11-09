<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\TopupService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function xenditInvoice(Request $request, TopupService $topups)
    {
        $expected = (string) config('xendit.callback_token');
        $given = (string) $request->header('x-callback-token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            AuditLog::record('webhook.xendit.unauthorized', null, ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $topups->handleXenditInvoiceCallback($request->all());

        return response()->json(['received' => true]);
    }
}
