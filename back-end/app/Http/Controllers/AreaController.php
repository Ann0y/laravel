<?php

namespace App\Http\Controllers;

use App\Models\ScenicArea;
use App\Models\OpenDay;
use App\Models\TicketStock;
use App\Models\TicketType;
use App\Support\Formatter;
use Illuminate\Http\Request;

use function App\Support\ok;

/**
 * 游客端公开数据：景区信息、票种、游园日期与剩余额度（对应 /api/area）
 */
class AreaController extends Controller
{
    public function index(Request $request)
    {
        $area = ScenicArea::orderBy('id')->first();

        $service = new \App\Support\TicketingService();
        $service->ensureOpenDays();

        $days = OpenDay::with('stocks')
            ->where('open_date', '>=', today())
            ->orderBy('open_date')
            ->limit(7)
            ->get();

        $openDays = $days->map(function (OpenDay $d) {
            return [
                'date' => $d->open_date->toDateString(),
                'closed' => $d->status !== '开放',
                'capacity' => $d->capacity,
                'per' => $d->stocks->keyBy(function (TicketStock $s) {
                    return $s->type->type_code;
                })->map(fn (TicketStock $s) => [
                    'quota' => $s->quota,
                    'sold' => $s->sold_count,
                ])->all(),
            ];
        })->values();

        $types = TicketType::where('del_flag', 0)->orderBy('id')->get()->map(fn ($t) => Formatter::ticketType($t));

        return ok([
            'area' => $area ? Formatter::area($area) : null,
            'types' => $types,
            'openDays' => $openDays,
        ]);
    }
}
