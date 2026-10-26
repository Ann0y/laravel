<?php

namespace App\Http\Controllers;

use App\Models\SysUser;
use App\Models\Visitor;
use App\Models\Operator;
use App\Models\SaleOrder;
use App\Models\Ticket;
use App\Models\RefundRecord;
use App\Support\ApiException;
use Illuminate\Http\Request;

use function App\Support\ok;

/** F10/F12/F15：用户（游客 / 票务员 / 管理员账号）与档案管理 */
class AdminController extends Controller
{
    // ---------- 账号 ----------
    public function users(Request $request)
    {
        $query = SysUser::query()->orderByDesc('create_time');
        if ($kw = $request->input('keyword')) {
            $query->where(fn ($q) => $q->where('username', 'like', "%{$kw}%")->orWhere('name', 'like', "%{$kw}%"));
        }
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        return ok([
            'page' => $page = $query->paginate(min((int) ($request->input('pageSize') ?: 10), 50))->appends($request->query()),
            'list' => collect($page->items())->map(fn (SysUser $u) => [
                'id' => $u->id, 'username' => $u->username, 'name' => $u->name,
                'role' => $u->role, 'status' => $u->status,
            ])->values(),
        ]);
    }

    public function saveUser(Request $request, ?int $id = null)
    {
        $creating = $id === null;
        $rules = [
            'username' => 'required|string|max:50|unique:sys_user,username' . ($creating ? '' : ",{$id}"),
            'name' => 'required|string|max:50',
            'role' => 'required|in:admin,operator,visitor',
            'status' => 'required|integer|in:0,1',
        ];
        if ($creating) {
            $rules['password'] = 'required|string|min:6|max:100';
        }
        $data = $request->validate($rules, AuthController::messages());

        $payload = [
            'username' => $data['username'],
            'name' => $data['name'],
            'role' => $data['role'],
            'status' => $data['status'],
        ];
        if ($creating) {
            $payload['password'] = bcrypt($data['password']);
        }

        $user = $creating ? SysUser::create($payload) : tap(SysUser::findOrFail($id))->update($payload);

        // 同步档案表
        if ($user->role === 'visitor') {
            Visitor::firstOrCreate(['user_id' => $user->id], ['status' => '正常']);
        }
        if ($user->role === 'operator') {
            Operator::firstOrCreate(['user_id' => $user->id], ['code' => 'OP' . str_pad((string) $user->id, 3, '0', STR_PAD_LEFT)]);
        }

        return ok(['id' => $user->id], $creating ? '创建成功' : '更新成功');
    }

    public function deleteUser(int $id)
    {
        $user = SysUser::findOrFail($id);
        $user->del_flag = 1;
        $user->status = 0;
        $user->save();

        return ok(null, '已删除（逻辑删除）');
    }

    // ---------- 票种 ----------
    public function types()
    {
        return ok(['list' => \App\Models\TicketType::where('del_flag', 0)->orderBy('id')->get()->map(fn ($t) => [
            // 管理端契约：数字主键 + 票种编码分开（游客端 Formatter::ticketType 的 id 为编码）
            'id' => $t->id,
            'typeCode' => $t->type_code,
            'typeName' => $t->type_name,
            'price' => (float) $t->price,
            'applicable' => $t->applicable,
            'refundable' => (bool) $t->refundable,
            'status' => $t->status,
        ])->values()]);
    }

