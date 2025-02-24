<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;


class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function getProduct()
    {
        return DB::table('products')
            ->orderBy('products.created_at', 'desc')
            ->get();
    }

    public function createProduct(Request $request)
    {
        DB::table('products')->insert([
            'product_name' => $request->productName,
            'price' => $request->price,
            'content' => $request->content,
            'quantity' => $request->quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $request->all();
    }

    public function createOrder(Request $request)
    {
        $getProduct = DB::table('products')->where('id', $request->id)->get()->first();
        if ($getProduct) {
            $link = Str::random(10);

            $response = Http::withOptions([
                'verify' => false,
            ])->get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => 'bitcoin',
                'vs_currencies' => 'vnd'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $price = $data['bitcoin']['vnd'] ?? 0;

                DB::table('orders')->insert([
                    'product_id' => $request->id,
                    'price' => $getProduct->price,
                    'quantity' => $request->quantity,
                    'total_to_btc' => ($getProduct->price * $request->quantity) / $price,
                    'expire_date' => now()->addMinutes(10),
                    'link' => $link,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            return response()->json([
                'status' => 'success',
                'link' => $link
            ]);
        }
    }

    public function getOrder(Request $request)
    {
        //

        $getOrder = DB::table('orders')->where('link', $request->link)->get()->first();
        if ($getOrder) {
            if ($getOrder->expire_date < now()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Link Expired'
                ]);
            }
            return response()->json([
                'id' => $getOrder->id,
                'product_id' => $getOrder->product_id,
                'price' => $getOrder->price,
                'quantity' => $getOrder->quantity,
                'total_to_btc' => $getOrder->total_to_btc,
                'expire_date' => $getOrder->expire_date,
                'status' => $getOrder->status,
                'link' => $getOrder->link,
                'created_at' => $getOrder->created_at,
                'updated_at' => $getOrder->updated_at
            ]);;
        }
    }

    public function updateOrder(Request $request)
    {
        $getOrder = DB::table('orders')->where('link', $request->link)->first();

        if (!$getOrder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ]);
        }

        $apiUrl = "https://api.bscscan.com/api?module=account&action=tokentx&contractaddress=0x7130d2a12b9bcbfae4f2634d864a1ee1ce3ead9c&address=0x16280fD4150ef830fFF4B0B5fCFAA25dd8999999&startblock=0&endblock=99999999&sort=asc&apikey=H5VAI7AA9ZFF8YUZK1Z794A3J2IES59426";

        $response = Http::withOptions(['verify' => false])->get($apiUrl);
        $data = $response->json();

        if ($data['status'] == "1") {
            $transactions = $data['result'];

            $createdAt = Carbon::parse($getOrder->created_at);
            $expireDate = Carbon::parse($getOrder->expire_date);
            $expectedValue = bcmul($getOrder->total_to_btc, "1000000000000000000"); // Chuyển BTC thành Wei

            // Lọc giao dịch hợp lệ
            $validTransactions = collect($transactions)->filter(function ($tx) use ($createdAt, $expireDate, $expectedValue) {
                $timestampVN = Carbon::createFromTimestampUTC($tx['timeStamp'])->setTimezone('Asia/Ho_Chi_Minh');
                return $tx['contractAddress'] === "0x7130d2a12b9bcbfae4f2634d864a1ee1ce3ead9c" &&
                    strtolower($tx['to']) === "0x16280fd4150ef830fff4b0b5fcfaa25dd8999999" &&
                    $timestampVN->between($createdAt, $expireDate) &&
                    $tx['value'] === $expectedValue;
            });

            if ($validTransactions->isNotEmpty()) {
                DB::table('orders')->where('id', $getOrder->id)->update([
                    'status' => 'completed',
                    'updated_at' => now()
                ]);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Thanh toán thành công',
                    'transactions' => $validTransactions->values()->toArray()
                ]);
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Thanh toán không thành công'
        ]);
    }

    public function getListOrder()
    {
        DB::table('orders')
            ->where([
                ['expire_date', '<', now()],
                ['status', '=', 'pending'],
            ])
            ->update(
                ['status' => 'cancelled']
            );

        $getOrder = DB::table('orders')
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->select('orders.id', 'orders.product_id', 'orders.price', 'orders.quantity', 'orders.total_to_btc', 'orders.expire_date', 'orders.status', 'orders.link', 'orders.created_at', 'orders.updated_at', 'products.product_name')
            ->orderBy('orders.created_at', 'desc')
            ->get();
        return response()->json([
            'status' => 'success',
            'data' => $getOrder,
        ]);
    }
}
