<?php

namespace App\Http\Controllers;

use App\Models\RefundRecord;
use App\Models\SaleOrder;
use App\Models\Ticket;
use App\Models\Visitor;
use App\Support\ApiException;
use App\Support\TicketingService;
use Illuminate\Http\Request;

use function App\Support\ok;

/**
 * 退票：游客申请（F04）、管理员审核（F13）
 */
class RefundController extends Controller
{
    public function requestRefund(Request $request)
    {
        $data = $request->validate([
            'ticketNo' => 'required|string|max:32',
            'reason' => 'required|string|max:255',
        ]);

        $user = app('authUser');
        if ($user->role !== 'visitor') {
            throw new ApiException('仅游客可提交退票申请', 403);
        }

        $visitor = Visitor::where('user_id', $user->id)->firstOrFail();
        $record = (new TicketingService())->requestRefund($visitor, trim($data['ticketNo']), trim($data['reason']));

        return ok([
            'id' => $record->id,
            'auditStatus' => $record->audit_status,
            'refundAmount' => (float) $record->refund_amount,
        ], '退票申请已提交，等待管理员审核');
    }

    public function pending(Request $request)
    {
        $records = RefundRecord::with(['ticket.order.type', 'visitor.user'])
            ->where('audit_status', '待审核')
            ->orderByDesc('apply_time')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'ticketNo' => $r->ticket->ticket_no,
                'orderNo' => $r->ticket->order->order_no,
                'typeName' => $r->ticket->order->type->type_name,
                'visitDate' => $r->ticket->order->visit_date->toDateString(),
                'visitorName' => $r->visitor->user->name,
                'reason' => $r->reason,
                'refundAmount' => (float) $r->refund_amount,
                'applyTime' => optional($r->apply_time)->toISOString(),
            ]);

        return ok(['list' => $records]);
    }

    public function audit(Request $request, int $id)
    {
        $data = $request->validate([
            'approve' => 'required|boolean',
            // 通过可不写意见；驳回必须说明原因（需求文档 3.3）
            'opinion' => 'required_if:approve,false|nullable|string|max:255',
        ], \App\Http\Controllers\AuthController::messages(), AuthController::attributes());

        $record = RefundRecord::findOrFail($id);
        $record = (new TicketingService())->auditRefund($record, (bool) $data['approve'], $data['opinion'] ?? null, app('authUser'));

        return ok([
            'id' => $record->id,
            'auditStatus' => $record->audit_status,
        ], $record->audit_status === '已通过' ? '已通过，额度将自动回补' : '已驳回');
    }

    public function mine(Request $request)
    {
        $user = app('authUser');
        $visitorId = Visitor::where('user_id', $user->id)->value('id');

        $records = RefundRecord::with('ticket.order.type')
            ->where('visitor_id', $visitorId)
            ->orderByDesc('apply_time')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'ticketNo' => $r->ticket->ticket_no,
                'typeName' => $r->ticket->order->type->type_name,
                'refundAmount' => (float) $r->refund_amount,
                'reason' => $r->reason,
                'auditStatus' => $r->audit_status,
                'auditOpinion' => $r->audit_opinion,
                'auditTime' => optional($r->audit_time)->toISOString(),
                'applyTime' => optional($r->apply_time)->toISOString(),
            ]);

        return ok(['list' => $records]);
    }
}
