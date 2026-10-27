<?php

namespace Database\Seeders;

use App\Models\SaleOrder;
use App\Models\ScenicArea;
use App\Models\SysUser;
use App\Models\Ticket;
use App\Models\TicketStock;
use App\Models\TicketType;
use App\Models\Visitor;
use App\Models\OpenDay;
use App\Models\Operator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** 演示数据：与需求文档 1.1/1.2 口径一致 */
    public function run(): void
    {
        // ----------
        // 1. 景区
        // ----------
        $area = ScenicArea::create([
            'name' => '青岚谷风景区',
            'description' => '峡谷栈道与森林步道两条经典线路。',
            'address' => '南岭市青岚区云栖路 88 号',
            'open_time' => '08:30-17:00',
            'notice' => '景区执行每日限流入园，请按订单上的游园日期当天入园，门票当日一次有效。长者、儿童优惠票请携带有效证件，检票时由工作人员核对。',
            'default_capacity' => 5000,
        ]);

        // ----------
        // 2. 票种
        // ----------
        $types = collect([
            ['type_code' => 'ADULT', 'type_name' => '成人票', 'price' => 110, 'applicable' => '18 周岁（含）至 59 周岁'],
            ['type_code' => 'CHILD', 'type_name' => '儿童票', 'price' => 60, 'applicable' => '6 周岁（含）至 17 周岁或身高 1.2 米（含）以上'],
            ['type_code' => 'STUDENT', 'type_name' => '学生票', 'price' => 80, 'applicable' => '全日制在校学生，凭学生证入园'],
            ['type_code' => 'SENIOR', 'type_name' => '长者票', 'price' => 70, 'applicable' => '60 周岁（含）以上，凭有效证件入园'],
            ['type_code' => 'FAMILY', 'type_name' => '家庭套票', 'price' => 260, 'applicable' => '2 名成人 + 1 名儿童联票（按套售卖，1 套 = 3 张门票）'],
            ['type_code' => 'GROUP', 'type_name' => '团体票', 'price' => 90, 'applicable' => '10 人（含）以上团队，凭单位介绍信入园，每单限 10 张起'],
            ['type_code' => 'MILITARY', 'type_name' => '军人优待票', 'price' => 40, 'applicable' => '现役军人 / 残障人士，凭有效证件优惠购票'],
        ])->map(fn ($t) => TicketType::create($t + ['refundable' => 1, 'status' => '在售']));

        // ----------
        // 2b. 园内项目（F17：入园后单独购买，不限量，需持当日门票）
        // ----------
        $items = collect([
            ['item_code' => 'CABLEWAY', 'item_name' => '峡谷索道缆车', 'price' => 80, 'description' => '单程上行至观景台，俯瞰峡谷全貌，单次乘坐'],
            ['item_code' => 'GLASS_BRIDGE', 'item_name' => '高空玻璃桥', 'price' => 50, 'description' => '悬空玻璃步道通行一次，恐高慎选'],
            ['item_code' => 'CRUISE', 'item_name' => '峡谷游船', 'price' => 60, 'description' => '水路环线约 40 分钟，含救生装备'],
            ['item_code' => 'GUIDE', 'item_name' => '人工讲解服务', 'price' => 30, 'description' => '金牌导游带讲解，约 1 小时走主线'],
            ['item_code' => 'SHUTTLE', 'item_name' => '园内观光车', 'price' => 40, 'description' => '单日不限次乘坐（乘车口出示票）'],
        ])->map(fn ($i) => \App\Models\ParkItem::create($i + ['status' => '在售']));

        // ----------
        // 3. 未来 7 天开放日 + 额度（含一定已售量，模拟真实体感）
        // ----------
        $quota = ['ADULT' => 500, 'CHILD' => 200, 'STUDENT' => 200, 'SENIOR' => 150, 'FAMILY' => 200, 'GROUP' => 100, 'MILITARY' => 150];
        $sold = ['ADULT' => 210, 'CHILD' => 60, 'STUDENT' => 45, 'SENIOR' => 30, 'FAMILY' => 22, 'GROUP' => 12, 'MILITARY' => 15];
        $days = collect();

        foreach (range(0, 6) as $i) {
            $date = now()->addDays($i)->startOfDay();
            $day = OpenDay::create([
                'area_id' => $area->id,
                'open_date' => $date->toDateString(),
                'capacity' => 5000,
                'status' => '开放',
            ]);
            $days->push($day);
        }

        // ----------
        // 4. 账号：管理员 / 票务员 / 演示游客
        // ----------
        $admin = SysUser::create(['username' => 'admin', 'password' => bcrypt('admin123'), 'name' => '景区管理员', 'role' => 'admin']);

        $opUser = SysUser::create(['username' => 'window01', 'password' => bcrypt('window123456'), 'name' => '李四（窗口票务员）', 'role' => 'operator']);
        Operator::create(['user_id' => $opUser->id, 'code' => 'OP001', 'window_no' => 'A-01']);

        $demoUser = SysUser::create(['username' => 'demo', 'password' => bcrypt('demo123456'), 'name' => '张三', 'role' => 'visitor']);
        $demo = Visitor::create(['user_id' => $demoUser->id, 'phone' => '13800001234', 'status' => '正常']);

        // ----------
        // 5. 演示订单（覆盖 已支付 / 已使用 / 退款中和已驳回迭代展示）
        // ----------
        $adult = $types->firstWhere('type_code', 'ADULT');
        $student = $types->firstWhere('type_code', 'STUDENT');
        $stockByType = fn ($code) => $quota[$code];

        // 已支付订单（后天的学生票 × 2）
        $paidDay = $days[2];
        $paid = SaleOrder::create([
            'order_no' => 'SO' . now()->subDay()->format('Ymd') . '0002',
            'visitor_id' => $demo->id,
            'type_id' => $student->id,
            'visit_date' => $paidDay->open_date,
            'quantity' => 2, 'unit_price' => $student->price, 'total_amount' => $student->price * 2,
            'channel' => '线上', 'status' => '已支付', 'pay_method' => '模拟支付',
            'pay_time' => now()->subDay()->addHours(-2),
        ]);
        for ($i = 0; $i < 2; $i++) {
            Ticket::create([
                'ticket_no' => $this->ticketNo(),
                'order_id' => $paid->id, 'type_id' => $student->id,
                'visit_date' => $paidDay->open_date, 'unit_price' => $student->price, 'status' => '未检票',
            ]);
        }

        // 已使用订单（昨天的成人票 × 2，一票已核销演示）
        $yesterDay = OpenDay::create([
            'area_id' => $area->id, 'open_date' => now()->subDay()->toDateString(),
            'capacity' => 5000, 'status' => '开放',
        ]);
        $days->push($yesterDay);
        $used = SaleOrder::create([
            'order_no' => 'SO' . now()->subDays(2)->format('Ymd') . '0003',
            'visitor_id' => $demo->id,
            'type_id' => $adult->id,
            'visit_date' => $yesterDay->open_date,
            'quantity' => 2, 'unit_price' => $adult->price, 'total_amount' => $adult->price * 2,
            'channel' => '线上', 'status' => '已使用', 'pay_method' => '模拟支付',
            'pay_time' => now()->subDays(2),
        ]);

        // 当日额度的已售量回填（演示库存余量口径）
        $stockBoost = ['ADULT' => 80, 'CHILD' => 25, 'STUDENT' => 10, 'SENIOR' => 5, 'FAMILY' => 8, 'GROUP' => 4, 'MILITARY' => 6];
        foreach ($days as $day) {
            foreach ($types as $t) {
                $soldCount = min($quota[$t->type_code] - 1, $sold[$t->type_code] + $stockBoost[$t->type_code]);
                TicketStock::create([
                    'open_day_id' => $day->id, 'type_id' => $t->id,
                    'quota' => $quota[$t->type_code], 'sold_count' => $soldCount, 'status' => '正常',
                ]);
            }
        }

        $check = now()->subDay()->setTime(9, 30);
        $usedTickets = [];
        foreach ([[1, $check], [2, $check->copy()->addMinutes(4)]] as [$i, $time]) {
            $usedTickets[] = Ticket::create([
                'ticket_no' => $this->ticketNo(),
                'order_id' => $used->id, 'type_id' => $adult->id,
                'visit_date' => $yesterDay->open_date, 'unit_price' => $adult->price, 'status' => '已检票',
                'check_time' => $time, 'checker_id' => $opUser->id,
            ]);
        }

        // ----------
        // 6. 游园评价（F16：已检票门票一票一评，首页「游园人说」展示脱敏评价）
        // ----------
        $reviewSeeds = [
            ['rating' => 5, 'content' => '峡谷栈道太出片了，一路走一路拍，水又清又绿，下次带朋友再来。'],
            ['rating' => 4, 'content' => '森林步道很凉快，指示牌清楚，就是观光车班次略少，值得推荐。'],
        ];
        foreach ($usedTickets as $i => $t) {
            \App\Models\TicketReview::create([
                'ticket_id' => $t->id,
                'visitor_id' => $demo->id,
                'rating' => $reviewSeeds[$i]['rating'],
                'content' => $reviewSeeds[$i]['content'],
                'create_time' => $check->copy()->addHours(4)->addMinutes($i * 23),
            ]);
        }

        // ----------
        // 7. 园内项目演示订单（F17）：demo 的缆车票放今天，票务员"检票核销"页可直接演示项目口核销
        // ----------
        $cableway = $items->firstWhere('item_code', 'CABLEWAY');
        $today = OpenDay::where('open_date', now()->toDateString())->first();
        if ($today) {
            $itemOrder = \App\Models\ParkItemOrder::create([
                'order_no' => 'IO' . now()->format('Ymd') . '0001',
                'visitor_id' => $demo->id,
                'item_id' => $cableway->id,
                'visit_date' => $today->open_date,
                'quantity' => 1,
                'unit_price' => $cableway->price,
                'total_amount' => $cableway->price,
                'pay_method' => '模拟支付', 'pay_time' => now(),
                'status' => '已支付',
            ]);
            \App\Models\ParkItemTicket::create([
                'ticket_code' => 'PI' . substr(strtoupper(bin2hex(random_bytes(8))), 0, 12),
                'order_id' => $itemOrder->id, 'item_id' => $cableway->id,
                'visit_date' => $today->open_date, 'status' => '未核销',
            ]);
        }
    }

    private function ticketNo(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $no = 'TK';
        for ($i = 0; $i < 12; $i++) {
            $no .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $no;
    }
}
