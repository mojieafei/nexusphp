<?php

namespace Database\Seeders;

use App\Models\BonusProduct;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BonusProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取设置值
        $onegbupload_bonus = get_setting('bonus.onegbupload');
        $fivegbupload_bonus = get_setting('bonus.fivegbupload');
        $tengbupload_bonus = get_setting('bonus.tengbupload');
        $hundredgbupload_bonus = get_setting('bonus.hundredgbupload');
        $tengbdownload_bonus = get_setting('bonus.tengbdownload');
        $hundredgbdownload_bonus = get_setting('bonus.hundredgbdownload');
        $oneinvite_bonus = get_setting('bonus.oneinvite');
        $customtitle_bonus = get_setting('bonus.customtitle');
        $vipstatus_bonus = get_setting('bonus.vipstatus');
        $bonusnoadpoint_advertisement = get_setting('advertisement.bonusnoadpoint');
        $bonusnoadtime_advertisement = get_setting('advertisement.bonusnoadtime');

        // 获取语言文件中的文本（如果有）
        $lang_mybonus = [];
        $langFile = get_langfile_path('mybonus.php');
        if (file_exists($langFile)) {
            require_once($langFile);
            global $lang_mybonus;
        }

        $products = [
            // 1.0 GB Uploaded
            [
                'art' => 'traffic',
                'name' => $lang_mybonus['text_uploaded_one'] ?? '1.0 GB 上传流量',
                'description' => $lang_mybonus['text_uploaded_note'] ?? '使用魔力值兑换 1.0 GB 上传流量',
                'points' => $onegbupload_bonus,
                'menge' => 1073741824,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_UPLOAD,
                'sort_order' => 1,
            ],
            // 5.0 GB Uploaded
            [
                'art' => 'traffic',
                'name' => $lang_mybonus['text_uploaded_two'] ?? '5.0 GB 上传流量',
                'description' => $lang_mybonus['text_uploaded_note'] ?? '使用魔力值兑换 5.0 GB 上传流量',
                'points' => $fivegbupload_bonus,
                'menge' => 5368709120,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_UPLOAD,
                'sort_order' => 2,
            ],
            // 10.0 GB Uploaded
            [
                'art' => 'traffic',
                'name' => $lang_mybonus['text_uploaded_three'] ?? '10.0 GB 上传流量',
                'description' => $lang_mybonus['text_uploaded_note'] ?? '使用魔力值兑换 10.0 GB 上传流量',
                'points' => $tengbupload_bonus,
                'menge' => 10737418240,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_UPLOAD,
                'sort_order' => 3,
            ],
            // 100.0 GB Uploaded
            [
                'art' => 'traffic',
                'name' => $lang_mybonus['text_uploaded_four'] ?? '100.0 GB 上传流量',
                'description' => $lang_mybonus['text_uploaded_note'] ?? '使用魔力值兑换 100.0 GB 上传流量',
                'points' => $hundredgbupload_bonus,
                'menge' => 107374182400,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_UPLOAD,
                'sort_order' => 4,
            ],
            // 10.0 GB Downloaded
            [
                'art' => 'traffic_downloaded',
                'name' => $lang_mybonus['text_downloaded_ten_gb'] ?? '10.0 GB 下载流量',
                'description' => $lang_mybonus['text_download_note'] ?? '使用魔力值兑换 10.0 GB 下载流量',
                'points' => $tengbdownload_bonus,
                'menge' => 10737418240,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_DOWNLOAD,
                'sort_order' => 5,
            ],
            // 100.0 GB Downloaded
            [
                'art' => 'traffic_downloaded',
                'name' => $lang_mybonus['text_downloaded_hundred_gb'] ?? '100.0 GB 下载流量',
                'description' => $lang_mybonus['text_download_note'] ?? '使用魔力值兑换 100.0 GB 下载流量',
                'points' => $hundredgbdownload_bonus,
                'menge' => 107374182400,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_DOWNLOAD,
                'sort_order' => 6,
            ],
            // Invite
            [
                'art' => 'invite',
                'name' => $lang_mybonus['text_buy_invite'] ?? '购买邀请',
                'description' => $lang_mybonus['text_buy_invite_note'] ?? '购买一个邀请名额',
                'points' => $oneinvite_bonus,
                'menge' => 1,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_SOCIAL,
                'sort_order' => 7,
            ],
            // Tmp Invite
            [
                'art' => 'tmp_invite',
                'name' => $lang_mybonus['text_buy_tmp_invite'] ?? '购买临时邀请',
                'description' => $lang_mybonus['text_buy_tmp_invite_note'] ?? '购买一个临时邀请名额',
                'points' => \App\Models\BonusLogs::getBonusForBuyTemporaryInvite(),
                'menge' => 1,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_SOCIAL,
                'sort_order' => 8,
            ],
            // Custom Title
            [
                'art' => 'title',
                'name' => $lang_mybonus['text_custom_title'] ?? '自定义头衔',
                'description' => $lang_mybonus['text_custom_title_note'] ?? '购买自定义头衔',
                'points' => $customtitle_bonus,
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_TOOL,
                'sort_order' => 9,
            ],
            // VIP Status
            [
                'art' => 'class',
                'name' => $lang_mybonus['text_vip_status'] ?? 'VIP 身份',
                'description' => $lang_mybonus['text_vip_status_note'] ?? '购买 1 个月 VIP 身份',
                'points' => $vipstatus_bonus,
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_PERMISSION,
                'sort_order' => 10,
            ],
            // Bonus Gift
            [
                'art' => 'gift_1',
                'name' => $lang_mybonus['text_bonus_gift'] ?? '魔力值礼物',
                'description' => $lang_mybonus['text_bonus_gift_note'] ?? '赠送魔力值给其他用户',
                'points' => 100,
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_SOCIAL,
                'sort_order' => 11,
            ],
            // Attendance Card
            [
                'art' => 'attendance_card',
                'name' => $lang_mybonus['text_attendance_card'] ?? '购买签到卡',
                'description' => $lang_mybonus['text_attendance_card_note'] ?? '购买签到卡',
                'points' => \App\Models\BonusLogs::getBonusForBuyAttendanceCard(),
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_TOOL,
                'sort_order' => 12,
            ],
            // Rainbow ID
            [
                'art' => 'rainbow_id',
                'name' => $lang_mybonus['text_buy_rainbow_id'] ?? '购买彩虹ID',
                'description' => $lang_mybonus['text_buy_rainbow_id_note'] ?? '购买彩虹ID',
                'points' => \App\Models\BonusLogs::getBonusForBuyRainbowId(),
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_TOOL,
                'sort_order' => 13,
            ],
            // Change Username Card
            [
                'art' => 'change_username_card',
                'name' => $lang_mybonus['text_buy_change_username_card'] ?? '购买改名卡',
                'description' => $lang_mybonus['text_buy_change_username_card_note'] ?? '购买改名卡',
                'points' => \App\Models\BonusLogs::getBonusForBuyChangeUsernameCard(),
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_TOOL,
                'sort_order' => 14,
            ],
            // Donate (Charity)
            [
                'art' => 'gift_2',
                'name' => $lang_mybonus['text_charity_giving'] ?? '慈善捐赠',
                'description' => $lang_mybonus['text_charity_giving_note'] ?? '将魔力值作为慈善捐赠',
                'points' => 1000,
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_SOCIAL,
                'sort_order' => 15,
            ],
            // Cancel Hit and Run
            [
                'art' => 'cancel_hr',
                'name' => $lang_mybonus['text_cancel_hr_title'] ?? '消除H&R',
                'description' => $lang_mybonus['text_cancel_hr_label'] ?? '消除一个H&R记录',
                'points' => \App\Models\BonusLogs::getBonusForCancelHitAndRun(),
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_TOOL,
                'sort_order' => 16,
            ],
            // No Ad
            [
                'art' => 'noad',
                'name' => ($bonusnoadtime_advertisement ?? 15) . ($lang_mybonus['text_no_advertisements'] ?? ' 天无广告'),
                'description' => $lang_mybonus['text_no_advertisements_note'] ?? sprintf('购买 %s 天无广告体验', $bonusnoadtime_advertisement ?? 15),
                'points' => $bonusnoadpoint_advertisement,
                'menge' => ($bonusnoadtime_advertisement ?? 15) * 86400,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_TOOL,
                'sort_order' => 17,
            ],
            // Mars Owner Card (火星老板卡)
            [
                'art' => 'mars_owner_card',
                'name' => '火星老板卡',
                'description' => '使用后可成为火星幸运局桌子的老板，有效期30天',
                'points' => 100000,
                'menge' => 0,
                'product_type' => BonusProduct::PRODUCT_TYPE_NORMAL,
                'category' => BonusProduct::CATEGORY_PERMISSION,
                'sort_order' => 18,
            ],
        ];

        foreach ($products as $product) {
            // 使用 art + menge 的组合作为唯一标识，因为多个商品可能使用相同的 art（如不同大小的流量包）
            BonusProduct::updateOrCreate(
                [
                    'art' => $product['art'],
                    'menge' => $product['menge'],
                ],
                $product
            );
        }

        // 为特殊权限创建商品（如果特殊权限存在）
        // 注意：这里使用 updateOrCreate 确保即使权限已存在，商品也会被创建/更新
        if (Schema::hasTable('special_permissions')) {
            $specialPermissions = \App\Models\SpecialPermission::where('is_active', true)->get();
            foreach ($specialPermissions as $permission) {
                // 默认价格，可以在后台修改
                $defaultPrice = 50000; // 默认5万魔力值
                
                BonusProduct::updateOrCreate(
                    [
                        'art' => 'buy_special_permission_' . $permission->id,
                        'special_permission_id' => $permission->id,
                    ],
                    [
                        'name' => '购买特殊权限：' . $permission->name,
                        'description' => $permission->description ?? '使用魔力值购买此特殊权限',
                        'points' => $defaultPrice,
                        'menge' => 0,
                        'product_type' => BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION,
                        'category' => BonusProduct::CATEGORY_PERMISSION,
                        'sort_order' => 100 + $permission->id,
                        'is_active' => true,
                    ]
                );
            }
            
            $this->command->info("Created/updated products for " . $specialPermissions->count() . " special permissions.");
        }
    }
}

