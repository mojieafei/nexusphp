<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//loggedinorreturn();

/**
 * 动态更新等级提升说明中的时间、下载量、分享率和做种积分阈值（按照settings.php的方式）
 * @param string $answer FAQ答案的HTML内容
 * @return string 更新后的HTML内容
 */
function add_seed_points_to_promotion_faq($answer) {
	// 获取账号设置（和settings.php一样的方式）
	$ACCOUNT = get_setting_from_db('account', []);
	
	// 等级映射：等级名称 => [输入前缀, 等级常量, 默认时间, 默认下载量, 默认分享率, 默认降级分享率, 默认做种积分]
	// 和settings.php中的promotion_criteria调用保持一致
	$classMapping = [
		'PowerUser' => ['pu', UC_POWER_USER, 4, 50, 1.05, 0.95, \App\Models\User::$classes[UC_POWER_USER]['min_seed_points'] ?? 0],
		'EliteUser' => ['eu', UC_ELITE_USER, 8, 120, 1.55, 1.45, \App\Models\User::$classes[UC_ELITE_USER]['min_seed_points'] ?? 0],
		'CrazyUser' => ['cu', UC_CRAZY_USER, 15, 300, 2.05, 1.95, \App\Models\User::$classes[UC_CRAZY_USER]['min_seed_points'] ?? 0],
		'InsaneUser' => ['iu', UC_INSANE_USER, 25, 500, 2.55, 2.45, \App\Models\User::$classes[UC_INSANE_USER]['min_seed_points'] ?? 0],
		'VeteranUser' => ['vu', UC_VETERAN_USER, 40, 750, 3.05, 2.95, \App\Models\User::$classes[UC_VETERAN_USER]['min_seed_points'] ?? 0],
		'ExtremeUser' => ['exu', UC_EXTREME_USER, 60, 1024, 3.55, 3.45, \App\Models\User::$classes[UC_EXTREME_USER]['min_seed_points'] ?? 0],
		'UltimateUser' => ['uu', UC_ULTIMATE_USER, 80, 1536, 4.05, 3.95, \App\Models\User::$classes[UC_ULTIMATE_USER]['min_seed_points'] ?? 0],
		'NexusMaster' => ['nm', UC_NEXUS_MASTER, 100, 3072, 4.55, 4.45, \App\Models\User::$classes[UC_NEXUS_MASTER]['min_seed_points'] ?? 0],
	];
	
	// 为每个等级动态替换值
	foreach ($classMapping as $className => $classInfo) {
		$inputPrefix = $classInfo[0];
		$class = $classInfo[1];
		$defaultTime = $classInfo[2];
		$defaultDl = $classInfo[3];
		$defaultPrRatio = $classInfo[4];
		$defaultDeRatio = $classInfo[5];
		$defaultSeedPoints = $classInfo[6];
		
		// 获取配置值（和settings.php一样的逻辑）
		$inputtime = $inputPrefix . "time";
		$inputdl = $inputPrefix . "dl";
		$inputprratio = $inputPrefix . "prratio";
		$inputderatio = $inputPrefix . "deratio";
		$inputSeedPoints = $class . "_min_seed_points";
		
		$time = isset($ACCOUNT[$inputtime]) ? (int)$ACCOUNT[$inputtime] : $defaultTime;
		$dl = isset($ACCOUNT[$inputdl]) ? (int)$ACCOUNT[$inputdl] : $defaultDl;
		$prRatio = isset($ACCOUNT[$inputprratio]) ? (float)$ACCOUNT[$inputprratio] : $defaultPrRatio;
		$deRatio = isset($ACCOUNT[$inputderatio]) ? (float)$ACCOUNT[$inputderatio] : $defaultDeRatio;
		$seedPoints = isset($ACCOUNT[$inputSeedPoints]) ? (int)$ACCOUNT[$inputSeedPoints] : $defaultSeedPoints;
		
		// 格式化下载量（GB或TB）
		$dlText = '';
		if ($dl >= 1024) {
			$tbValue = $dl / 1024;
			// 如果是整数，显示整数；否则显示一位小数
			if ($tbValue == floor($tbValue)) {
				$dlText = (int)$tbValue . 'TB';
			} else {
				$dlText = number_format($tbValue, 1) . 'TB';
			}
		} else {
			$dlText = $dl . 'G';
		}
		
		// 匹配包含该等级名称的tr标签，然后找到包含"必须注册至少"的td标签（内容所在的td）
		$pattern = '/(<tr[^>]*>.*?<b[^>]*class="' . preg_quote($className, '/') . '_Name"[^>]*>.*?)(<td[^>]*>.*?必须注册至少.*?<\/td>)(.*?<\/tr>)/s';
		$answer = preg_replace_callback($pattern, function($matches) use ($time, $dlText, $prRatio, $deRatio, $seedPoints) {
			$beforeContentTd = $matches[1]; // tr开始到内容td之前
			$contentTd = $matches[2]; // 包含内容的td标签
			$afterContentTd = $matches[3]; // 内容td之后到tr结束
			
			// 在td内容中替换
			// 替换"注册至少X周" - 只替换数字，保留"注册至少"和"周"
			$contentTd = preg_replace('/(注册至少)\d+(周)/u', '${1}' . $time . '${2}', $contentTd);
			
			// 替换"下载至少XG"或"下载至少XTB"或"下载至少X.5TB" - 替换数字+单位，保留"下载至少"
			$contentTd = preg_replace('/(下载至少)[\d.]+[GT]B?/u', '${1}' . $dlText, $contentTd);
			
			// 替换"分享率大于X" - 只替换数字，保留"分享率大于"
			$contentTd = preg_replace('/(分享率大于)[\d.]+/u', '${1}' . $prRatio, $contentTd);
			
			// 替换"分享率低于X" - 只替换数字，保留"分享率低于"
			$contentTd = preg_replace('/(分享率低于)[\d.]+/u', '${1}' . $deRatio, $contentTd);
			
			// 如果做种积分阈值大于0，添加"做种积分大于X"（在"分享率大于X"之后）
			if ($seedPoints > 0) {
				$contentTd = preg_replace('/(分享率大于[\d.]+)([。，<])/u', '${1}，做种积分大于' . number_format($seedPoints) . '${2}', $contentTd);
			}
			
			return $beforeContentTd . $contentTd . $afterContentTd;
		}, $answer);
	}
	
	return $answer;
}

