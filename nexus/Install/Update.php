<?php

namespace Nexus\Install;

use App\Models\Attendance;
use App\Models\BonusLogs;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\HitAndRun;
use App\Models\Icon;
use App\Models\Language;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\AttendanceRepository;
use App\Repositories\BonusRepository;
use App\Repositories\ExamRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TokenRepository;
use App\Repositories\ToolRepository;
use App\Repositories\TorrentRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class Update extends Install
{

    protected $steps = ['Env check', 'Get files', 'Update .env',  'Perform updates'];

    protected string $lockFile = 'update.lock';


    public function getLogFile()
    {
        return getLogFile("update");
    }

    public function getUpdateDirectory()
    {
        return ROOT_PATH . 'public/update';
    }

    public function listTableFieldsFromCreateTable($createTableSql)
    {
        $arr = preg_split("/[\r\n]+/", $createTableSql);
        $result = [];
        foreach ($arr as $value) {
            $value = trim($value);
            if (substr($value, 0, 1) != '`') {
                continue;
            }
            $pos = strpos($value, '`', 1);
            $field = substr($value, 1, $pos - 1);
            $result[$field] = rtrim($value, ',');
        }
        return $result;
    }

    public function listTableFieldsFromDb($table)
    {
        $sql = "desc $table";
        $res = sql_query($sql);
        $data = [];
        while ($row = mysql_fetch_assoc($res)) {
            $data[$row['Field']] = $row;
        }
        return $data;
    }

    private function addSetting($name, $value)
    {
        $attributes = [
            'name' => $name,
        ];
        $now = Carbon::now()->toDateTimeString();
        $values = [
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        return Setting::query()->firstOrCreate($attributes, $values);
    }

    public function runExtraQueries()
    {
        $toolRep = new ToolRepository();
        $redis = NExusDB::redis();
        /**
         * @since 1.7.13
         */
        foreach (['adminpanel', 'modpanel', 'sysoppanel'] as $table) {
            $columnInfo = NexusDB::getMysqlColumnInfo($table, 'id');
            if ($columnInfo['DATA_TYPE'] == 'tinyint' || empty($columnInfo['EXTRA']) || $columnInfo['EXTRA'] != 'auto_increment') {
                sql_query("alter table $table modify id int(11) unsigned not null AUTO_INCREMENT");
            }
        }

        //custom field menu
        $url = 'fields.php';
        $table = 'adminpanel';
        $count = get_row_count($table, "where url = " . sqlesc($url));
        if ($count == 0) {
            $insert = [
                'name' => 'Custom Field Manage',
                'url' => $url,
                'info' => 'Manage custom fields',
            ];
            $id = NexusDB::insert($table, $insert);
            $this->doLog("[ADD CUSTOM FIELD MENU] insert: " . json_encode($insert) . " to table: $table, id: $id");
        }
        //since beta8
        if (WITH_LARAVEL && !NexusDB::hasColumn('categories', 'icon_id')) {
            $this->doLog('[INIT CATEGORY ICON_ID]');
            $this->runMigrate('database/migrations/2022_03_08_040415_add_icon_id_to_categories_table.php');
            $icon = Icon::query()->orderBy('id', 'asc')->first();
            if ($icon) {
                Category::query()->where('icon_id', 0)->update(['icon_id' => $icon->id]);
            }
        }
        //fix base url, since beta8
        if (WITH_LARAVEL && NexusDB::hasTable('settings')) {
            $settingBasic = get_setting('basic');
            if (isset($settingBasic['BASEURL']) && Str::startsWith($settingBasic['BASEURL'], 'localhost')) {
                $this->doLog('[RESET CONFIG basic.BASEURL]');
                Setting::query()->where('name', 'basic.BASEURL')->update(['value' => '']);
            }
            if (isset($settingBasic['announce_url']) && Str::startsWith($settingBasic['announce_url'], 'localhost')) {
                $this->doLog('[RESET CONFIG basic.announce_url]');
                Setting::query()->where('name', 'basic.announce_url')->update(['value' => '']);
            }
        }

        //torrent support sticky second level
        if (WITH_LARAVEL) {
            $columnInfo = NexusDB::getMysqlColumnInfo('torrents', 'pos_state');
            $this->doLog("[TORRENT POS_STATE], column info: " . json_encode($columnInfo));
            if ($columnInfo['DATA_TYPE'] == 'enum') {
                $sql = "alter table torrents modify `pos_state` varchar(32) NOT NULL DEFAULT 'normal'";
                $this->doLog("[ALTER TORRENT POS_STATE TYPE TO VARCHAR], $sql");
                sql_query($sql);
            }
        }

        /**
         * @since 1.6.0-beta9
         *
         * attendance change, do migrate
         */
        if (WITH_LARAVEL) {
            if (!NexusDB::hasTable('attendance')) {
                //no table yet, no need to migrate
                $this->runMigrate('database/migrations/2021_06_08_113437_create_attendance_table.php');
            }
            if (!NexusDB::hasColumn('attendance', 'total_days')) {
                $this->runMigrate('database/migrations/2021_06_13_215440_add_total_days_to_attendance_table.php');
                $attendanceRep = new AttendanceRepository();
                $count = $attendanceRep->migrateAttendance();
                $this->doLog("[MIGRATE_ATTENDANCE] $count");
            }
        }

        /**
         * @since 1.6.0-beta13
         *
         * add seed points to user
         */
        if (WITH_LARAVEL && !NexusDB::hasColumn('users', 'seed_points')) {
            $this->runMigrate('database/migrations/2021_06_24_013107_add_seed_points_to_users_table.php');
            //Don't do this, initial seed points = 0;
//            $result = $this->initSeedPoints();
            $this->doLog("[INIT SEED POINTS]");
        }

        /**
         * @since 1.6.0-beta14
         *
         * add id to agent_allowed_exception
         */
        if (WITH_LARAVEL && !NexusDB::hasColumn('agent_allowed_exception', 'id')) {
            $this->runMigrate('database/migrations/2022_02_25_021356_add_id_to_agent_allowed_exception_table.php');
            $this->doLog("[ADD_ID_TO_AGENT_ALLOWED_EXCEPTION]");
        }

        /**
         * @since 1.6.0
         *
         * init tag
         */
        if (WITH_LARAVEL && !NexusDB::hasTable('tags')) {
            $this->runMigrate('database/migrations/2022_03_07_012545_create_tags_table.php');
            $this->initTag();
            $this->doLog("[INIT_TAG]");
        }

        /**
         * @since 1.6.3
         *
         * add usersearch.php and unco.php
         */
        $menus = [
            ['name' => 'Search user', 'url' => 'usersearch.php', 'info' => 'Search user'],
            ['name' => 'Confirm user', 'url' => 'unco.php', 'info' => 'Confirm user to complete registration'],
        ];
        $table = 'modpanel';
        foreach ($menus as $menu) {
            $count = get_row_count($table, "where url = " . sqlesc($menu['url']));
            if ($count == 0) {
                $id = NexusDB::insert($table, $menu);
                $this->doLog("[ADD MENU] insert: " . json_encode($menu) . " to table: $table, id: $id");
            }
        }

        /**
         * @since 1.7.0
         *
         * add attendance_card to users
         */
        if (WITH_LARAVEL && !NexusDB::hasColumn('users', 'attendance_card')) {
            $this->runMigrate('database/migrations/2022_04_02_163930_create_attendance_logs_table.php');
            $this->runMigrate('database/migrations/2022_04_03_041642_add_attendance_card_to_users_table.php');
            $rep = new AttendanceRepository();
            $count = $rep->migrateAttendanceLogs();
            $this->doLog("[ADD_ATTENDANCE_CARD_TO_USERS], migrateAttendanceLogs: $count");
        }

        /**
         * @since 1.7.12
         */
        $menus = [
            ['name' => 'Add Bonus/Attend card/Invite/upload', 'url' => 'increment-bulk.php', 'info' => 'Add Bonus/Attend card/Invite/upload to certain classes'],
        ];
        $table = 'sysoppanel';
        $this->addMenu($table, $menus);
        $menuToDel = ['amountupload.php', 'amountattendancecard.php', 'amountbonus.php', 'deletedisabled.php'];
        $this->removeMenu($menuToDel);

        /**
         * @since 1.7.19
         */
        $this->removeMenu(['freeleech.php']);
        NexusDB::cache_del('nexus_rss');
        NexusDB::cache_del('nexus_is_ip_seed_box');

        /**
         * @since 1.7.24
         */
        if (!NexusDB::hasColumn('searchbox', 'extra')) {
            $this->runMigrate('database/migrations/2022_09_02_031539_add_extra_to_searchbox_table.php');
            SearchBox::query()->update(['extra' => [
                SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => 1,
                SearchBox::EXTRA_DISPLAY_SEED_BOX_ICON_ON_TORRENT_LIST => 1,
            ]]);
        }

        /**
         * @since 1.8.0
         */
        $shouldMigrateSearchBox = false;
        if (!NexusDB::hasColumn('searchbox', 'section_name')) {
            $shouldMigrateSearchBox = true;
            $searchBoxLog = "no section_name field";
        } else {
            $columnInfo = NexusDB::getMysqlColumnInfo('searchbox', 'section_name');
            $searchBoxLog = "has section_name, searchbox.section DATA_TYPE: " . $columnInfo['DATA_TYPE'];
            if ($columnInfo['DATA_TYPE'] != 'json') {
                $searchBoxLog .= ", not json";
                $shouldMigrateSearchBox = true;
            }
        }
        $this->doLog("$searchBoxLog, shouldMigrateSearchBox: $shouldMigrateSearchBox");
        if ($shouldMigrateSearchBox) {
            $this->runMigrate('database/migrations/2021_06_08_113437_create_searchbox_table.php');
            $this->runMigrate('database/migrations/2022_03_08_041951_add_custom_fields_to_searchbox_table.php');
            $this->runMigrate('database/migrations/2022_09_02_031539_add_extra_to_searchbox_table.php');
            $this->runMigrate('database/migrations/2022_09_05_230532_add_mode_to_section_related.php');
            $this->runMigrate('database/migrations/2022_09_06_004318_add_section_name_to_searchbox_table.php');
            $this->runMigrate('database/migrations/2022_09_06_030324_change_searchbox_field_extra_to_json.php');
            $this->migrateSearchBoxModeRelated();
            $this->doLog("[MIGRATE_TAXONOMY_TO_MODE_RELATED]");
        }
        $this->removeMenu(['catmanage.php']);

        if (!NexusDB::hasColumn('users', 'seed_points_updated_at')) {
            $this->runMigrate('database/migrations/2022_11_23_042152_add_seed_points_seed_times_update_time_to_users_table.php');
            foreach (User::$notificationOptions as $option) {
                $sql = "update users set notifs = concat(notifs, '[$option]') where instr(notifs, '[$option]') = 0";
                NexusDB::statement($sql);
            }
        }

        if (!$this->isSnatchedTableTorrentUserUnique()) {
            $toolRep->removeDuplicateSnatch();
            $this->runMigrate('database/migrations/2023_03_29_021950_handle_snatched_user_torrent_unique.php');
            $this->doLog("removeDuplicateSnatch and migrate 2023_03_29_021950_handle_snatched_user_torrent_unique");
        }

        if (!NexusDB::hasIndex("peers", "unique_torrent_peer_user")) {
            $toolRep->removeDuplicatePeer();
            $this->runMigrate('database/migrations/2023_04_01_005409_add_unique_torrent_peer_user_to_peers_table.php');
            $this->doLog("removeDuplicatePeer and migrate 2023_04_01_005409_add_unique_torrent_peer_user_to_peers_table");
        }

        /**
         * @since 1.8.3
         */
        $hasTableSetting = NexusDB::hasTable('settings');
        if ($hasTableSetting) {
            $updateSettings = [];
            if (get_setting("system.meilisearch_enabled") == 'yes') {
                $updateSettings["enabled"] = "yes";
            }
            if (get_setting("system.meilisearch_search_description") == 'yes') {
                $updateSettings["search_description"] = "yes";
            }
            if (!empty($updateSettings)) {
                $this->saveSettings(['meilisearch' => $updateSettings]);
            }
        }

        /**
         * @since 1.8.10
         */
        if ($hasTableSetting) {
            Setting::query()->firstOrCreate(
                ["name" => "system.alarm_email_receiver"],
                ["value" => User::query()->where("class", User::CLASS_STAFF_LEADER)->first(["id"])->id]
            );
        }

        /**
         * @since 1.9.0
         */
        if (!Schema::hasTable("torrent_extras")) {
            $this->runMigrate("database/migrations/2025_01_08_133552_create_torrent_extra_table.php");
            Artisan::call("upgrade:migrate_torrents_table_text_column");
            Language::updateTransStatus();
            $this->addSetting('main.complain_enabled', 'yes');
            $this->addSetting('image_hosting.driver', 'local');
            $this->addSetting('permission.user_token_allowed', json_encode(TokenRepository::listUserTokenPermissions(false)));
        }
        if (!$redis->exists(Setting::USER_TOKEN_PERMISSION_ALLOWED_CACHE_KRY)) {
            Setting::updateUserTokenPermissionAllowedCache(TokenRepository::listUserTokenPermissions(false));
        }

        /**
         * @since 1.9.5
         */
        if (!Schema::hasColumn("snatched", "hit_and_run_id")) {
            $this->runMigrate("database/migrations/2025_06_09_222012_add_hr_and_buy_id_to_snatched_table.php");
            Artisan::call("upgrade:migrate_snatched_hr_id");
            Artisan::call("upgrade:migrate_snatched_buy_log_id");
        }
        if (!Schema::hasTable("tracker_urls")) {
            $this->runMigrate("database/migrations/2025_06_19_194137_create_tracker_urls_table.php");
            $this->initTrackerUrl('update');
            NexusDB::cache_del("nexus_plugin_store_all");
        }

        /**
         * 星尘农场游戏 - 初始化
         * @since 1.9.x
         */
        if (!Schema::hasTable("stardust_farms")) {
            $this->doLog("[STARDUST_FARM] Creating tables...");
            $this->runMigrate("database/migrations/2025_11_08_000001_create_stardust_farm_tables.php");
            $this->doLog("[STARDUST_FARM] Tables created!");
            
            // 初始化作物数据（10种天体）
            $this->doLog("[STARDUST_FARM] Initializing crops...");
            $this->initStardustCrops();
            $this->doLog("[STARDUST_FARM] Crops initialized!");
            
            // 初始化成就数据
            $this->doLog("[STARDUST_FARM] Initializing achievements...");
            $this->initStardustAchievements();
            $this->doLog("[STARDUST_FARM] Achievements initialized!");
            
            $this->doLog("[STARDUST_FARM] Installation completed! 🌍🪐☀️");
        }
        
        /**
         * 星尘农场 - 广播系统
         * @since 1.9.x
         */
        if (!Schema::hasTable("stardust_broadcasts")) {
            $this->doLog("[STARDUST_FARM_BROADCAST] Creating broadcasts table...");
            $this->runMigrate("database/migrations/2025_11_08_000002_create_stardust_broadcasts_table.php");
            $this->doLog("[STARDUST_FARM_BROADCAST] Broadcasts table created!");
        }

        /**
         * 角色权限系统 - 初始化
         * @since 1.9.x
         */
        if (!Schema::hasTable("roles")) {
            $this->doLog("[ROLE_SYSTEM] Creating role tables...");
            $this->runMigrate("database/migrations/2025_01_20_000001_create_roles_table.php");
            $this->runMigrate("database/migrations/2025_01_20_000002_create_role_permissions_table.php");
            $this->runMigrate("database/migrations/2025_01_20_000003_create_user_roles_table.php");
            $this->doLog("[ROLE_SYSTEM] Role tables created!");
            
            // 初始化默认角色数据
            $this->doLog("[ROLE_SYSTEM] Initializing default roles...");
            Artisan::call("db:seed", ["--class" => "RoleSeeder", "--force" => true]);
            $this->doLog("[ROLE_SYSTEM] Default roles initialized!");
            $this->doLog("[ROLE_SYSTEM] Role system installation completed! 👥");
        } else {
            // 如果表已存在，检查是否需要添加 icon 字段
            if (!Schema::hasColumn("roles", "icon")) {
                $this->doLog("[ROLE_SYSTEM] Adding icon column to roles table...");
                $this->runMigrate("database/migrations/2025_01_20_000004_add_icon_to_roles_table.php");
                $this->doLog("[ROLE_SYSTEM] Icon column added!");
                
                // 更新现有角色的图标
                $this->doLog("[ROLE_SYSTEM] Updating existing roles with icons...");
                Artisan::call("db:seed", ["--class" => "RoleSeeder", "--force" => true]);
                $this->doLog("[ROLE_SYSTEM] Roles updated with icons!");
            }

            // 无论是否新增 icon，都重新跑一次 RoleSeeder，确保新增的角色（如 re_uploader）被自动补全
            $this->doLog("[ROLE_SYSTEM] Ensuring latest roles (e.g. re_uploader) are seeded...");
            Artisan::call("db:seed", ["--class" => "RoleSeeder", "--force" => true]);
            $this->doLog("[ROLE_SYSTEM] Roles seeder re-run completed!");
        }

        /**
         * 特殊权限系统 - 初始化
         * @since 1.9.x
         */
        if (!Schema::hasTable("special_permissions")) {
            $this->doLog("[SPECIAL_PERMISSION] Creating special permission tables...");
            $this->runMigrate("database/migrations/2025_01_25_000001_create_special_permissions_table.php");
            $this->runMigrate("database/migrations/2025_01_25_000002_create_user_special_permissions_table.php");
            $this->doLog("[SPECIAL_PERMISSION] Special permission tables created!");
        }
        
        /**
         * 游戏得分表 - 添加一键获取标记字段
         * @since 1.9.x
         */
        if (Schema::hasTable("meteor_game_scores") && !Schema::hasColumn("meteor_game_scores", "is_auto_claim")) {
            $this->doLog("[GAME_SCORES] Adding is_auto_claim column to game score tables...");
            $this->runMigrate("database/migrations/2025_01_25_000003_add_is_auto_claim_to_game_scores.php");
            $this->doLog("[GAME_SCORES] is_auto_claim column added!");
        }

        /**
         * 魔力值商品表 - 初始化
         * @since 1.9.x
         * 注意：必须在特殊权限初始化之后执行，因为商品会为特殊权限创建对应商品
         */
        if (!Schema::hasTable("bonus_products")) {
            $this->doLog("[BONUS_PRODUCTS] Creating bonus products table...");
            $this->runMigrate("database/migrations/2025_01_25_000004_create_bonus_products_table.php");
            $this->doLog("[BONUS_PRODUCTS] Bonus products table created!");
        } else {
            // 如果表已存在，检查并修复唯一约束（art 改为 art + menge 组合）
            $this->doLog("[BONUS_PRODUCTS] Checking unique constraint...");
            $this->runMigrate("database/migrations/2025_01_25_000005_fix_bonus_products_unique_constraint.php");
            $this->doLog("[BONUS_PRODUCTS] Unique constraint updated!");
        }

        // 补充新增字段：category
        if (Schema::hasTable("bonus_products")) {
            $this->doLog("[BONUS_PRODUCTS] Adding category column if missing...");
            $this->runMigrate("database/migrations/2025_02_20_000006_add_category_to_bonus_products.php");
            $this->doLog("[BONUS_PRODUCTS] Category column ensured!");
        }

        // 无论表是否已存在，都确保特殊权限数据已初始化
        // 注意：必须在 bonus_products 表存在后执行，因为模型事件会创建关联商品
        if (Schema::hasTable("special_permissions") && Schema::hasTable("bonus_products")) {
            $this->doLog("[SPECIAL_PERMISSION] Initializing/updating special permissions...");
            Artisan::call("db:seed", ["--class" => "SpecialPermissionSeeder", "--force" => true]);
            $this->doLog("[SPECIAL_PERMISSION] Special permissions initialization completed! 🔑");
        }

        // 无论表是否已存在，都确保商品数据已初始化（包括为现有特殊权限创建商品）
        // 注意：必须在特殊权限初始化之后执行，因为商品会为特殊权限创建对应商品
        if (Schema::hasTable("bonus_products")) {
            $this->doLog("[BONUS_PRODUCTS] Initializing/updating products...");
            Artisan::call("db:seed", ["--class" => "BonusProductSeeder", "--force" => true]);
            $this->doLog("[BONUS_PRODUCTS] Products initialization completed! 🛒");
        }
    }

    public function runExtraMigrate()
    {
        if (!WITH_LARAVEL) {
            $this->doLog(__METHOD__ . ", laravel is not available");
            return;
        }
        if (NexusDB::hasColumn('torrents', 'tags')) {
            if (Torrent::query()->where('tags', '>', 0)->count() > 0 && TorrentTag::query()->count() == 0) {
                $this->doLog("[MIGRATE_TORRENT_TAG]...");
                $tagRep = new TagRepository();
                $tagRep->migrateTorrentTag();
                $this->doLog("[MIGRATE_TORRENT_TAG] done!");
            }
            $sql = 'alter table torrents drop column tags';
            sql_query($sql);
            $this->doLog($sql);
        } else {
            $this->doLog("torrents table does not has column: tags");
        }

        clear_setting_cache();

    }

    private function addMenu($table, array $menus)
    {
        foreach ($menus as $menu) {
            $count = get_row_count($table, "where url = " . sqlesc($menu['url']));
            if ($count == 0) {
                $id = NexusDB::insert($table, $menu);
                $this->doLog("[ADD MENU] insert: " . json_encode($menu) . " to table: $table, id: $id");
            }
        }
    }

    private function removeMenu(array $menus, array $tables = ['sysoppanel', 'adminpanel', 'modpanel'])
    {
        $this->doLog("[REMOVE MENU]: " . json_encode($menus));
        if (empty($menus)) {
            return;
        }
        foreach ($tables as $table) {
            NexusDB::table($table)->whereIn('url', $menus)->delete();
        }
    }


    public function listVersions()
    {
        $url = "https://api.github.com/repos/xiaomlove/nexusphp/releases";
        $versions = $this->requestGithub($url);
        return array_reverse($versions);
    }

    public function getLatestCommit()
    {
        $url = "https://api.github.com/repos/xiaomlove/nexusphp/commits/php8";
        return $this->requestGithub($url);
    }

    public function requestGithub($url)
    {
        $client = new Client();
        $logPrefix = "Request github: $url";
        $response = $client->get($url, ['timeout' => 10,]);
        if (($statusCode = $response->getStatusCode()) != 200) {
            throw new \RuntimeException("$logPrefix fail, status code：$statusCode");
        }
        if ($response->getBody()->getSize() <= 0) {
            throw new \RuntimeException("$logPrefix fail, response empty");
        }
        $bodyString = $response->getBody()->getContents();
        $this->doLog("[REQUEST_GITHUB_RESPONSE]: $bodyString");
        $results = json_decode($bodyString, true);
        if (empty($results) || !is_array($results)) {
            throw new \RuntimeException("$logPrefix response invalid");
        }
        return $results;
    }

    public function downAndExtractCode($url, array $includes = []): string
    {
        $requireCommand = 'rsync';
        if (!command_exists($requireCommand)) {
            throw new \RuntimeException("command: $requireCommand not exists!");
        }
        $arr = explode('/', $url);
        $basename = last($arr);
        $isZip = false;
        if (Str::contains($basename,'.zip')) {
            $isZip = true;
            $basename = strstr($basename, '.zip', true);
            $suffix = ".zip";
        } else {
            $suffix = '.tar.gz';
        }
        $filename = sprintf('%s/nexusphp-%s-%s%s', sys_get_temp_dir(), $basename, date('YmdHis'), $suffix);
        $this->doLog("download from: $url, save to filename: $filename");
        $client = new Client();
        $response = $client->request('GET', $url, ['sink' => $filename]);
        if (($statusCode = $response->getStatusCode()) != 200) {
            throw new \RuntimeException("Download fail, status code：$statusCode");
        }
        if (($bodySize = $response->getBody()->getSize()) <= 0) {
            throw new \RuntimeException("Download fail, file size：$bodySize");
        }
        if (!file_exists($filename)) {
            throw new \RuntimeException("Download fail, file not exists：$filename");
        }
        if (filesize($filename) <= 0) {
            throw new \RuntimeException("Download fail, file: $filename size = 0");
        }
        $this->doLog('SUCCESS_DOWNLOAD');
        $extractDir = str_replace($suffix, "", $filename);
        $command = "mkdir -p $extractDir";
        $this->executeCommand($command);

        if ($isZip) {
            $command = "unzip -q $filename -d $extractDir";
        } else {
            $command = "tar -xf $filename -C $extractDir";
        }
        $this->executeCommand($command);

        foreach (glob("$extractDir/*") as $path) {
            if (is_dir($path)) {
                $excludes = array_merge(ToolRepository::BACKUP_EXCLUDES, ['public/favicon.ico', '.env', 'public/pic/category/chd/*']);
                if (!in_array('composer', $includes)) {
                    $excludes[] = 'composer.lock';
                    $excludes[] = 'composer.json';
                }
//                $command = sprintf('cp -raf %s/. %s', $path, ROOT_PATH);
                $command = "rsync -rvq $path/ " . ROOT_PATH;
                $command .= " --include=public/vendor";
                foreach ($excludes as $exclude) {
                    $command .= " --exclude=$exclude";
                }
                $this->executeCommand($command);
                //remove original file
                unlink($filename);
                break;
            }
        }
        $this->doLog('SUCCESS_EXTRACT');
        return $extractDir;
    }

    public function initSeedPoints(): int
    {
        $size = 10000;
        $tableName = (new User())->getTable();
        $result = 0;
        do {
            $affectedRows = NexusDB::table($tableName)
                ->whereNull('seed_points')
                ->limit($size)
                ->update([
                    'seed_points' => NexusDB::raw('seedbonus')
                ]);
            $result += $affectedRows;
            $this->doLog("affectedRows: $affectedRows, query: " . last_query());
        } while ($affectedRows > 0);

        return $result;
    }

    public function updateDependencies()
    {
        $command = "composer install -d " . ROOT_PATH;
        $this->executeCommand($command);
        $this->doLog("[COMPOSER INSTALL] SUCCESS");
    }

    public function initTag()
    {
        $priority = count(Tag::DEFAULTS);
        $dateTimeStringNow = date('Y-m-d H:i:s');
        foreach (Tag::DEFAULTS as $value) {
            $attributes = [
                'name' => $value['name'],
            ];
            $values = [
                'priority' => $priority,
                'color' => $value['color'],
                'created_at' => $dateTimeStringNow,
                'updated_at' => $dateTimeStringNow,
            ];
            Tag::query()->firstOrCreate($attributes, $values);
            $priority--;
        }
    }

    private function isSnatchedTableTorrentUserUnique(): bool
    {
        $tableName = 'snatched';
        $result = NexusDB::select('show index from ' . $tableName);
        foreach ($result as $item) {
            if (in_array($item['Column_name'], ['torrentid', 'userid']) && $item['Non_unique'] == 0) {
                return true;
            }
        }
        return false;
    }

    public function updateEnvFile()
    {
        $envFile = ROOT_PATH . '.env';
        $envExample = ROOT_PATH . '.env.example';
        $envData = readEnvFile($envFile);
        $envExampleData = readEnvFile($envExample);
        foreach ($envExampleData as $key => $value) {
            if (!isset($envData[$key])) {
                $envData[$key] = $value;
            }
        }
        $fp = @fopen($envFile, 'w');
        if ($fp === false) {
            throw new \RuntimeException("can't create env file, make sure php has permission to create file at: " . ROOT_PATH);
        }
        $content = "";
        foreach ($envData as $key => $value) {
            $content .= "{$key}={$value}\n";
        }
        fwrite($fp, $content);
        fclose($fp);
    }

    /**
     * 初始化星尘农场作物数据
     */
    private function initStardustCrops()
    {
        $crops = [
            ['name' => '月球', 'name_en' => 'Moon', 'emoji' => '🌙', 'seed_price' => 50, 'grow_duration' => 120, 'fragment_min' => 1, 'fragment_max' => 2, 'experience' => 10, 'level_required' => 1, 'sort_order' => 1],
            ['name' => '水星', 'name_en' => 'Mercury', 'emoji' => '☿️', 'seed_price' => 100, 'grow_duration' => 240, 'fragment_min' => 1, 'fragment_max' => 2, 'experience' => 20, 'level_required' => 1, 'sort_order' => 2],
            ['name' => '金星', 'name_en' => 'Venus', 'emoji' => '♀️', 'seed_price' => 150, 'grow_duration' => 360, 'fragment_min' => 1, 'fragment_max' => 2, 'experience' => 30, 'level_required' => 2, 'sort_order' => 3],
            ['name' => '地球', 'name_en' => 'Earth', 'emoji' => '🌍', 'seed_price' => 200, 'grow_duration' => 480, 'fragment_min' => 1, 'fragment_max' => 2, 'experience' => 40, 'level_required' => 3, 'sort_order' => 4],
            ['name' => '火星', 'name_en' => 'Mars', 'emoji' => '♂️', 'seed_price' => 250, 'grow_duration' => 720, 'fragment_min' => 1, 'fragment_max' => 2, 'experience' => 50, 'level_required' => 4, 'sort_order' => 5],
            ['name' => '木星', 'name_en' => 'Jupiter', 'emoji' => '♃', 'seed_price' => 400, 'grow_duration' => 1440, 'fragment_min' => 1, 'fragment_max' => 3, 'experience' => 80, 'level_required' => 5, 'sort_order' => 6],
            ['name' => '土星', 'name_en' => 'Saturn', 'emoji' => '♄', 'seed_price' => 500, 'grow_duration' => 2160, 'fragment_min' => 1, 'fragment_max' => 3, 'experience' => 100, 'level_required' => 6, 'sort_order' => 7],
            ['name' => '天王星', 'name_en' => 'Uranus', 'emoji' => '⛢', 'seed_price' => 600, 'grow_duration' => 2880, 'fragment_min' => 1, 'fragment_max' => 3, 'experience' => 120, 'level_required' => 7, 'sort_order' => 8],
            ['name' => '海王星', 'name_en' => 'Neptune', 'emoji' => '♆', 'seed_price' => 700, 'grow_duration' => 3600, 'fragment_min' => 2, 'fragment_max' => 4, 'experience' => 150, 'level_required' => 8, 'sort_order' => 9],
            ['name' => '太阳', 'name_en' => 'Sun', 'emoji' => '☀️', 'seed_price' => 2000, 'grow_duration' => 4320, 'fragment_min' => 2, 'fragment_max' => 5, 'experience' => 300, 'level_required' => 10, 'sort_order' => 10],
        ];

        foreach ($crops as $crop) {
            NexusDB::table('stardust_crops')->insert(array_merge($crop, [
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    /**
     * 初始化星尘农场成就数据
     */
    private function initStardustAchievements()
    {
        $achievements = [
            [
                'name' => '重建太阳系',
                'name_en' => 'Rebuild Solar System',
                'description' => '集齐9大天体（8大行星+太阳）各1个完整行星',
                'icon' => '🌟',
                'type' => 'collect',
                'conditions' => json_encode(['planets' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'each_count' => 1]),
                'reward_stardust' => 10000,
                'is_repeatable' => true,
                'sort_order' => 1,
            ],
            [
                'name' => '星际农夫',
                'name_en' => 'Star Farmer',
                'description' => '累计收获100个碎片',
                'icon' => '👨‍🌾',
                'type' => 'collect',
                'conditions' => json_encode(['total_fragments' => 100]),
                'reward_stardust' => 500,
                'is_repeatable' => false,
                'sort_order' => 2,
            ],
            [
                'name' => '资深农夫',
                'name_en' => 'Veteran Farmer',
                'description' => '累计收获500个碎片',
                'icon' => '🎖️',
                'type' => 'collect',
                'conditions' => json_encode(['total_fragments' => 500]),
                'reward_stardust' => 2000,
                'is_repeatable' => false,
                'sort_order' => 3,
            ],
            [
                'name' => '好邻居',
                'name_en' => 'Good Neighbor',
                'description' => '帮助好友浇水50次',
                'icon' => '💧',
                'type' => 'interaction',
                'conditions' => json_encode(['water_times' => 50]),
                'reward_stardust' => 300,
                'is_repeatable' => false,
                'sort_order' => 4,
            ],
            [
                'name' => '神秘访客',
                'name_en' => 'Mysterious Visitor',
                'description' => '访问100个不同的农场',
                'icon' => '👣',
                'type' => 'interaction',
                'conditions' => json_encode(['visit_unique_farms' => 100]),
                'reward_stardust' => 500,
                'is_repeatable' => false,
                'sort_order' => 5,
            ],
            [
                'name' => '星际大盗',
                'name_en' => 'Star Thief',
                'description' => '累计偷取100个碎片',
                'icon' => '🦹',
                'type' => 'interaction',
                'conditions' => json_encode(['steal_times' => 100]),
                'reward_stardust' => 800,
                'is_repeatable' => false,
                'sort_order' => 6,
            ],
            [
                'name' => '首次收获',
                'name_en' => 'First Harvest',
                'description' => '完成第一次作物收获',
                'icon' => '🌱',
                'type' => 'special',
                'conditions' => json_encode(['first_harvest' => true]),
                'reward_stardust' => 50,
                'is_repeatable' => false,
                'sort_order' => 7,
            ],
            [
                'name' => '土地大亨',
                'name_en' => 'Land Tycoon',
                'description' => '拥有12块土地',
                'icon' => '🏆',
                'type' => 'special',
                'conditions' => json_encode(['land_slots' => 12]),
                'reward_stardust' => 1000,
                'is_repeatable' => false,
                'sort_order' => 8,
            ],
            [
                'name' => '太阳收藏家',
                'name_en' => 'Sun Collector',
                'description' => '拥有10个完整的太阳',
                'icon' => '☀️',
                'type' => 'collect',
                'conditions' => json_encode(['planet_id' => 10, 'count' => 10]),
                'reward_stardust' => 5000,
                'is_repeatable' => false,
                'sort_order' => 9,
            ],
            [
                'name' => '财富自由',
                'name_en' => 'Wealthy',
                'description' => '拥有100000星尘',
                'icon' => '💰',
                'type' => 'special',
                'conditions' => json_encode(['stardust' => 100000]),
                'reward_stardust' => 10000,
                'is_repeatable' => false,
                'sort_order' => 10,
            ],
        ];

        foreach ($achievements as $achievement) {
            NexusDB::table('stardust_achievements')->insert(array_merge($achievement, [
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

}

