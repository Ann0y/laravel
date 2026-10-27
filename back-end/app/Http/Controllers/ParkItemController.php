<?php

namespace App\Http\Controllers;

use App\Models\ParkItem;
use App\Models\ParkItemOrder;
use App\Models\Visitor;
use App\Support\ApiException;
use App\Support\Formatter;
use Illuminate\Http\Request;

use function App\Support\ok;

/**
 * F17 园内项目：公开浏览、游客购买 / 我的项目订单
 */
class ParkItemController extends Controller
{
    /** 公开：在售项目列表 */
    public function index()
    {
        $list = ParkItem::where('del_flag', 0)
            ->where('status', '在售')
            ->orderBy('price')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->item_code,
                'itemCode' => $i->item_code,
                'name' => $i->item_name,
                'price' => (float) $i->price,
                'description' => $i->description,
            ]);

        return ok(['items' => $list]);
    }

    /** 游客购买项目票（提交即支付出票） */
    public function buy(Request $request)
    {
        $user = app('authUser');
        if ($user->role !== 'visitor') {
            throw new ApiException('仅游客可以购买园内项目', 403);
        }

        $data = $request->validate([
            'itemCode' => 'required|string|max:20',
            'visitDate' => 'required|date|after_or_equal:tomorrow',
            'quantity' => 'required|integer|min:1|max:5',
        ], AuthController::messages(), AuthController::attributes());

        $visitor = Visitor::where('user_id', $user->id)->firstOrFail();
        $order = (new \App\Support\ParkItemService())->createItemOrder(
            $visitor, $data['itemCode'], $data['visitDate'], (int) $data['quantity'],
        );

        return ok(['order' => Formatter::itemOrder($order)], '项目票购买成功，入园后在项目口出示核销码');
    }

    /** 我的项目订单 */
    public function myOrders()
    {
        $user = app('authUser');
        if ($user->role !== 'visitor') {
            throw new ApiException('仅游客可查询项目订单', 403);
        }

        $visitor = Visitor::where('user_id', $user->id)->firstOrFail();
        $list = ParkItemOrder::with(['item', 'tickets'])
            ->where('visitor_id', $visitor->id)
            ->orderByDesc('create_time')
            ->get()
            ->map(fn ($o) => Formatter::itemOrder($o));

        return ok(['orders' => $list]);
    }
}