stdhead($lang_faq['head_faq']);
// 如果URL中有nocache参数，清除缓存
if (isset($_GET['nocache'])) {
	$Cache->delete_value('faq');
	$Cache->delete_value('faq_page');
}
$Cache->new_page('faq', 900, true);
if (!$Cache->get_page())
{
$Cache->add_whole_row();
//make_folder("cache/" , get_langfolder_cookie());
//cache_check ('faq');
begin_main_frame();

begin_frame($lang_faq['text_welcome_to'].$SITENAME." - ".$SLOGAN);
echo sprintf($lang_faq['text_welcome_content_one'].sprintf($lang_faq['text_welcome_content_two'], \App\Models\Setting::getSiteName(), \App\Models\Setting::getSiteName()));
end_frame();

$lang_id = get_guest_lang_id();
$is_rulelang = get_single_value("language","rule_lang","WHERE id = ".sqlesc($lang_id));
if (!$is_rulelang){
	$lang_id = 6; //English
}
$res = sql_query("SELECT `id`, `link_id`, `question`, `flag` FROM `faq` WHERE `type`='categ' AND `lang_id` = ".sqlesc($lang_id)." ORDER BY `order` ASC");
while ($arr = mysql_fetch_array($res)) {
	$faq_categ[$arr['link_id']]['title'] = $arr['question'];
	$faq_categ[$arr['link_id']]['flag'] = $arr['flag'];
	$faq_categ[$arr['link_id']]['link_id'] = $arr['link_id'];
}

$res = sql_query("SELECT `id`, `link_id`, `question`, `answer`, `flag`, `categ` FROM `faq` WHERE `type`='item' AND `lang_id` = ".sqlesc($lang_id)." ORDER BY `order` ASC");
while ($arr = mysql_fetch_array($res)) {
	$faq_categ[$arr['categ']]['items'][$arr['id']]['question'] = $arr['question'];
	$faq_categ[$arr['categ']]['items'][$arr['id']]['answer'] = $arr['answer'];
	$faq_categ[$arr['categ']]['items'][$arr['id']]['flag'] = $arr['flag'];
	$faq_categ[$arr['categ']]['items'][$arr['id']]['link_id'] = $arr['link_id'];
}

if (isset($faq_categ)) {
	// gather orphaned items
	/*
	foreach ($faq_categ as $id => $temp)
	{
		if (!array_key_exists("title", $faq_categ[$id]))
		{
			foreach ($faq_categ[$id]['items'] as $id2 => $temp)
			{
				$faq_orphaned[$id2]['question'] = $faq_categ[$id]['items'][$id2]['question'];
				$faq_orphaned[$id2][answer] = $faq_categ[$id]['items'][$id2][answer];
				$faq_orphaned[$id2]['flag'] = $faq_categ[$id]['items'][$id2]['flag'];
				unset($faq_categ[$id]);
			}
		}
	}
	*/

	begin_frame("<span id=\"top\">".$lang_faq['text_contents'] . "</span>");
	foreach ($faq_categ as $id => $temp)
	{
		if ($faq_categ[$id]['flag'] == "1")
		{
			print("<ul><li><a href=\"#id". $faq_categ[$id]['link_id'] ."\"><b>". $faq_categ[$id]['title'] ."</b></a><ul>\n");
   			if (array_key_exists("items", $faq_categ[$id]))
			{
    				foreach ($faq_categ[$id]['items'] as $id2 => $temp)
				{
	 				if ($faq_categ[$id]['items'][$id2]['flag'] == "1") print("<li><a href=\"#id". $faq_categ[$id]['items'][$id2]['link_id'] ."\" class=\"faqlink\">". $faq_categ[$id]['items'][$id2]['question'] ."</a></li>\n");
	 				elseif ($faq_categ[$id]['items'][$id2]['flag'] == "2") print("<li><a href=\"#id". $faq_categ[$id]['items'][$id2]['link_id'] ."\" class=\"faqlink\">". $faq_categ[$id]['items'][$id2]['question'] ."</a> <img class=\"faq_updated\" src=\"pic/trans.gif\" alt=\"Updated\" /></li>\n");
	 				elseif ($faq_categ[$id]['items'][$id2]['flag'] == "3") print("<li><a href=\"#id". $faq_categ[$id]['items'][$id2]['link_id'] ."\" class=\"faqlink\">". $faq_categ[$id]['items'][$id2]['question'] ."</a> <img class=\"faq_new\" src=\"pic/trans.gif\" alt=\"New\" /></li>\n");
    				}
			}
			print("</ul></li></ul><br />");
		}
	}
	end_frame();

	foreach ($faq_categ as $id => $temp) {
		if ($faq_categ[$id]['flag'] == "1")
		{
			$frame = $faq_categ[$id]['title'] ." - <a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>";
			begin_frame($frame);
			print("<span id=\"id". $faq_categ[$id]['link_id'] ."\"></span>");
			if (array_key_exists("items", $faq_categ[$id]))
			{
				foreach ($faq_categ[$id]['items'] as $id2 => $temp)
				{
					if ($faq_categ[$id]['items'][$id2]['flag'] != "0")
					{
						print("<br /><span id=\"id".$faq_categ[$id]['items'][$id2]['link_id']."\"><b>". $faq_categ[$id]['items'][$id2]['question'] ."</b></span><br />\n");
						$answer = $faq_categ[$id]['items'][$id2]['answer'];
						// 如果是等级提升说明的问题（link_id = 23），添加做种积分阈值信息
						if ($faq_categ[$id]['items'][$id2]['link_id'] == 23) {
							$answer = add_seed_points_to_promotion_faq($answer);
						}
						print("<br />". $answer ."\n<br /><br />\n");
					}
				}
			}
			end_frame();
		}
	}
}
end_main_frame();
	$Cache->end_whole_row();
	$Cache->cache_page();
}
echo $Cache->next_row();
//cache_save ('faq');
stdfoot();
?>