    public function saveType(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'typeCode' => 'required|string|max:20|unique:ticket_type,type_code' . ($id ? ",{$id}" : ''),
            'typeName' => 'required|string|max:50',
            'price' => 'required|numeric|min:0.01',
            'applicable' => 'nullable|string|max:100',
            'refundable' => 'nullable|boolean',
            'status' => 'required|in:在售,停售',
        ]);

        $payload = [
            'type_code' => $data['typeCode'], 'type_name' => $data['typeName'], 'price' => $data['price'],
            'applicable' => $data['applicable'] ?? null, 'refundable' => (int) ($data['refundable'] ?? true), 'status' => $data['status'],
        ];

        $type = $id ? tap(\App\Models\TicketType::findOrFail($id))->update($payload) : \App\Models\TicketType::create($payload);

        return ok(['id' => $type->id], '保存成功');
    }

    public function deleteType(int $id)
    {
        \App\Models\TicketType::where('id', $id)->update(['del_flag' => 1, 'status' => '停售']);
        return ok(null, '已删除（逻辑删除）');
    }

    // ---------- 开放日 ----------
    public function openDays(Request $request)
    {
        $query = \App\Models\OpenDay::with('stocks.type')->orderBy('open_date');
        if ($from = $request->input('from')) {
            $query->whereDate('open_date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('open_date', '<=', $to);
        }

        return ok(['list' => $query->limit((int) ($request->input('limit') ?: 14))->get()->map(fn ($d) => [
            'id' => $d->id,
            'date' => $d->open_date->toDateString(),
            'status' => $d->status,
            'capacity' => $d->capacity,
            'stocks' => $d->stocks->map(fn ($s) => \App\Support\Formatter::stockRow($s))->values(),
        ])->values()]);
    }

    public function saveOpenDay(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:开放,闭园',
            'capacity' => 'required|integer|min:0',
        ]);
        \App\Models\OpenDay::where('id', $id)->update([
            'status' => $data['status'], 'capacity' => $data['capacity'],
        ]);

        return ok(null, '已更新');
    }

    // ---------- 额度 ----------
    public function saveStock(Request $request, int $id)
    {
        $data = $request->validate([
            'quota' => 'required|integer|min:0',
            'status' => 'required|in:正常,停售',
        ]);
        $stock = \App\Models\TicketStock::findOrFail($id);
        $quota = max($data['quota'], $stock->sold_count);
        $stock->quota = $quota;
        $stock->status = $data['status'];
        $stock->save();

        return \App\Support\ok(['remaining' => $stock->quota - $stock->sold_count], '额度已更新');
    }

    // ---------- 工作台 / 统计 ----------
    public function statistics(Request $request)
    {
        $start = $request->input('start', now()->subDays(29)->toDateString());
        $end = $request->input('end', now()->toDateString());

        $orders = SaleOrder::query()->with('type')
            ->whereIn('status', ['已支付', '已使用', '退款中', '已退款']);

        $byDay = (clone $orders)
            ->selectRaw("DATE(create_time) as d, COUNT(*) as orders, SUM(total_amount) as revenue, SUM(quantity) as tickets")
            ->whereBetween('create_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn ($r) => ['date' => $r->d, 'orders' => (int) $r->orders, 'tickets' => (int) $r->tickets, 'revenue' => (float) $r->revenue]);

        $byType = (clone $orders)
            ->selectRaw('type_id, COUNT(*) as c, SUM(total_amount) as revenue')
            ->groupBy('type_id')->get()
            ->map(fn ($r) => ['type' => $r->type->type_name, 'count' => (int) $r->c, 'revenue' => (float) $r->revenue]);

        $byChannel = (clone $orders)
            ->selectRaw('channel, COUNT(*) as c, SUM(total_amount) as revenue')
            ->groupBy('channel')->get()
            ->map(fn ($r) => ['channel' => $r->channel, 'orders' => (int) $r->c, 'revenue' => (float) $r->revenue]);

        $checkedByDay = \App\Models\Ticket::query()
            ->whereDate('check_time', '>=', $start)->whereDate('check_time', '<=', $end)
            ->selectRaw('DATE(check_time) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

        $todaySold = \App\Models\Ticket::whereDate('create_time', today())->count();
        $todayChecked = \App\Models\Ticket::whereDate('check_time', today())->count();
        $todayRevenue = (clone $orders)->whereDate('create_time', today())->sum('total_amount');
        $pendingRefunds = \App\Models\RefundRecord::where('audit_status', '待审核')->count();
        $totalTickets = (clone $orders)->get(['id'])->count();
        $refunds = \App\Models\RefundRecord::query()->whereBetween('apply_time', [$start, $end]);
        $refundTotal = (clone $refunds)->count();

        return ok([
            'today' => ['sold' => $todaySold, 'checked' => $todayChecked, 'revenue' => (float) $todayRevenue],
            'pendingRefunds' => $pendingRefunds,
            'byDay' => $byDay->all(),
            'byType' => $byType->all(),
            'byChannel' => $byChannel->all(),
            'checkedByDay' => $checkedByDay->all(),
            'refundCount' => $refundTotal,
            'ticketCount' => $totalTickets,
        ]);
    }

    public function workbench()
    {
        $user = app('authUser');
        $operator = Operator::where('user_id', $user->id)->firstOrFail();

        $todaySold = SaleOrder::where('operator_id', $operator->id)->whereDate('create_time', today())->count();
        $todayRevenue = SaleOrder::where('operator_id', $operator->id)->whereDate('create_time', today())->sum('total_amount');
        $todaySoldTickets = \App\Models\Ticket::whereDate('create_time', today())->count();
        $pendingCheck = \App\Models\Ticket::whereDate('visit_date', today())->where('status', '未检票')->count();

        return ok([
            'operator' => ['code' => $operator->code, 'windowNo' => $operator->window_no],
            'todaySoldOrders' => $todaySold,
            'todayRevenue' => (float) $todayRevenue,
            'todaySoldTickets' => $todaySoldTickets,
            'todayPendingCheck' => $pendingCheck,
        ]);
    }
}
