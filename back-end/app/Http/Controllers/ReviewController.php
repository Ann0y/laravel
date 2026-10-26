<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReview;
use App\Models\Visitor;
use App\Support\ApiException;
use App\Support\Formatter;
use Illuminate\Http\Request;

use function App\Support\ok;

/**
 * F16 游园评价：公开展示（脱敏）+ 游客对已检票门票提交（一票一评）
 */
class ReviewController extends Controller
{
    /** 公开：最新评价（首页「游园人说」） */
    public function index(Request $request)
    {
        $list = TicketReview::with(['ticket.order.type', 'visitor.user'])
            ->orderByDesc('create_time')
            ->limit((int) $request->input('limit', 8))
            ->get()
            ->map(fn ($r) => Formatter::review($r, maskVisitor: true) + [
                'typeName' => $r->ticket->order->type->type_name,
                'visitDate' => $r->ticket->visit_date->toDateString(),
            ]);

        return ok(['list' => $list]);
    }

    /** 游客提交评价（仅自己名下、已检票门票，一票一评） */
    public function store(Request $request)
    {
        $user = app('authUser');
        if ($user->role !== 'visitor') {
            throw new ApiException('仅游客可以提交评价', 403);
        }

        $data = $request->validate([
            'ticketNo' => 'required|string|max:32',
            'rating' => 'required|integer|in:1,2,3,4,5',
            'content' => 'nullable|string|max:255',
        ], AuthController::messages(), AuthController::attributes());

        $visitor = Visitor::where('user_id', $user->id)->firstOrFail();
        $ticket = Ticket::where('ticket_no', trim($data['ticketNo']))->firstOrFail();

        if ($ticket->order->visitor_id !== $visitor->id) {
            throw new ApiException('无权评价该门票', 403);
        }
        if ($ticket->status !== '已检票') {
            throw new ApiException('游园核销后才能评价该门票');
        }
        if (TicketReview::where('ticket_id', $ticket->id)->exists()) {
            throw new ApiException('该门票已评价过');
        }

        $review = TicketReview::create([
            'ticket_id' => $ticket->id,
            'visitor_id' => $visitor->id,
            'rating' => (int) $data['rating'],
            'content' => $data['content'] ?? null,
        ]);

        return ok(Formatter::review($review), '评价提交成功，感谢你的反馈');
    }
}
