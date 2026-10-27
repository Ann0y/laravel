<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API 路由（需求文档第 7 章）
|--------------------------------------------------------------------------
| 登录态：Session（cookie），api 分组前置 StartSession（见 bootstrap/app.php）
| 统一响应：{code, message, data}，code=200 成功
*/

// 公开接口（游客可访问）
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/area', [AreaController::class, 'index']);
Route::get('/park/items', [\App\Http\Controllers\ParkItemController::class, 'index']);   // F17：公开在售园内项目
Route::get('/reviews', [ReviewController::class, 'index']);   // F16：公开最新游园评价
Route::get('/enums', fn () => \App\Support\ok([
    'orderStatus' => ['待支付', '已支付', '已使用', '退款中', '已退款', '已取消'],
    'ticketStatus' => ['未检票', '已检票', '已退款', '已过期'],
    'refundStatus' => ['待审核', '已通过', '已驳回'],
    'channel' => ['线上', '窗口'],
    'payMethod' => ['模拟支付', '现金', '扫码'],
    'typeStatus' => ['在售', '停售'],
]));

// 需登录
Route::middleware('auth.session')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/password', [AuthController::class, 'password']);

    // 订单与门票
    Route::post('/orders', [OrderController::class, 'create']);           // 游客购票 / 窗口代客（渠道按角色）
    Route::get('/orders/my', [OrderController::class, 'myOrders']);
    Route::get('/orders/mine', [OrderController::class, 'myOperated'])->middleware('auth.role:operator');
    Route::post('/orders/{id}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/reschedule', [OrderController::class, 'reschedule']);  // F03 扩展：门票改期
    Route::get('/tickets/order/{orderId}', [TicketController::class, 'byOrder']);
    Route::get('/refunds/mine', [RefundController::class, 'mine']);
    Route::post('/refunds', [RefundController::class, 'requestRefund']);
    Route::delete('/refunds/{id}', [RefundController::class, 'withdraw']);   // F04：游客撤回待审核申请
    Route::post('/reviews', [ReviewController::class, 'store']);                     // F16：游园评价（已检票门票）

    // 园内项目（F17：游客购买，需持当日门票）
    Route::post('/park/orders', [\App\Http\Controllers\ParkItemController::class, 'buy']);
    Route::get('/park/orders/my', [\App\Http\Controllers\ParkItemController::class, 'myOrders']);

    // 票务员
    Route::post('/tickets/check', [TicketController::class, 'check'])->middleware('auth.role:operator');
    Route::get('/operator/workbench', [AdminController::class, 'workbench'])->middleware('auth.role:operator');

    // 管理端
    Route::middleware('auth.role:admin')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/tickets', [TicketController::class, 'index']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'saveUser']);
        Route::put('/users/{id}', [AdminController::class, 'saveUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        Route::get('/ticket-types', [AdminController::class, 'types']);
        Route::post('/ticket-types', [AdminController::class, 'saveType']);
        Route::put('/ticket-types/{id}', [AdminController::class, 'saveType']);
        Route::delete('/ticket-types/{id}', [AdminController::class, 'deleteType']);

        Route::get('/park-items', [AdminController::class, 'parkItems']);
        Route::post('/park-items', [AdminController::class, 'saveParkItem']);
        Route::put('/park-items/{id}', [AdminController::class, 'saveParkItem']);
        Route::delete('/park-items/{id}', [AdminController::class, 'deleteParkItem']);

        Route::get('/open-days', [AdminController::class, 'openDays']);
        Route::put('/open-days/{id}', [AdminController::class, 'saveOpenDay']);
        Route::put('/stocks/{id}', [AdminController::class, 'saveStock']);

        Route::get('/refunds/pending', [RefundController::class, 'pending']);
        Route::patch('/refunds/{id}/audit', [RefundController::class, 'audit']);

        Route::get('/statistics/overview', [AdminController::class, 'statistics']);
    });
});
