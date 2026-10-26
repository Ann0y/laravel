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
        ])->map(fn ($t) => TicketType::create($t + ['refundable' => 1, 'status' => '在售']));

        // ----------
        // 3. 未来 7 天开放日 + 额度（含一定已售量，模拟真实体感）
        // ----------
        $quota = ['ADULT' => 500, 'CHILD' => 200, 'STUDENT' => 200, 'SENIOR' => 150];
        $sold = ['ADULT' => 210, 'CHILD' => 60, 'STUDENT' => 45, 'SENIOR' => 30];
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
        $stockBoost = ['ADULT' => 80, 'CHILD' => 25, 'STUDENT' => 10, 'SENIOR' => 5];
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
        foreach ([[1, $check], [2, $check->copy()->addMinutes(4)]] as [$i, $time]) {
            Ticket::create([
                'ticket_no' => $this->ticketNo(),
                'order_id' => $used->id, 'type_id' => $adult->id,
                'visit_date' => $yesterDay->open_date, 'unit_price' => $adult->price, 'status' => '已检票',
                'check_time' => $time, 'checker_id' => $opUser->id,
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
