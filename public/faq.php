<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//loggedinorreturn();

/**
 * 在等级提升说明中添加做种积分阈值信息
 * @param string $answer FAQ答案的HTML内容
 * @return string 添加了做种积分阈值后的HTML内容
 */
function add_seed_points_to_promotion_faq($answer) {
	// 获取账号设置（和settings.php一样的方式）
	$ACCOUNT = get_setting_from_db('account', []);
	
	// 等级映射：等级名称 => [等级常量, 默认值]
	$classMapping = [
		'PowerUser' => [UC_POWER_USER, \App\Models\User::$classes[UC_POWER_USER]['min_seed_points'] ?? 0],
		'EliteUser' => [UC_ELITE_USER, \App\Models\User::$classes[UC_ELITE_USER]['min_seed_points'] ?? 0],
		'CrazyUser' => [UC_CRAZY_USER, \App\Models\User::$classes[UC_CRAZY_USER]['min_seed_points'] ?? 0],
		'InsaneUser' => [UC_INSANE_USER, \App\Models\User::$classes[UC_INSANE_USER]['min_seed_points'] ?? 0],
		'VeteranUser' => [UC_VETERAN_USER, \App\Models\User::$classes[UC_VETERAN_USER]['min_seed_points'] ?? 0],
		'ExtremeUser' => [UC_EXTREME_USER, \App\Models\User::$classes[UC_EXTREME_USER]['min_seed_points'] ?? 0],
		'UltimateUser' => [UC_ULTIMATE_USER, \App\Models\User::$classes[UC_ULTIMATE_USER]['min_seed_points'] ?? 0],
		'NexusMaster' => [UC_NEXUS_MASTER, \App\Models\User::$classes[UC_NEXUS_MASTER]['min_seed_points'] ?? 0],
	];
	
	// 为每个等级添加做种积分阈值
	foreach ($classMapping as $className => $classInfo) {
		$class = $classInfo[0];
		$defaultSeedPoints = $classInfo[1];
		// 配置键名格式和settings.php一样：$class . "_min_seed_points"
		$configKey = $class . '_min_seed_points';
		// 获取做种积分阈值（和settings.php一样的逻辑）
		$seedPoints = isset($ACCOUNT[$configKey]) ? (int)$ACCOUNT[$configKey] : $defaultSeedPoints;
		
		// 如果做种积分阈值大于0，则添加
		if ($seedPoints > 0) {
			// 在包含该等级名称的tr标签中，找到"分享率大于数字"的位置，在其前面添加"，做种积分大于X"
			// 匹配模式：在包含该等级名称的tr标签中，找到"分享率大于数字"
			$pattern = '/(<tr[^>]*>.*?<b[^>]*class="' . preg_quote($className, '/') . '_Name"[^>]*>.*?分享率大于[\d.]+)([。，<])/s';
			$replacement = '$1，做种积分大于' . number_format($seedPoints) . '$2';
			$answer = preg_replace($pattern, $replacement, $answer);
		}
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
