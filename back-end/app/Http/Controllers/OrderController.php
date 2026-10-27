<?php

namespace App\Http\Controllers;

use App\Support\ApiException;
use Illuminate\Http\Request;

use function App\Support\ok;

/**
 * 订单：游客购票（F03）、我的订单（F04）、票务员现场售票（F06）、管理员检索（F12）
 */
class OrderController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'typeId' => 'required|string|max:20',
            'visitDate' => 'required|date|after_or_equal:today',
            'quantity' => 'required|integer|min:1|max:30',
        ], AuthController::messages(), AuthController::attributes());

        $user = app('authUser');

        if ($user->role === 'visitor') {
            $visitor = \App\Models\Visitor::where('user_id', $user->id)->firstOrFail();
            $order = (new \App\Support\TicketingService())->createOrder(
                $visitor, $data['typeId'], $data['visitDate'], $data['quantity'], '线上',
            );

            return ok(['order' => \App\Support\Formatter::order($order)], '订单提交成功');
        }

        if ($user->role === 'operator') {
            // 窗口代客购票：提交即支付出票（需求文档 3.2），登记线下支付方式（现金 / 扫码）
            $operator = \App\Models\Operator::where('user_id', $user->id)->firstOrFail();
            $visitorName = $request->input('visitorName');
            $payMethod = $request->input('payMethod');
            if ($payMethod !== null && !in_array($payMethod, ['现金', '扫码'], true)) {
                throw new ApiException('支付方式仅支持现金或扫码');
            }
            $visitor = $this->ensureVisitor($visitorName);
            $order = (new \App\Support\TicketingService())->createOrder(
                $visitor, $data['typeId'], $data['visitDate'], $data['quantity'], '窗口', $operator, $payMethod,
            );

            return ok(['order' => \App\Support\Formatter::order($order)], '窗口购票成功，已出票');
        }

        throw new ApiException('管理员不参与购票', 403);
    }

    private function ensureVisitor(?string $name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new ApiException('窗口购票请填写游客姓名');
        }

        $user = \App\Models\SysUser::firstOrCreate(
            ['username' => 'walkin_' . md5($name)],
            ['password' => bcrypt(\Str::random(16)), 'name' => $name, 'role' => 'visitor'],
        );

        return \App\Models\Visitor::firstOrCreate(['user_id' => $user->id], ['status' => '正常']);
    }

    public function myOrders(Request $request)
    {
        $user = app('authUser');
        $visitor = \App\Models\Visitor::where('user_id', $user->id)->firstOrFail();

        $query = \App\Models\SaleOrder::where('visitor_id', $visitor->id)
            ->with(['type', 'tickets.latestRefund', 'visitor.user'])
            ->orderByDesc('create_time');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return ok(['orders' => $query->get()->map(fn ($o) => \App\Support\Formatter::order($o))->values()->all()]);
    }

    public function myOperated(Request $request)
    {
        $user = app('authUser');
        $operator = \App\Models\Operator::where('user_id', $user->id)->firstOrFail();

        $orders = \App\Models\SaleOrder::where('channel', '窗口')
            ->where('operator_id', $operator->id)
            ->with(['type', 'tickets.latestRefund', 'visitor.user'])
            ->orderByDesc('create_time')
            ->get()
            ->map(fn ($o) => \App\Support\Formatter::order($o))
            ->values();

        return ok(['orders' => $orders]);
    }

    /** 管理员全量订单检索：订单号 / 日期 / 渠道 / 状态多条件 + 分页（F12） */
    public function index(Request $request)
    {
        $query = \App\Models\SaleOrder::query()
            ->with(['type', 'tickets.latestRefund', 'visitor.user', 'operator.user'])
            ->orderByDesc('create_time');

        if ($no = $request->input('orderNo')) {
            $query->where('order_no', 'like', "%{$no}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }
        if ($visitDate = $request->input('visitDate')) {
            $query->whereDate('visit_date', $visitDate);
        }

        return ok([
            'page' => $page = $query->paginate(min((int) ($request->input('pageSize') ?: 10), 50))->appends($request->query()),
            'list' => collect($page->items())->map(fn ($o) => \App\Support\Formatter::order($o))->values(),
        ]);
    }

    public function pay(int $id)
    {
        $order = \App\Models\SaleOrder::with('tickets')->findOrFail($id);
        $this->assertOwn($order);

        $order = (new \App\Support\TicketingService())->payOrder($order);

        return ok(['order' => \App\Support\Formatter::order($order)], '支付成功，出票完成');
    }

    public function cancel(int $id)
    {
        $order = \App\Models\SaleOrder::findOrFail($id);
        $this->assertOwn($order);

        $order = (new \App\Support\TicketingService())->cancelOrder($order);

        return ok(['order' => \App\Support\Formatter::order($order)], '订单已取消，当日额度已回补');
    }

    /** 门票改期（游客）：整单未检票门票换游园日，吞吐同一套库存校验 */
    public function reschedule(Request $request, int $id)
    {
        $user = app('authUser');
        if ($user->role !== 'visitor') {
            throw new ApiException('仅游客支持门票改期', 403);
        }

        $data = $request->validate([
            'visitDate' => 'required|date|after_or_equal:today',
        ], \App\Http\Controllers\AuthController::messages(), AuthController::attributes());

        $visitor = \App\Models\Visitor::where('user_id', $user->id)->firstOrFail();
        $order = (new \App\Support\TicketingService())->rescheduleOrder(
            $visitor, \App\Models\SaleOrder::findOrFail($id), $data['visitDate'],
        );

        return ok(['order' => \App\Support\Formatter::order($order)], '改期成功，门票已更新至新游园日期');
    }

    private function assertOwn(\App\Models\SaleOrder $order): void
    {
        $user = app('authUser');
        if ($user->role === 'visitor') {
            $visitor = \App\Models\Visitor::where('user_id', $user->id)->firstOrFail();
            if ($order->visitor_id !== $visitor->id) {
                throw new ApiException('无权操作该订单', 403);
            }
        }
    }
}
