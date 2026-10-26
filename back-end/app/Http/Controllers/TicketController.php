<?php

namespace App\Http\Controllers;

use App\Models\SaleOrder;
use App\Support\ApiException;
use Illuminate\Http\Request;

use function App\Support\ok;

/**
 * 门票：订单门票明细、游客检索、票务员检票核销（F07）
 */
class TicketController extends Controller
{
    public function byOrder(int $orderId)
    {
        $order = SaleOrder::with(['tickets.latestRefund'])->findOrFail($orderId);
        $user = app('authUser');

        if ($user->role === 'visitor' && $order->visitor_id !== \App\Models\Visitor::where('user_id', $user->id)->value('id')) {
            throw new ApiException('无权查看该订单门票', 403);
        }

        return ok(['tickets' => $order->tickets->map(fn ($t) => \App\Support\Formatter::ticket($t))->values()]);
    }

    public function check(Request $request)
    {
        $data = $request->validate(['ticketNo' => 'required|string|max:32']);

        $ticket = (new \App\Support\TicketingService())->checkTicket(trim($data['ticketNo']), app('authUser'));

        return \App\Support\ok(['ticket' => [
            'ticketNo' => $ticket->ticket_no,
            'status' => $ticket->status,
            'checkTime' => optional($ticket->check_time)->toISOString(),
        ]], '核销成功，欢迎入园');
    }

    /** 管理员门票检索 */
    public function index(Request $request)
    {
        $query = \App\Models\Ticket::query()->with('order.visitor.user');

        if ($no = $request->input('ticketNo')) {
            $query->where('ticket_no', 'like', "%{$no}%");
        }
        if ($orderNo = $request->input('orderNo')) {
            $query->whereHas('order', fn ($q) => $q->where('order_no', 'like', "%{$orderNo}%"));
        }
        if ($visitDate = $request->input('visitDate')) {
            $query->whereDate('visit_date', $visitDate);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return ok([
            'page' => $page = $query->orderByDesc('create_time')->paginate(min((int) ($request->input('pageSize') ?: 10), 50))->appends($request->query()),
            'list' => collect($page->items())->map(fn (\App\Models\Ticket $t) => [
                'ticketNo' => $t->ticket_no,
                'visitDate' => $t->visit_date->toDateString(),
                'unitPrice' => (float) $t->unit_price,
                'status' => $t->status,
                'orderNo' => $t->order->order_no,
                'visitorName' => $t->order->visitor->user->name,
            ])->values(),
        ]);
    }
}
