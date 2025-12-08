<?php

namespace App\Http\Controllers;

use App\Models\BonusProduct;
use App\Models\User;
use App\Repositories\BonusRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusProductController extends Controller
{
    protected $bonusRep;

    public function __construct(BonusRepository $bonusRep)
    {
        $this->bonusRep = $bonusRep;
    }

    /**
     * 获取商品列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 获取所有启用的商品
        $products = BonusProduct::getActiveProducts();
        
        $result = [];
        foreach ($products as $product) {
            $item = [
                'id' => $product->id,
                'art' => $product->art,
                'name' => $product->name,
                'description' => $product->description,
                'points' => floatval($product->points),
                'menge' => $product->menge,
                'product_type' => $product->product_type,
                'special_permission_id' => $product->special_permission_id,
            ];
            
            // 检查商品是否应该显示
            $shouldShow = true;
            $disableReason = '';
            
            // 检查特殊权限商品：用户是否已拥有该权限
            if ($product->product_type === BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION && $product->special_permission_id) {
                $hasPermission = $user->specialPermissions()->where('special_permissions.id', $product->special_permission_id)->exists();
                if ($hasPermission) {
                    $item['has_permission'] = true;
                    $item['disable_reason'] = '您已拥有此权限';
                }
            }
            
            // 检查其他条件（gift_1, noad, cancel_hr等）
            // 这些条件在前端或后端都可以检查，这里先返回商品，由前端决定是否显示
            
            $result[] = $item;
        }
        
        return response()->json([
            'success' => true,
            'data' => $result,
            'user_bonus' => floatval($user->seedbonus ?? 0),
        ]);
    }

    /**
     * 购买商品
     */
    public function purchase(Request $request, $productId)
    {
        $user = $request->user();
        $product = BonusProduct::find($productId);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => '商品不存在',
            ], 404);
        }
        
        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => '该商品已下架',
            ], 400);
        }
        
        // 检查用户魔力值是否足够
        if ($user->seedbonus < $product->points) {
            return response()->json([
                'success' => false,
                'message' => '魔力值不足',
            ], 400);
        }
        
        // 检查特殊权限商品：用户是否已拥有该权限
        if ($product->product_type === BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION && $product->special_permission_id) {
            $hasPermission = $user->specialPermissions()->where('special_permissions.id', $product->special_permission_id)->exists();
            if ($hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => '您已经拥有此特殊权限，无需重复购买',
                ], 400);
            }
        }
        
        // 执行购买逻辑
        try {
            DB::beginTransaction();
            
            $art = $product->art;
            $points = $product->points;
            $menge = $product->menge;
            
            // 根据商品类型执行不同的购买逻辑
            if ($art == 'traffic') {
                $this->bonusRep->consumeToExchangeUpload($user->id, $menge, $points);
            } elseif ($art == 'traffic_downloaded') {
                $this->bonusRep->consumeToExchangeDownload($user->id, $menge, $points);
            } elseif ($art == 'invite') {
                $this->bonusRep->consumeToBuyInvite($user->id, $points);
            } elseif ($art == 'tmp_invite') {
                $this->bonusRep->consumeToBuyTemporaryInvite($user->id);
            } elseif ($art == 'title') {
                $title = $request->input('title');
                if (empty($title)) {
                    throw new \InvalidArgumentException('请输入自定义头衔');
                }
                $this->bonusRep->consumeToBuyCustomTitle($user->id, $title, $points);
            } elseif ($art == 'class') {
                $this->bonusRep->consumeToBuyVip($user->id, $points);
            } elseif ($art == 'gift_1') {
                $username = $request->input('username');
                $bonusgift = $request->input('bonusgift');
                $message = $request->input('message', '');
                if (empty($username) || empty($bonusgift)) {
                    throw new \InvalidArgumentException('请输入接收者用户名和赠送数量');
                }
                $this->bonusRep->consumeToGiftBonus($user->id, $username, $bonusgift, $message);
            } elseif ($art == 'gift_2') {
                $bonuscharity = $request->input('bonuscharity');
                $ratiocharity = $request->input('ratiocharity', 0.3);
                if (empty($bonuscharity)) {
                    throw new \InvalidArgumentException('请输入捐赠数量');
                }
                $this->bonusRep->consumeToCharityGiving($user->id, $bonuscharity, $ratiocharity);
            } elseif ($art == 'noad') {
                $this->bonusRep->consumeToBuyNoAd($user->id, $points);
            } elseif ($art == 'attendance_card') {
                $this->bonusRep->consumeToBuyAttendanceCard($user->id);
            } elseif ($art == 'rainbow_id') {
                $this->bonusRep->consumeToBuyRainbowId($user->id);
            } elseif ($art == 'change_username_card') {
                $this->bonusRep->consumeToBuyChangeUsernameCard($user->id);
            } elseif ($art == 'cancel_hr') {
                $hrId = $request->input('hr_id');
                if (empty($hrId)) {
                    throw new \InvalidArgumentException('请输入H&R ID');
                }
                $this->bonusRep->consumeToCancelHitAndRun($user->id, $hrId);
            } elseif (strpos($art, 'buy_special_permission') === 0 || $product->special_permission_id) {
                // 购买特殊权限
                $permission = $product->specialPermission;
                if (!$permission || !$permission->is_active) {
                    throw new \InvalidArgumentException('特殊权限不存在或已禁用');
                }
                
                // 扣除魔力值
                $this->bonusRep->consumeUserBonus($user->id, $points, \App\Models\BonusLogs::BUSINESS_TYPE_BUY_TORRENT, "购买特殊权限：{$permission->name}");
                
                // 分配权限
                $user->specialPermissions()->syncWithoutDetaching([$product->special_permission_id]);
                
                // 清除用户缓存
                clear_user_cache($user->id, $user->passkey);
            } else {
                throw new \InvalidArgumentException('不支持的商品类型：' . $art);
            }
            
            DB::commit();
            
            // 刷新用户数据
            $user->refresh();
            
            return response()->json([
                'success' => true,
                'message' => '购买成功',
                'user_bonus' => floatval($user->seedbonus),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

