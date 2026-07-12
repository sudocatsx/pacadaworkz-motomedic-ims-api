<?php

namespace App\Http\Controllers\API;

use App\Services\AuthorizationPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthorizationController extends Controller
{
    public function __construct(private AuthorizationPinService $pins) {}

    public function authorizers(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:discount,refund,void'],
        ]);
        [$module, $permission] = match ($validated['action']) {
            'discount' => ['POS', 'Authorize Discount'],
            'refund' => ['Transactions', 'Refund'],
            'void' => ['Transactions', 'Void'],
        };

        return response()->json(['success' => true, 'data' => $this->pins->eligible($module, $permission)]);
    }

    public function setPin(Request $request)
    {
        $user = $request->user('api')->loadMissing('role.permissions');
        abort_unless(
            $user->hasPermission('POS', 'Authorize Discount') ||
            $user->hasPermission('Transactions', 'Refund') ||
            $user->hasPermission('Transactions', 'Void'),
            403
        );
        $validated = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ]);
        $user->update(['authorization_pin' => Hash::make($validated['pin'])]);

        return response()->json(['success' => true, 'message' => 'Authorization PIN updated.']);
    }
}
