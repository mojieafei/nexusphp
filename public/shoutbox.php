<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
if (isset($_GET['del']))
{
	if (is_valid_id($_GET['del']))
	{
		if(user_can('sbmanage'))
		{
			sql_query("DELETE FROM shoutbox WHERE id=".mysql_real_escape_string($_GET['del']));
		}
	}
}
$where=$_GET["type"] ?? '';
$refresh = ($CURUSER['sbrefresh'] ?? 120)
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Refresh" content="<?php echo $refresh?>; url=<?php echo get_protocol_prefix() . $BASEURL?>/shoutbox.php?type=<?php echo htmlspecialchars($where)?>">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo get_css_uri()."theme.css"?>" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><style type="text/css">body {overflow-y:scroll; overflow-x: hidden}</style>
<?php
print(get_style_addicode());
$startcountdown = "startcountdown(".$refresh.")";
?>
<script type="text/javascript">
//<![CDATA[
var t;
function startcountdown(time)
{
parent.document.getElementById('countdown').innerHTML=time;
time=time-1;
t=setTimeout("startcountdown("+time+")",1000);
}
function countdown(time)
{
	if (time <= 0){
	parent.document.getElementById("hbtext").disabled=false;
	parent.document.getElementById("hbsubmit").disabled=false;
	parent.document.getElementById("hbsubmit").value=parent.document.getElementById("sbword").innerHTML;
	}
	else {
	parent.document.getElementById("hbsubmit").value=time;
	time=time-1;
	setTimeout("countdown("+time+")", 1000);
	}
}
function hbquota(){
parent.document.getElementById("hbtext").disabled=true;
parent.document.getElementById("hbsubmit").disabled=true;
var time=10;
countdown(time);
//]]>
}
</script>
</head>
<body class='inframe' <?php if (isset($_GET["type"]) && $_GET["type"] != "helpbox"){?> onload="<?php echo $startcountdown?>" <?php } else {?> onload="hbquota()" <?php } ?>>
<?php
if(isset($_GET["sent"]) && $_GET["sent"]=="yes"){
if(!isset($_GET["shbox_text"]) || !$_GET['shbox_text'])
{
	$userid=intval($CURUSER["id"] ?? 0);
}
else
{
	if($_GET["type"]=="helpbox")
	{
		if ($showhelpbox_main != 'yes'){
            do_log("Someone is hacking shoutbox. helpbox_disabled - IP : ".getip());
			die($lang_shoutbox['text_helpbox_disabled']);
		}
		$userid=0;
		$type='hb';
	}
	elseif ($_GET["type"] == 'shoutbox')
	{
		$userid=intval($CURUSER["id"] ?? 0);
		if (!$userid){
            do_log("Someone is hacking shoutbox. no_permission_to_shoutbox - IP : ".getip());
			die($lang_shoutbox['text_no_permission_to_shoutbox']);
		}
		if (!empty($_GET["toguest"]))
			$type ='hb';
		else $type = 'sb';
	}
	$date=sqlesc(time());
	$text=trim($_GET["shbox_text"]);
    // admin（用户ID=1）不受发言频率限制
    if (!isset($userid) || $userid != 1) {
        if (isset($userid) && $userid > 0) {
            $lock = new \Nexus\Database\NexusLock("shoutbox:$userid", 60);
        } else {
            $lock = new \Nexus\Database\NexusLock("shoutbox:" . getip(), 60);
        }
        if (!$lock->acquire()) {
            die($lang_shoutbox['speaking_too_often']);
        }
    }
	sql_query("INSERT INTO shoutbox (userid, date, text, type) VALUES (" . sqlesc($userid) . ", $date, " . sqlesc($text) . ", ".sqlesc($type).")") or sqlerr(__FILE__, __LINE__);
	
	// 检查是否包含"求魔力"或"求上传"，并随机发放奖励
	if ($userid > 0 && isset($text) && !empty($text)) {
		$textLower = mb_strtolower($text, 'UTF-8');
		$currentTime = time();
		
		// 检查"求魔力"
		if (mb_strpos($textLower, '求魔力') !== false || mb_strpos($textLower, '求魔力值') !== false) {
			$cacheKey = "shoutbox_bonus_gift_{$userid}";
			$lastGiftTime = $Cache->get_value($cacheKey);
			
			// 检查冷却时间：24小时 = 86400秒
			if ($lastGiftTime === false || ($currentTime - $lastGiftTime) >= 86400) {
				$rand = mt_rand(1, 100);
				$bonusAmount = 0;
				
				// 概率计算：1% -> 10% -> 30%，总共41%概率
				if ($rand == 1) {
					// 1%概率：2000-3000魔力
					$bonusAmount = mt_rand(2000, 3000);
				} elseif ($rand >= 2 && $rand <= 11) {
					// 10%概率：1000-2000魔力
					$bonusAmount = mt_rand(1000, 2000);
				} elseif ($rand >= 12 && $rand <= 41) {
					// 30%概率：1-1000魔力
					$bonusAmount = mt_rand(1, 1000);
				}
				
				// 调试日志
				do_log("Shoutbox bonus check: userid={$userid}, rand={$rand}, bonusAmount={$bonusAmount}, lastGiftTime=" . ($lastGiftTime ?: 'none'), 'debug');
				
				if ($bonusAmount > 0) {
					// 发放魔力值
					KPS("+", $bonusAmount, $userid);
					
					// 记录赠送时间（只有成功发放才记录）
					$Cache->cache_value($cacheKey, $currentTime, 86400);
					
					// 获取用户名
					$userInfo = sql_query("SELECT username FROM users WHERE id=" . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
					$userRow = mysql_fetch_assoc($userInfo);
					$username = $userRow['username'] ?? '用户';
					
					// 调侃消息模板
					$messages = [
						"{$username}的感言感动了神明，获得了{$bonusAmount}点魔力值！",
						"神明被{$username}的真诚打动，赐予了{$bonusAmount}点魔力值！",
						"{$username}的祈祷得到了回应，神明送来了{$bonusAmount}点魔力值！",
						"天降祥瑞！{$username}获得了神明赠送的{$bonusAmount}点魔力值！",
						"{$username}的诚心感动了天地，获得了{$bonusAmount}点魔力值奖励！",
						"神明听到了{$username}的呼唤，慷慨地送出了{$bonusAmount}点魔力值！",
						"{$username}的愿望实现了！神明赐予了{$bonusAmount}点魔力值！",
						"奇迹发生了！{$username}获得了神明赠送的{$bonusAmount}点魔力值！",
						"{$username}的虔诚打动了神明，获得了{$bonusAmount}点魔力值！",
						"神明被{$username}的坚持感动，送出了{$bonusAmount}点魔力值！"
					];
					$adminMessage = $messages[array_rand($messages)];
					
					// 以admin身份（用户ID=1）发送消息到群聊区
					sql_query("INSERT INTO shoutbox (userid, date, text, type) VALUES (1, " . sqlesc($currentTime) . ", " . sqlesc($adminMessage) . ", " . sqlesc($type) . ")") or sqlerr(__FILE__, __LINE__);
					
					do_log("Shoutbox bonus granted: userid={$userid}, bonusAmount={$bonusAmount}", 'info');
				}
			} else {
				// 调试日志：冷却时间未到
				$remainingTime = 86400 - ($currentTime - $lastGiftTime);
				do_log("Shoutbox bonus cooldown: userid={$userid}, remainingTime={$remainingTime} seconds", 'debug');
			}
		}
		
		// 检查"求上传"
		if (mb_strpos($textLower, '求上传') !== false || mb_strpos($textLower, '求上传量') !== false) {
			$cacheKey = "shoutbox_upload_gift_{$userid}";
			$lastGiftTime = $Cache->get_value($cacheKey);
			
			// 检查冷却时间：168小时 = 604800秒
			if ($lastGiftTime === false || ($currentTime - $lastGiftTime) >= 604800) {
				$rand = mt_rand(1, 100);
				$uploadAmount = 0;
				
				if ($rand <= 1) {
					// 1%概率：200-300MB
					$uploadAmount = mt_rand(200, 300);
				} elseif ($rand <= 11) {
					// 10%概率：100-200MB
					$uploadAmount = mt_rand(100, 200);
				} elseif ($rand <= 41) {
					// 30%概率：50MB
					$uploadAmount = 50;
				}
				
				if ($uploadAmount > 0) {
					// 转换为字节并发放上传量
					$uploadBytes = getsize_int($uploadAmount, "M");
					sql_query("UPDATE users SET uploaded = uploaded + " . sqlesc($uploadBytes) . " WHERE id = " . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
					
					// 记录赠送时间
					$Cache->cache_value($cacheKey, $currentTime, 604800);
					
					// 获取用户名
					$userInfo = sql_query("SELECT username FROM users WHERE id=" . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
					$userRow = mysql_fetch_assoc($userInfo);
					$username = $userRow['username'] ?? '用户';
					
					// 调侃消息模板
					$messages = [
						"{$username}的感言感动了神明，获得了{$uploadAmount}MB上传量！",
						"神明被{$username}的真诚打动，赐予了{$uploadAmount}MB上传量！",
						"{$username}的祈祷得到了回应，神明送来了{$uploadAmount}MB上传量！",
						"天降祥瑞！{$username}获得了神明赠送的{$uploadAmount}MB上传量！",
						"{$username}的诚心感动了天地，获得了{$uploadAmount}MB上传量奖励！",
						"神明听到了{$username}的呼唤，慷慨地送出了{$uploadAmount}MB上传量！",
						"{$username}的愿望实现了！神明赐予了{$uploadAmount}MB上传量！",
						"奇迹发生了！{$username}获得了神明赠送的{$uploadAmount}MB上传量！",
						"{$username}的虔诚打动了神明，获得了{$uploadAmount}MB上传量！",
						"神明被{$username}的坚持感动，送出了{$uploadAmount}MB上传量！"
					];
					$adminMessage = $messages[array_rand($messages)];
					
					// 以admin身份（用户ID=1）发送消息到群聊区
					sql_query("INSERT INTO shoutbox (userid, date, text, type) VALUES (1, " . sqlesc($currentTime) . ", " . sqlesc($adminMessage) . ", " . sqlesc($type) . ")") or sqlerr(__FILE__, __LINE__);
				}
			}
		}
	}
	
	print "<script type=\"text/javascript\">parent.document.forms['shbox'].shbox_text.value='';</script>";
}
}

$limit = ($CURUSER['sbnum'] ?? 70);
if ($where == "helpbox" && $showhelpbox_main == 'yes') {
    //request helpbox, not require login
    $sql = "SELECT * FROM shoutbox WHERE type='hb' ORDER BY date DESC LIMIT ".$limit;
} elseif ($where == "shoutbox" && isset($CURUSER) && ($CURUSER['hidehb'] == 'yes' || $showhelpbox_main != 'yes')) {
    //request shoutbox, exclude helpbox content, require login
    $sql = "SELECT * FROM shoutbox WHERE type='sb' ORDER BY date DESC LIMIT ".$limit;
} elseif (isset($CURUSER)) {
    $sql = "SELECT * FROM shoutbox ORDER BY date DESC LIMIT ".$limit;
} else {
    die("<h1>".$lang_shoutbox['std_access_denied']."</h1>"."<p>".$lang_shoutbox['std_access_denied_note']."</p></body></html>");
}
$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
if (mysql_num_rows($res) == 0)
print("\n");
else
{
	print("<table border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>\n");

	while ($arr = mysql_fetch_assoc($res))
	{
        $del = '';
		if (user_can('sbmanage')) {
			$del .= "[<a href=\"shoutbox.php?del=".$arr['id']."\">".$lang_shoutbox['text_del']."</a>]";
		}
		if ($arr["userid"]) {
			$username = get_username($arr["userid"],false,true,true,true,false,false,"",true);
			if (isset($arr["type"]) && isset($_GET['type']) && $_GET["type"] != 'helpbox' && $arr["type"] == 'hb')
				$username .= $lang_shoutbox['text_to_guest'];
			}
		else $username = $lang_shoutbox['text_guest'];
		if (isset($CURUSER) && $CURUSER['timetype'] != 'timealive')
			$time = (new DateTime())->setTimestamp($arr["date"])->format('m.d H:i');
		else $time = get_elapsed_time($arr["date"]).$lang_shoutbox['text_ago'];
		print("<tr><td class=\"shoutrow\"><span class='date'>[".$time."]</span> ".
$del ." ". $username." " . format_comment($arr["text"],true,false,true,true,600,false,false)."
</td></tr>\n");
	}
	print("</table>");
}
?>
</body>
</html>
