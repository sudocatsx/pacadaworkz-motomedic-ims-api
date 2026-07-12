<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\POS\Cart\CartItemNotFoundException;
use App\Exceptions\POS\Cart\EmptyCartException;
use App\Exceptions\POS\InsufficientPaymentException;
use App\Http\Requests\POS\Cart\ApplyDiscountRequest;
use App\Http\Requests\POS\Cart\StoreCartItemRequest;
use App\Http\Requests\POS\Cart\UpdateCartItemRequest;
use App\Http\Requests\POS\CheckoutRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Http\Resources\PosProductResource;
use App\Http\Resources\SalesTransactionResource;
use App\Services\AuthorizationPinService;
use App\Services\PosService;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    private $posService;

    public function __construct(PosService $posService, private AuthorizationPinService $pins)
    {
        $this->posService = $posService;
    }

    public function products()
    {
        return response()->json([
            'success' => true,
            'data' => PosProductResource::collection($this->posService->getProducts()),
        ]);
    }

    public function show()
    {
        try {

            $userId = Auth::id();
            $result = $this->posService->getCart($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'cart' => CartResource::make($result['cart']),
                    'summary' => $result['summary'],
                ],
                'message' => 'Cart retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error', // least information bai kapag production na
                // 'message' => $e->getMessage() //comment out kapag dev mode
            ], 500);
        }
    }

    public function store(StoreCartItemRequest $request)
    {

        try {
            $userId = Auth::id();

            $validated = $request->validated();

            $result = $this->posService->addItemToCart($userId, $validated);

            return response()->json([
                'success' => true,
                // 'data' => new CartItemResource($result),
                'data' => CartItemResource::make($result),
                // 'data' => $result,
                'message' => 'Item added to cart successfully',
            ], 201);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('POS Add to Cart Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function update(UpdateCartItemRequest $request, int $id)
    {
        try {
            $userId = Auth::id();

            $validated = $request->validated();

            $result = $this->posService->updateCartItem($userId, $id, $validated['quantity']);

            return response()->json([
                'success' => true,
                'data' => CartItemResource::make($result),
                'message' => 'Cart item updated successfully',
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (CartItemNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('POS Update Cart Item Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function delete(int $id)
    {
        $userId = Auth::id();
        try {
            $this->posService->removeCartItem($userId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Cart item removed successfully',
            ]);
        } catch (CartItemNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('POS Delete Cart Item Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'cart_item_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function clearCart()
    {
        $userId = Auth::id();
        try {
            $this->posService->clearCart($userId);

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('POS Clear Cart Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function applyDiscount(ApplyDiscountRequest $request)
    {
        try {
            $validated = $request->validated();
            $userId = Auth::id();
            $initiator = $request->user('api')->load('role.permissions');
            $authorizer = $this->pins->authorize($initiator, (int) $validated['authorizer_id'], $validated['pin'], 'POS', 'Authorize Discount');
            unset($validated['authorizer_id'], $validated['pin']);

            $cart = $this->posService->applyDiscount($userId, $validated);
            $result = $this->posService->getCart($userId); // recalculate summary after discount

            return response()->json([
                'success' => true,
                'data' => [
                    'cart' => CartResource::make($result['cart']),
                    'summary' => $result['summary'],
                ],
                'message' => 'Discount applied successfully',
            ]);
        } catch (EmptyCartException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('POS Apply Discount Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    public function checkoutCart(CheckoutRequest $request)
    {
        try {
            $userId = Auth::id();
            $validated = $request->validated();

            $transaction = $this->posService->processCheckout($userId, $validated);

            return response()->json([
                'success' => true,
                'data' => SalesTransactionResource::make($transaction),
                'message' => 'Checkout successful',
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                // 'message' => 'Internal server error'
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (InsufficientPaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (EmptyCartException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('POS Checkout Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                // 'message' => $e->getMessage(),
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
