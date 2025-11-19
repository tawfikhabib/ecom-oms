<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\GenerateInvoiceAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected ConfirmOrderAction $confirmOrderAction;
    protected CancelOrderAction $cancelOrderAction;
    protected GenerateInvoiceAction $generateInvoiceAction;

    public function __construct(
        OrderService $orderService,
        ConfirmOrderAction $confirmOrderAction,
        CancelOrderAction $cancelOrderAction,
        GenerateInvoiceAction $generateInvoiceAction
    ) {
        $this->orderService = $orderService;
        $this->confirmOrderAction = $confirmOrderAction;
        $this->cancelOrderAction = $cancelOrderAction;
        $this->generateInvoiceAction = $generateInvoiceAction;
        $this->middleware('auth:api');
    }

    /**
     * Get all orders for authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $orders = $this->orderService->getCustomerOrders(auth()->id(), $perPage);

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
        ], 200);
    }

    /**
     * Create a new order
     *
     * @param StoreOrderRequest $request
     * @return JsonResponse
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $order = $this->orderService->createOrder(
                auth()->id(),
                $data['items'],
                [
                    'tax_amount' => $data['tax_amount'] ?? 0,
                    'shipping_amount' => $data['shipping_amount'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                ]
            );

            return response()->json([
                'message' => 'Order created successfully',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Order creation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get a single order
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function show(Order $order): JsonResponse
    {
        // Check ownership
        if ($order->customer_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => new OrderResource($order),
        ], 200);
    }

    /**
     * Confirm order (deduct inventory)
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function confirm(Order $order): JsonResponse
    {
        try {
            // Check ownership
            if ($order->customer_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $confirmed = $this->confirmOrderAction->execute($order);

            return response()->json([
                'message' => 'Order confirmed successfully',
                'data' => new OrderResource($confirmed),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Order confirmation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ship order
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function ship(Order $order): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'vendor'])) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $shipped = $this->orderService->shipOrder($order);

            return response()->json([
                'message' => 'Order shipped successfully',
                'data' => new OrderResource($shipped),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ship operation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deliver order
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function deliver(Order $order): JsonResponse
    {
        try {
            if (!auth()->user()->hasRole(['admin', 'vendor'])) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $delivered = $this->orderService->deliverOrder($order);

            return response()->json([
                'message' => 'Order delivered successfully',
                'data' => new OrderResource($delivered),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Delivery operation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel order
     *
     * @param Order $order
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(Order $order, Request $request): JsonResponse
    {
        try {
            // Check ownership
            if ($order->customer_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $reason = $request->input('reason', 'No reason provided');
            $cancelled = $this->cancelOrderAction->execute($order, $reason);

            return response()->json([
                'message' => 'Order cancelled successfully',
                'data' => new OrderResource($cancelled),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Order cancellation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate invoice for order
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function generateInvoice(Order $order): JsonResponse
    {
        try {
            // Check ownership
            if ($order->customer_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $invoice = $this->generateInvoiceAction->execute($order);

            return response()->json([
                'message' => 'Invoice generated successfully',
                'data' => [
                    'invoice_number' => $invoice->invoice_number,
                    'pdf_path' => $invoice->pdf_path,
                    'issued_at' => $invoice->issued_at,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invoice generation failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
