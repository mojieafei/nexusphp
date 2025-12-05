<?php
require_once('../include/bittorrent.php');
dbconn();
require_once(get_langfile_path());
//require(get_langfile_path("",true));
loggedinorreturn();
parked();

function bonusarray($option = 0){
	global $onegbupload_bonus,$fivegbupload_bonus,$tengbupload_bonus,$oneinvite_bonus,$customtitle_bonus,$vipstatus_bonus, $basictax_bonus, $taxpercentage_bonus, $bonusnoadpoint_advertisement, $bonusnoadtime_advertisement;
	global $lang_mybonus;

	$results = [];
    //1.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $onegbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 1073741824;
    $bonus['name'] = $lang_mybonus['text_uploaded_one'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
	$results[] = $bonus;

    //5.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $fivegbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 5368709120;
    $bonus['name'] = $lang_mybonus['text_uploaded_two'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;


    //10.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $tengbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 10737418240;
    $bonus['name'] = $lang_mybonus['text_uploaded_three'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;

    //100.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = get_setting('bonus.hundredgbupload');
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 107374182400;
    $bonus['name'] = $lang_mybonus['text_uploaded_four'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;

    //10.0 GB Downloaded
    $bonus = array();
    $bonus['points'] = get_setting('bonus.tengbdownload');
    $bonus['art'] = 'traffic_downloaded';
    $bonus['menge'] = 10737418240;
    $bonus['name'] = $lang_mybonus['text_downloaded_ten_gb'];
    $bonus['description'] = $lang_mybonus['text_download_note'];
    $results[] = $bonus;

    //100.0 GB Downloaded
    $bonus = array();
    $bonus['points'] = get_setting('bonus.hundredgbdownload');
    $bonus['art'] = 'traffic_downloaded';
    $bonus['menge'] = 107374182400;
    $bonus['name'] = $lang_mybonus['text_downloaded_hundred_gb'];
    $bonus['description'] = $lang_mybonus['text_download_note'];
    $results[] = $bonus;

    //Invite
    if ($oneinvite_bonus > 0){
        $bonus = array();
        $bonus['points'] = $oneinvite_bonus;
        $bonus['art'] = 'invite';
        $bonus['menge'] = 1;
        $bonus['name'] = $lang_mybonus['text_buy_invite'];
        $bonus['description'] = $lang_mybonus['text_buy_invite_note'];
        $results[] = $bonus;
    }

    //Tmp Invite
    $tmpInviteBonus = \App\Models\BonusLogs::getBonusForBuyTemporaryInvite();
    if ($tmpInviteBonus > 0) {
        $bonus = array();
        $bonus['points'] = $tmpInviteBonus;
        $bonus['art'] = 'tmp_invite';
        $bonus['menge'] = 1;
        $bonus['name'] = $lang_mybonus['text_buy_tmp_invite'];
        $bonus['description'] = $lang_mybonus['text_buy_tmp_invite_note'];
        $results[] = $bonus;
    }

    //Custom Title
    $bonus = array();
    $bonus['points'] = $customtitle_bonus;
    $bonus['art'] = 'title';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_custom_title'];
    $bonus['description'] = $lang_mybonus['text_custom_title_note'];
    $results[] = $bonus;


    //VIP Status
    $bonus = array();
    $bonus['points'] = $vipstatus_bonus;
    $bonus['art'] = 'class';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_vip_status'];
    $bonus['description'] = $lang_mybonus['text_vip_status_note'];
    $results[] = $bonus;

    //Bonus Gift
    $bonus = array();
    $bonus['points'] = 100;
    $bonus['art'] = 'gift_1';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_bonus_gift'];
    $bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
    if ($basictax_bonus || $taxpercentage_bonus){
        $onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
        $bonus['description'] .= "<br /><br />".$lang_mybonus['text_system_charges_receiver']."<b>".($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].add_s($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : "") : "").($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : "")."</b>".$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
    }
    $results[] = $bonus;


    //No ad for 15 days
    $bonus = array();
    $bonus['points'] = $bonusnoadpoint_advertisement;
    $bonus['art'] = 'noad';
    $bonus['menge'] = $bonusnoadtime_advertisement * 86400;
    $bonus['name'] = $bonusnoadtime_advertisement.$lang_mybonus['text_no_advertisements'];
    $bonus['description'] = $lang_mybonus['text_no_advertisements_note'];
    $results[] = $bonus;

    //Attendance card
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyAttendanceCard();
    $bonus['art'] = 'attendance_card';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_attendance_card'];
    $bonus['description'] = $lang_mybonus['text_attendance_card_note'];
    $results[] = $bonus;

    //Rainbow ID
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyRainbowId();
    $bonus['art'] = 'rainbow_id';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_buy_rainbow_id'];
    $bonus['description'] = $lang_mybonus['text_buy_rainbow_id_note'];
    $results[] = $bonus;

    //Change username card
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyChangeUsernameCard();
    $bonus['art'] = 'change_username_card';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_buy_change_username_card'];
    $bonus['description'] = $lang_mybonus['text_buy_change_username_card_note'];
    $results[] = $bonus;

    //Donate
    $bonus = array();
    $bonus['points'] = 1000;
    $bonus['art'] = 'gift_2';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_charity_giving'];
    $bonus['description'] = $lang_mybonus['text_charity_giving_note'];
    $results[] = $bonus;


    //Cancel hit and run
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForCancelHitAndRun();
    $bonus['art'] = 'cancel_hr';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
    $bonus['description'] = '<p>
            <span style="">' . $lang_mybonus['text_cancel_hr_label'] . '</span>
            <input type="number" name="hr_id" />
        </p>';
    $results[] = $bonus;

    //Buy medal
    //migrate to medal.php since v1.8
//    $medals = \App\Models\Medal::query()->where('get_type', \App\Models\Medal::GET_TYPE_EXCHANGE)->get();
//    foreach ($medals as $medal) {
//        $results[] = [
//            'points' => $medal->price,
//            'art' => 'buy_medal',
//            'menge' => 0,
//            'name' => $medal->name,
//            'description' => sprintf(
//                '<div style="display: flex;align-items: center"><div style="padding: 10px">%s</div><div><img src="%s" style="max-height: 120px"/></div></div><input type="hidden" name="medal_id" value="%s">',
//                $medal->description, $medal->image_large, $medal->id
//            ),
//            'medal_id' => $medal->id,
//        ];
//    }

    return $results;

//
//	switch ($option)
//	{
//		case 1: {//1.0 GB Uploaded
//			$bonus['points'] = $onegbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 1073741824;
//			$bonus['name'] = $lang_mybonus['text_uploaded_one'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 2: {//5.0 GB Uploaded
//			$bonus['points'] = $fivegbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 5368709120;
//			$bonus['name'] = $lang_mybonus['text_uploaded_two'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 3: {//10.0 GB Uploaded
//			$bonus['points'] = $tengbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 10737418240;
//			$bonus['name'] = $lang_mybonus['text_uploaded_three'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 4: {//Invite
//			$bonus['points'] = $oneinvite_bonus;
//			$bonus['art'] = 'invite';
//			$bonus['menge'] = 1;
//			$bonus['name'] = $lang_mybonus['text_buy_invite'];
//			$bonus['description'] = $lang_mybonus['text_buy_invite_note'];
//			break;
//			}
//		case 5: {//Custom Title
//			$bonus['points'] = $customtitle_bonus;
//			$bonus['art'] = 'title';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_custom_title'];
//			$bonus['description'] = $lang_mybonus['text_custom_title_note'];
//			break;
//			}
//		case 6: {//VIP Status
//			$bonus['points'] = $vipstatus_bonus;
//			$bonus['art'] = 'class';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_vip_status'];
//			$bonus['description'] = $lang_mybonus['text_vip_status_note'];
//			break;
//			}
//		case 7: {//Bonus Gift
//			$bonus['points'] = 25;
//			$bonus['art'] = 'gift_1';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_bonus_gift'];
//			$bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
//			if ($basictax_bonus || $taxpercentage_bonus){
//				$onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
//				$bonus['description'] .= "<br /><br />".$lang_mybonus['text_system_charges_receiver']."<b>".($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].add_s($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : "") : "").($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : "")."</b>".$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
//				}
//			break;
//			}
//		case 8: {
//			$bonus['points'] = $bonusnoadpoint_advertisement;
//			$bonus['art'] = 'noad';
//			$bonus['menge'] = $bonusnoadtime_advertisement * 86400;
//			$bonus['name'] = $bonusnoadtime_advertisement.$lang_mybonus['text_no_advertisements'];
//			$bonus['description'] = $lang_mybonus['text_no_advertisements_note'];
//			break;
//			}
//		case 9: {
//			$bonus['points'] = 1000;
//			$bonus['art'] = 'gift_2';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_charity_giving'];
//			$bonus['description'] = $lang_mybonus['text_charity_giving_note'];
//			break;
//			}
//        case 10: {
//            $bonus['points'] = \App\Models\BonusLogs::getBonusForCancelHitAndRun();
//            $bonus['art'] = 'cancel_hr';
//            $bonus['menge'] = 0;
//            $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
//            $bonus['description'] = '<p>
//            <span style="">' . $lang_mybonus['text_cancel_hr_label'] . '</span>
//            <input type="number" name="hr_id" />
//        </p>';
//            break;
//        }
//		default: break;
//	}
//	return $bonus;
}

$allBonus = bonusarray();
$lockSeconds = 10;
$lockText = sprintf($lang_mybonus['lock_text'], $lockSeconds);
if ($bonus_tweak == "disable" || $bonus_tweak == "disablesave")
	stderr($lang_mybonus['std_sorry'],$lang_mybonus['std_karma_system_disabled'].($bonus_tweak == "disablesave" ? "<b>".$lang_mybonus['std_points_active']."</b>" : ""),false);

$action = htmlspecialchars($_GET['action'] ?? '');
$do = htmlspecialchars($_GET['do'] ?? '');
unset($msg);
if (isset($do)) {
	if ($do == "upload")
	$msg = $lang_mybonus['text_success_upload'];
    elseif ($do == "download")
    $msg = $lang_mybonus['text_success_download'];
	elseif ($do == "invite")
	$msg = $lang_mybonus['text_success_invites'];
    elseif ($do == "tmp_invite")
        $msg = $lang_mybonus['text_success_tmp_invites'];
	elseif ($do == "vip")
	$msg =  $lang_mybonus['text_success_vip']."<b>".get_user_class_name(UC_VIP,false,false,true)."</b>".$lang_mybonus['text_success_vip_two'];
	elseif ($do == "vipfalse")
	$msg =  $lang_mybonus['text_no_permission'];
	elseif ($do == "title")
	$msg = sprintf($lang_mybonus['text_success_custom_title'], $CURUSER['title']);
	elseif ($do == "transfer")
	$msg =  $lang_mybonus['text_success_gift'];
	elseif ($do == "noad")
	$msg =  $lang_mybonus['text_success_no_ad'];
	elseif ($do == "charity")
	$msg =  $lang_mybonus['text_success_charity'];
    elseif ($do == "cancel_hr")
        $msg =  $lang_mybonus['text_success_cancel_hr'];
    elseif ($do == "buy_medal")
        $msg =  $lang_mybonus['text_success_buy_medal'];
    elseif ($do == "attendance_card")
        $msg =  $lang_mybonus['text_success_buy_attendance_card'];
    elseif ($do == "rainbow_id")
        $msg =  $lang_mybonus['text_success_buy_rainbow_id'];
    elseif ($do == "change_username_card")
        $msg =  $lang_mybonus['text_success_buy_change_username_card'];
    elseif ($do == 'duplicated')
        $msg = $lockText;
	else
	$msg = '';
}
	stdhead($CURUSER['username'] . $lang_mybonus['head_karma_page']);

	$bonus = number_format($CURUSER['seedbonus'], 1);
if (!$action) {
	print("<div id=\"bonus-exchange-wrapper\">");
	print("<table align=\"center\" width=\"97%\" border=\"1\" cellspacing=\"0\" cellpadding=\"3\">\n");
	print("<tr><td class=\"colhead\" colspan=\"4\" align=\"center\"><font class=\"big\">".$SITENAME.$lang_mybonus['text_karma_system']."</font></td></tr>\n");
	if ($msg)
	print("<tr id=\"bonus-message\"><td align=\"center\" colspan=\"4\"><font class=\"striking\"><b>". $msg ."</b></font></td></tr>");
?>
<tr id="bonus-summary"><td class="text" align="center" colspan="4"><?php echo $lang_mybonus['text_exchange_your_karma']?><span id="current-bonus"><?php echo $bonus?></span><?php echo $lang_mybonus['text_for_goodies'] ?>
<br /><b><?php echo $lang_mybonus['text_no_buttons_note'] ?></b><br /><small style="color: orangered">(<?php echo $lockText ?>)</small></td></tr>
<?php
print("<tr><td class=\"colhead\" align=\"center\">".$lang_mybonus['col_option']."</td>".
"<td class=\"colhead\" align=\"left\">".$lang_mybonus['col_description']."</td>".
"<td class=\"colhead\" align=\"center\">".$lang_mybonus['col_points']."</td>".
"<td class=\"colhead\" align=\"center\">".$lang_mybonus['col_trade']."</td>".
"</tr>");


for ($i=0; $i < count($allBonus); $i++)
{
	$bonusarray = $allBonus[$i];
	if (
	    ($bonusarray['art'] == 'gift_1' && $bonusgift_bonus == 'no')
        || ($bonusarray['art'] == 'noad' && ($enablead_advertisement == 'no' || $bonusnoad_advertisement == 'no'))
        || ($bonusarray['art'] == 'cancel_hr' && !\App\Models\HitAndRun::getIsEnabled())
    ) {
        continue;
    }
    $bonusarrray['points'] = floatval($bonusarray['points']);

	print("<form action=\"?action=exchange\" method=\"post\">");
	print("<tr>");
	print("<td class=\"rowhead_center\"><input type=\"hidden\" name=\"option\" value=\"".$i."\" /><b>".($i + 1)."</b></td>");
	if ($bonusarray['art'] == 'title'){ //for Custom Title!
	    $otheroption_title = "<input type=\"text\" name=\"title\" style=\"width: 200px\" maxlength=\"30\" />";
	    print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_enter_titile'].$otheroption_title.$lang_mybonus['text_click_exchange']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points'])."</td>");
	}
	elseif ($bonusarray['art'] == 'gift_1'){  //for Give A Karma Gift
			$otheroption = "<table width=\"100%\"><tr><td class=\"embedded\"><b>".$lang_mybonus['text_username']."</b><input type=\"text\" name=\"username\" style=\"width: 200px\" maxlength=\"24\" /></td><td class=\"embedded\"><b>".$lang_mybonus['text_to_be_given']."</b><input type=\"number\" name=\"bonusgift\" id=\"giftcustom\" style='width: 80px' min='100' />".$lang_mybonus['text_karma_points']."</td></tr><tr><td class=\"embedded\" colspan=\"2\"><b>".$lang_mybonus['text_message']."</b><input type=\"text\" name=\"message\" style=\"width: 400px\" maxlength=\"100\" /></td></tr></table>";
			print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_enter_receiver_name']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min']."100</td>");
	}
	elseif ($bonusarray['art'] == 'gift_2'){  //charity giving
			$otheroption = "<table width=\"100%\"><tr><td class=\"embedded\">".$lang_mybonus['text_ratio_below']."<select name=\"ratiocharity\"> <option value=\"0.1\"> 0.1</option><option value=\"0.2\"> 0.2</option><option value=\"0.3\" selected=\"selected\"> 0.3</option> <option value=\"0.4\"> 0.4</option> <option value=\"0.5\"> 0.5</option> <option value=\"0.6\"> 0.6</option><option value=\"0.7\"> 0.7</option><option value=\"0.8\"> 0.8</option></select>".$lang_mybonus['text_and_downloaded_above']." 10 GB</td><td class=\"embedded\"><b>".$lang_mybonus['text_to_be_given']."</b><select name=\"bonuscharity\" id=\"charityselect\" > <option value=\"1000\"> 1,000</option><option value=\"2000\"> 2,000</option><option value=\"3000\" selected=\"selected\"> 3000</option> <option value=\"5000\"> 5,000</option> <option value=\"8000\"> 8,000</option> <option value=\"10000\"> 10,000</option><option value=\"20000\"> 20,000</option><option value=\"50000\"> 50,000</option></select>".$lang_mybonus['text_karma_points']."</td></tr></table>";
			print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_select_receiver_ratio']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min']."1,000<br />".$lang_mybonus['text_max']."50,000</td>");
	}
	else {  //for VIP or Upload
		print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points'])."</td>");
	}

	if($CURUSER['seedbonus'] >= $bonusarray['points'])
	{
	    $permission = 'sendinvite';
		if ($bonusarray['art'] == 'gift_1'){
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_karma_gift']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'noad'){
			if ($enablenoad_advertisement == 'yes' && get_user_class() >= $noad_advertisement)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_class_above_no_ad']."\" disabled=\"disabled\" /></td>");
			elseif (!empty($CURUSER['noaduntil']) && strtotime($CURUSER['noaduntil']) >= TIMENOW)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_already_disabled']."\" disabled=\"disabled\" /></td>");
			elseif (get_user_class() < $bonusnoad_advertisement)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".get_user_class_name($bonusnoad_advertisement,false,false,true).$lang_mybonus['text_plus_only']."\" disabled=\"disabled\" /></td>");
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'gift_2'){
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_charity_giving']."\" /></td>");
		}
		elseif($bonusarray['art'] == 'invite')
		{
			if (\App\Models\Setting::get('main.invitesystem') != 'yes')
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.invite_system_closed')."\" disabled=\"disabled\" /></td>");
			elseif(!user_can($permission, false, 0)){
			$requireClass = get_setting("authority.$permission");
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.no_permission', ['class' => \App\Models\User::getClassText($requireClass)])."\" disabled=\"disabled\" /></td>");}
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif($bonusarray['art'] == 'tmp_invite')
		{
			if (\App\Models\Setting::get('main.invitesystem') != 'yes')
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.invite_system_closed')."\" disabled=\"disabled\" /></td>");
			elseif(!user_can($permission, false, 0)){
			$requireClass = get_setting("authority.$permission");
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.no_permission', ['class' => \App\Models\User::getClassText($requireClass)])."\" disabled=\"disabled\" /></td>");}
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'class')
		{
			if (get_user_class() >= UC_VIP)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['std_class_above_vip']."\" disabled=\"disabled\" /></td>");
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'title')
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		elseif ($bonusarray['art'] == 'traffic')
		{
			if ($CURUSER['downloaded'] > 0){
				if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824)//Uploaded amount reach limit
					$ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
				else $ratio = 0;
			}
			else $ratio = $ratiolimit_bonus + 1; //Ratio always above limit
			if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus){
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_ratio_too_high']."\" disabled=\"disabled\" /></td>");
			}
			else print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		} elseif ($bonusarray['art'] == 'change_username_card') {
		    if (\App\Models\UserMeta::query()->where('uid', $CURUSER['id'])->where('meta_key', \App\Models\UserMeta::META_KEY_CHANGE_USERNAME)->exists()) {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_change_username_card_already_has']."\" disabled=\"disabled\"/></td>");
            } else {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
            }
        } elseif ($bonusarray['art'] == 'rainbow_id') {
            if (\App\Models\UserMeta::query()->where('uid', $CURUSER['id'])->where('meta_key', \App\Models\UserMeta::META_KEY_PERSONALIZED_USERNAME)->whereNull('deadline')->exists()) {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_rainbow_id_already_valid_forever']."\" disabled=\"disabled\"/></td>");
            } else {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
            }
		} else {
            print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
        }
	}
	else
	{
		print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_more_points_needed']."\" disabled=\"disabled\" /></td>");
	}
	print("</tr>");
	print("</form>");

}

print("</table><br />");
print("</div>");

$bonusExchangeJs = <<<'JS'
// 强制重置PJAX状态和页面初始化
try {
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.href);
    }
    
    // 清除可能存在的PJAX缓存状态
    if (window.jQuery && jQuery.fn && jQuery.fn.pjax) {
        jQuery(document).off('.pjax');
        jQuery('#pjax-container').removeData('pjax');
    }
    
    // 重置所有表单和按钮状态
    jQuery('form').each(function() {
        if (this.dataset) {
            this.dataset.submitting = '';
        } else {
            this.setAttribute('data-submitting', '');
        }
    });
    jQuery('input[type="submit"], button[type="submit"]').each(function() {
        var originalText = jQuery(this).data('original-text');
        if (originalText !== undefined) {
            jQuery(this).val(originalText);
        }
        jQuery(this).prop('disabled', false).data('loading', false);
    });
    
    // 关闭可能残留的layer弹窗
    if (window.layer && typeof window.layer.closeAll === 'function') {
        window.layer.closeAll();
    }
} catch (e) {
    console.warn('页面状态重置异常:', e);
}

jQuery(function ($) {
    var $wrapper = null;
    $wrapper = $('#bonus-exchange-wrapper');
    if (!$wrapper || !$wrapper.length) {
        return;
    }

    var layerLoadIndex = null;
    var fallbackOverlay = null;

    function scheduleCleanup() {
        $(window).one('pagehide beforeunload unload', function () {
            stopLoading();
        });
    }

    function resetForms() {
        $wrapper.find('form').each(function () {
            if (this.dataset) {
                this.dataset.submitting = '';
            } else {
                this.setAttribute('data-submitting', '');
            }
            var $buttons = $(this).find('input[type="submit"], button[type="submit"]');
            $buttons.each(function () {
                var $btn = $(this);
                var originalText = $btn.data('original-text');
                if (originalText !== undefined) {
                    $btn.val(originalText);
                }
                $btn.prop('disabled', false).data('loading', false);
            });
        });
    }

    function startLoading() {
        if (layerLoadIndex !== null || fallbackOverlay) {
            return;
        }
        if (window.layer && typeof window.layer.load === 'function') {
            layerLoadIndex = window.layer.load(1, {shade: 0.2});
        } else {
            fallbackOverlay = $('<div id="bonus-loading-overlay">处理中…</div>').css({
                position: 'fixed',
                left: 0,
                top: 0,
                right: 0,
                bottom: 0,
                background: 'rgba(0,0,0,0.35)',
                color: '#fff',
                fontSize: '18px',
                lineHeight: '100vh',
                textAlign: 'center',
                zIndex: 9999
            }).appendTo('body');
        }
    }

    function stopLoading() {
        if (layerLoadIndex !== null && window.layer && typeof window.layer.close === 'function') {
            window.layer.close(layerLoadIndex);
        }
        layerLoadIndex = null;
        if (fallbackOverlay) {
            fallbackOverlay.remove();
            fallbackOverlay = null;
        }
    }

    $(window).on('pageshow', function () {
        stopLoading();
        resetForms();
    });

    // 拦截表单提交，使用 AJAX
    $wrapper.on('submit', 'form', function (e) {
        console.log('表单提交被拦截');
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        var form = this;
        var $form = $(form);
        
        // 防止重复提交
        var submitting = form.dataset ? form.dataset.submitting : form.getAttribute('data-submitting');
        if (submitting === '1') {
            console.log('表单正在提交中，忽略');
            return false;
        }

        var $button = $form.find('input[type="submit"], button[type="submit"]').filter(':enabled').first();
        if ($button.length && $button.data('loading')) {
            console.log('按钮正在加载中，忽略');
            return false;
        }
        
        // 获取表单数据
        var optionValue = $form.find('input[name="option"]').val();
        var titleValue = $form.find('input[name="title"]').val();
        var title = titleValue ? titleValue : '';
        console.log('准备交换，option:', optionValue, 'title:', title);
        
        // 显示确认弹窗
        var confirmMsg = '确认要交换吗？';
        
        // 等待 nexusConfirm 加载（最多等待2秒）
        var tryCount = 0;
        var maxTries = 20;
        var tryConfirm = function() {
            tryCount++;
            if (typeof window.nexusConfirm === 'function') {
                console.log('显示确认弹窗');
                window.nexusConfirm(confirmMsg, function() {
                    // 确认后提交
                    if (form.dataset) {
                        form.dataset.submitting = '1';
                    } else {
                        form.setAttribute('data-submitting', '1');
                    }
                    if ($button.length) {
                        var originalText = $button.val();
                        $button.data('original-text', originalText);
                        $button.data('loading', true);
                        if (typeof originalText === 'string' && originalText.indexOf('…') === -1) {
                            $button.val(originalText + '…');
                        }
                        $button.prop('disabled', true);
                    }
                    
                    // 使用表单序列化获取所有表单数据（就像传统表单提交一样）
                    var formData = $form.serialize();
                    console.log('表单序列化数据:', formData);
                    
                    // 将表单数据转换为对象，并添加 action 参数
                    var formDataArray = formData.split('&');
                    var ajaxData = {
                        action: 'exchangeBonus'
                    };
                    
                    // 解析表单数据
                    for (var i = 0; i < formDataArray.length; i++) {
                        var pair = formDataArray[i].split('=');
                        if (pair.length === 2) {
                            var key = decodeURIComponent(pair[0]);
                            var value = decodeURIComponent(pair[1]);
                            ajaxData[key] = value;
                        }
                    }
                    
                    console.log('AJAX 数据对象:', ajaxData);
                    
                    // 验证 option 值
                    if (!ajaxData.option || ajaxData.option === '') {
                        console.error('option 值为空！表单数据:', formData);
                        if (typeof window.nexusAlert === 'function') {
                            window.nexusAlert('无法获取交换选项，请刷新页面重试');
                        } else {
                            alert('无法获取交换选项，请刷新页面重试');
                        }
                        // 恢复按钮状态
                        if ($button.length) {
                            var originalText = $button.data('original-text');
                            if (originalText !== undefined) {
                                $button.val(originalText);
                            }
                            $button.prop('disabled', false).data('loading', false);
                        }
                        if (form.dataset) {
                            form.dataset.submitting = '';
                        } else {
                            form.setAttribute('data-submitting', '');
                        }
                        return;
                    }
                    
                    // 发送 AJAX 请求
                    jQuery.ajax({
                        url: 'ajax.php',
                        type: 'POST',
                        data: ajaxData,
                        dataType: 'json',
                        success: function(response) {
                        if (form.dataset) {
                            form.dataset.submitting = '';
                        } else {
                            form.setAttribute('data-submitting', '');
                        }
                        if ($button.length) {
                            var originalText = $button.data('original-text');
                            if (originalText !== undefined) {
                                $button.val(originalText);
                            }
                            $button.prop('disabled', false).data('loading', false);
                        }
                        
                        if (response.ret == 0) {
                            // 成功：显示成功消息并更新魔力值
                            if (typeof window.nexusAlert === 'function') {
                                window.nexusAlert(response.msg, function() {
                                    // 更新当前魔力值显示
                                    if (response.data) {
                                        if (response.data.new_bonus) {
                                            jQuery('#current-bonus').text(response.data.new_bonus);
                                        }
                                    }
                                });
                            } else {
                                alert(response.msg);
                                if (response.data) {
                                    if (response.data.new_bonus) {
                                        jQuery('#current-bonus').text(response.data.new_bonus);
                                    }
                                }
                            }
                        } else {
                            // 失败：显示错误消息
                            if (typeof window.nexusAlert === 'function') {
                                window.nexusAlert(response.msg);
                            } else {
                                alert(response.msg);
                            }
                        }
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        form.dataset.submitting = '';
                        if ($button.length) {
                            var originalText = $button.data('original-text');
                            if (originalText !== undefined) {
                                $button.val(originalText);
                            }
                            $button.prop('disabled', false).data('loading', false);
                        }
                        
                        var errorMsg = '请求失败，请重试';
                        try {
                            var jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse && jsonResponse.msg) {
                                errorMsg = jsonResponse.msg;
                            }
                        } catch(err) {
                            if (xhr.responseJSON && xhr.responseJSON.msg) {
                                errorMsg = xhr.responseJSON.msg;
                            }
                        }
                        if (typeof window.nexusAlert === 'function') {
                            window.nexusAlert(errorMsg);
                        } else {
                            alert(errorMsg);
                        }
                    }
                });
                });
            } else {
                // 如果 nexusConfirm 还没加载，等待一下再试
                if (tryCount < maxTries) {
                    console.log('等待 nexusConfirm 加载...', tryCount);
                    setTimeout(tryConfirm, 100);
                } else {
                    // 超时后使用传统方式
                    console.log('nexusConfirm 加载超时，使用传统提交');
                    if (confirm(confirmMsg)) {
                        if (form.dataset) {
                            form.dataset.submitting = '1';
                        } else {
                            form.setAttribute('data-submitting', '1');
                        }
                        startLoading();
                        scheduleCleanup();
                        form.submit();
                    }
                }
            }
        };
        
        tryConfirm();
        
        return false;
    });

    // 拦截按钮点击，阻止默认表单提交
    $wrapper.on('click', 'input[type="submit"], button[type="submit"]', function (event) {
        var form = this.form;
        if (form) {
            var submitting = form.dataset ? form.dataset.submitting : form.getAttribute('data-submitting');
            if (submitting === '1') {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                return false;
            }
        }
        
        // 如果按钮被禁用，阻止点击
        if ($(this).prop('disabled')) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return false;
        }
    });
});
JS;

\Nexus\Nexus::js($bonusExchangeJs, 'footer', false);
?>

<table width="97%" cellpadding="3">
<tr><td class="colhead" align="center"><font class="big"><?php echo $lang_mybonus['text_what_is_karma'] ?></font></td></tr>
<tr><td class="text" align="left">
<?php
print("<h1>".$lang_mybonus['text_get_by_seeding']."</h1>");
print("<ul>");
if ($perseeding_bonus > 0)
	print("<li>".$perseeding_bonus.$lang_mybonus['text_point'].add_s($perseeding_bonus).$lang_mybonus['text_for_seeding_torrent'].$maxseeding_bonus.$lang_mybonus['text_torrent'].add_s($maxseeding_bonus).")</li>");
print("<li>".$lang_mybonus['text_bonus_formula_one'].$tzero_bonus.$lang_mybonus['text_bonus_formula_two'].$nzero_bonus.$lang_mybonus['text_bonus_formula_wi'].get_setting('bonus.zero_bonus_factor').$lang_mybonus['text_bonus_formula_three'].$bzero_bonus.$lang_mybonus['text_bonus_formula_four'].$l_bonus.$lang_mybonus['text_bonus_formula_five']."</li>");
$minSize = get_setting('bonus.min_size');
if ($minSize > 0) {
    print("<li>".sprintf($lang_mybonus['text_bonus_mini_size'], mksize($minSize))."</li>");
}
if ($donortimes_bonus)
	print("<li>".$lang_mybonus['text_donors_always_get'].$donortimes_bonus.$lang_mybonus['text_times_of_bonus']."</li>");

print("</ul>");

$seedBonusResult = calculate_seed_bonus($CURUSER['id']);
$A = $seedBonusResult['A'];

$bonusTableResult = build_bonus_table($CURUSER, $seedBonusResult, ['table_style' => 'width: 50%']);

$percent = $seedBonusResult['seed_bonus'] * 100 / ($bzero_bonus + $perseeding_bonus * $maxseeding_bonus);
print("<div align=\"center\">".$lang_mybonus['text_you_are_currently_getting'].round($seedBonusResult['seed_bonus'],3).$lang_mybonus['text_point'].add_s($seedBonusResult['seed_bonus']).$lang_mybonus['text_per_hour']." (A = ".round($A,1).")</div><table align=\"center\" border=\"0\" width=\"400\"><tr><td class=\"loadbarbg\" style='border: none; padding: 0px;'>");

if ($percent <= 30) $loadpic = "loadbarred";
elseif ($percent <= 60) $loadpic = "loadbaryellow";
else $loadpic = "loadbargreen";
$width = $percent * 4;
print("<img class=\"".$loadpic."\" src=\"pic/trans.gif\" style=\"width: ".$width."px;\" alt=\"".$percent."%\" /></td></tr></table>");

if ($bonusTableResult['has_medal_addition']) {
    print("<h1>".$lang_mybonus['text_get_by_medal']."</h1>");
    print("<ul>");
    print("<li>".sprintf($lang_mybonus['medal_additional_desc'], $CURUSER['id'])."</li>");
    print("<li>".$lang_mybonus['medal_additional_factor'].$bonusTableResult['medal_addition_factor']."</li>");
    if (!empty($bonusTableResult['has_medal_series_addition']) && !empty($bonusTableResult['medal_series_addition_factor'])) {
        print("<li>勋章系列额外加成系数：".$bonusTableResult['medal_series_addition_factor']."</li>");
    }
    print("</ul>");
}
if ($bonusTableResult['has_official_addition']) {
    print("<h1>".$lang_mybonus['text_get_by_seeding_official']."</h1>");
    print("<ul>");
    print("<li>".$lang_mybonus['official_calculate_method']."</li>");
    print("<li>".$lang_mybonus['official_tag_bonus_additional_factor'].$bonusTableResult['official_addition_factor']."</li>");
    print("</ul>");
}

if ($bonusTableResult['has_stardust_farm_addition']) {
    print("<h1>🌍 通过星尘农场获得</h1>");
    print("<ul>");
    print("<li>每合成1个完整行星，魔力值获取速度提升 <strong>2%</strong></li>");
    print("<li>最多可合成10个不同行星，达到 <strong>20%</strong> 的魔力值加成</li>");
    print("<li>当前已合成行星数：<strong>" . round($bonusTableResult['stardust_farm_addition_factor'] / 0.02) . "</strong> 个</li>");
    print("<li>当前星尘农场加成系数：<strong>" . $bonusTableResult['stardust_farm_addition_factor'] . "</strong></li>");
    print("<li>📢 <a href=\"stardust_farm.php\" style=\"color: #4ECDC4; font-weight: bold;\">👉 进入星尘农场</a> 种植天体、合成行星，提升魔力值获取速度！</li>");
    print("</ul>");
}

if ($bonusTableResult['has_harem_addition']) {
    print("<h1>".$lang_mybonus['text_get_by_harem']."</h1>");
    print("<ul>");
    print("<li>".sprintf($lang_mybonus['harem_additional_desc'], $CURUSER['id'])."</li>");
    print("<li>".$lang_mybonus['harem_additional_factor'].$bonusTableResult['harem_addition_factor']."</li>");
    print("<li>".$lang_mybonus['harem_additional_note']."</li>");
    print("</ul>");
}

print("<h1>".$lang_mybonus['text_bonus_summary']."</h1>");
print '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$bonusTableResult['table'].'</div>';

print("<h1>".$lang_mybonus['text_other_things_get_bonus']."</h1>");
print("<ul>");
if ($uploadtorrent_bonus > 0)
	print("<li>".$lang_mybonus['text_upload_torrent'].$uploadtorrent_bonus.$lang_mybonus['text_point'].add_s($uploadtorrent_bonus)."</li>");
if ($uploadsubtitle_bonus > 0)
	print("<li>".$lang_mybonus['text_upload_subtitle'].$uploadsubtitle_bonus.$lang_mybonus['text_point'].add_s($uploadsubtitle_bonus)."</li>");
if ($starttopic_bonus > 0)
	print("<li>".$lang_mybonus['text_start_topic'].$starttopic_bonus.$lang_mybonus['text_point'].add_s($starttopic_bonus)."</li>");
if ($makepost_bonus > 0)
	print("<li>".$lang_mybonus['text_make_post'].$makepost_bonus.$lang_mybonus['text_point'].add_s($makepost_bonus)."</li>");
if ($addcomment_bonus > 0)
	print("<li>".$lang_mybonus['text_add_comment'].$addcomment_bonus.$lang_mybonus['text_point'].add_s($addcomment_bonus)."</li>");
if ($pollvote_bonus > 0)
	print("<li>".$lang_mybonus['text_poll_vote'].$pollvote_bonus.$lang_mybonus['text_point'].add_s($pollvote_bonus)."</li>");
if ($offervote_bonus > 0)
	print("<li>".$lang_mybonus['text_offer_vote'].$offervote_bonus.$lang_mybonus['text_point'].add_s($offervote_bonus)."</li>");
if ($funboxvote_bonus > 0)
	print("<li>".$lang_mybonus['text_funbox_vote'].$funboxvote_bonus.$lang_mybonus['text_point'].add_s($funboxvote_bonus)."</li>");
if ($saythanks_bonus > 0)
	print("<li>".$lang_mybonus['text_say_thanks'].$saythanks_bonus.$lang_mybonus['text_point'].add_s($saythanks_bonus)."</li>");
if ($receivethanks_bonus > 0)
	print("<li>".$lang_mybonus['text_receive_thanks'].$receivethanks_bonus.$lang_mybonus['text_point'].add_s($receivethanks_bonus)."</li>");
if ($adclickbonus_advertisement > 0)
	print("<li>".$lang_mybonus['text_click_on_ad'].$adclickbonus_advertisement.$lang_mybonus['text_point'].add_s($adclickbonus_advertisement)."</li>");
if ($prolinkpoint_bonus > 0)
	print("<li>".$lang_mybonus['text_promotion_link_clicked'].$prolinkpoint_bonus.$lang_mybonus['text_point'].add_s($prolinkpoint_bonus)."</li>");
if ($funboxreward_bonus > 0)
	print("<li>".$lang_mybonus['text_funbox_reward']."</li>");
print($lang_mybonus['text_howto_get_karma_four']);
if ($ratiolimit_bonus > 0)
	print("<li>".$lang_mybonus['text_user_with_ratio_above'].$ratiolimit_bonus.$lang_mybonus['text_and_uploaded_amount_above'].$dlamountlimit_bonus.$lang_mybonus['text_cannot_exchange_uploading']."</li>");
print($lang_mybonus['text_howto_get_karma_five'].$uploadtorrent_bonus.$lang_mybonus['text_point'].add_s($uploadtorrent_bonus).$lang_mybonus['text_howto_get_karma_six']);
?>
</td></tr></table>
<?php
}

// Bonus exchange
if ($action == "exchange") {
	if (isset($_POST["userid"]) || isset($_POST["points"]) || isset($_POST["bonus"]) || isset($_POST["art"]) || !isset($_POST['option']) || !isset($allBonus[$_POST['option']])){
		write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is trying to cheat at bonus system",'mod');
		die($lang_mybonus['text_cheat_alert']);
	}
	$option = intval($_POST["option"] ?? 0);
	$bonusarray = $allBonus[$option];
	$points = $bonusarray['points'];
	$userid = $CURUSER['id'];
	$art = $bonusarray['art'];

//	$bonuscomment = $CURUSER['bonuscomment'];
	$seedbonus=$CURUSER['seedbonus']-$points;

	if($CURUSER['seedbonus'] >= $points) {
        $bonusRep = new \App\Repositories\BonusRepository();
        $lockName = "user:$userid:exchange:bonus";
        $lock = new \Nexus\Database\NexusLock($lockName, $lockSeconds);
        if (!$lock->get()) {
            do_log("[LOCKED], $lockName, $lockText");
            nexus_redirect('mybonus.php?do=duplicated');
        }
		//=== trade for upload
		if($art == "traffic") {
			if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824) {
                //uploaded amount reach limit
                if ($CURUSER['downloaded'] > 0) {
                    $ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
                } else {
                    $ratio = PHP_INT_MAX;
                }
            } else {
                $ratio = 0;
            }
			if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus)
				die($lang_mybonus['text_cheat_alert']);
			else {
			$upload = $CURUSER['uploaded'];
			$up = $upload + $bonusarray['menge'];
            do_log(sprintf(
                "user: %s going to use %s bonus to exchange uploaded from %s to %s",
                $CURUSER['id'], $points, $CURUSER['uploaded'], $up
            ));
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for upload bonus.\n " .$bonuscomment;
//			sql_query("UPDATE users SET uploaded = ".sqlesc($up).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $points. " Points for uploaded.", ['uploaded' => $up]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=upload");
			}
		}
        if($art == "traffic_downloaded") {
            $downloaded = $CURUSER['downloaded'];
            $down = $downloaded + $bonusarray['menge'];
            do_log(sprintf(
                "user: %s going to use %s bonus to exchange downloaded from %s to %s",
                $CURUSER['id'], $points, $CURUSER['downloaded'], $down
            ));
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD, $points. " Points for downloaded.", ['downloaded' => $down]);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=download");
        }
		//=== trade for one month VIP status ***note "SET class = '10'" change "10" to whatever your VIP class number is
		elseif($art == "class") {
			if (get_user_class() >= UC_VIP) {
				stdmsg($lang_mybonus['std_no_permission'],$lang_mybonus['std_class_above_vip'], 0);
				stdfoot();
				die;
			}
			$vip_until = date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + 28*86400));
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for 1 month VIP Status.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET class = '".UC_VIP."', vip_added = 'yes', vip_until = ".sqlesc($vip_until).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP, $points. " Points for 1 month VIP Status.", ['class' => UC_VIP, 'vip_added' => 'yes', 'vip_until' => $vip_until]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=vip");
		}
		//=== trade for invites
		elseif($art == "invite") {
			if(!user_can('buyinvite'))
				die(get_user_class_name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only']);
			$invites = $CURUSER['invites'];
			$inv = $invites+$bonusarray['menge'];
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE, $points. " Points for invites.", ['invites' => $inv, ]);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=invite");
		}
        //=== temporary invite
        elseif($art == "tmp_invite") {
            if(!user_can('buyinvite'))
                die(get_user_class_name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only']);
//            $invites = $CURUSER['invites'];
//            $inv = $invites+$bonusarray['menge'];
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeToBuyTemporaryInvite($CURUSER['id']);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=tmp_invite");
        }
		//=== trade for special title
		/**** the $words array are words that you DO NOT want the user to have... use to filter "bad words" & user class...
		the user class is just for show, but what the hell tongue.gif Add more or edit to your liking.
		*note if they try to use a restricted word, they will recieve the special title "I just wasted my karma" *****/
		elseif($art == "title") {
			//===custom title
			$title = $_POST["title"];
			$words = array("fuck", "shit", "pussy", "cunt", "nigger", "Staff Leader","SysOp", "Administrator","Moderator","Uploader","Retiree","VIP","Nexus Master","Ultimate User","Extreme User","Veteran User","Insane User","Crazy User","Elite User","Power User","User","Peasant","Champion");
			$title = str_replace($words, $lang_mybonus['text_wasted_karma'], $title);
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET title = ".sqlesc($title).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title.", ['title' => $title, ]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=title");
		}
		elseif($art == "noad" && $enablead_advertisement == 'yes' && $enablebonusnoad_advertisement == 'yes') {
			if (($enablenoad_advertisement == 'yes' && get_user_class() >= $noad_advertisement) || strtotime($CURUSER['noaduntil']) >= TIMENOW || get_user_class() < $bonusnoad_advertisement)
				die($lang_mybonus['text_cheat_alert']);
			else{
				$noaduntil = date("Y-m-d H:i:s",(TIMENOW + $bonusarray['menge']));
//				$bonuscomment = date("Y-m-d") . " - " .$points. " Points for ".$bonusnoadtime_advertisement." days without ads.\n " .htmlspecialchars($bonuscomment);
//				sql_query("UPDATE users SET noad='yes', noaduntil='".$noaduntil."', seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id=".sqlesc($userid));
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_NO_AD, $points. " Points for ".$bonusnoadtime_advertisement." days without ads.", ['noad' => 'yes', 'noaduntil' => $noaduntil]);
				nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=noad");
			}
		}
		elseif($art == 'gift_2') // charity giving
		{
			$points = intval($_POST["bonuscharity"] ?? 0);
			if ($points < 1000 || $points > 50000){
				stdmsg($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed_two'], 0);
				stdfoot();
				die();
			}
			$ratiocharity = $_POST["ratiocharity"];
			if ($ratiocharity < 0.1 || $ratiocharity > 0.8){
				stdmsg($lang_mybonus['text_error'], $lang_mybonus['bonus_ratio_not_allowed']);
				stdfoot();
				die();
			}
			if($CURUSER['seedbonus'] >= $points) {
				$points2= number_format($points,1);
//				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".\n " .htmlspecialchars($bonuscomment);
				$charityReceiverCount = get_row_count("users", "WHERE enabled='yes' AND 10737418240 < downloaded AND $ratiocharity > uploaded/downloaded");
				if ($charityReceiverCount) {
//					sql_query("UPDATE users SET seedbonus = seedbonus - $points, charity = charity + $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                    $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO, $points. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".", ['charity' => \Nexus\Database\NexusDB::raw("charity + $points"), ]);
					$charityPerUser = $points/$charityReceiverCount;
					sql_query("UPDATE users SET seedbonus = seedbonus + $charityPerUser WHERE enabled='yes' AND 10737418240 < downloaded AND $ratiocharity > uploaded/downloaded") or sqlerr(__FILE__, __LINE__);
					nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=charity");
				}
				else
				{
					stdmsg($lang_mybonus['std_sorry'], $lang_mybonus['std_no_users_need_charity']);
					stdfoot();
					die;
				}
			}
		}
		elseif($art == "gift_1" && $bonusgift_bonus == 'yes') {
			//=== trade for giving the gift of karma
			$points = $_POST["bonusgift"];
			$message = $_POST["message"];
			//==gift for peeps with no more options
			$usernamegift = sqlesc(trim($_POST["username"]));
			$res = sql_query("SELECT id, seedbonus FROM users WHERE username=" . $usernamegift);
			$arr = mysql_fetch_assoc($res);
            if (empty($arr)) {
                stdmsg($lang_mybonus['text_error'], $lang_mybonus['text_receiver_not_exists'], 0);
                stdfoot();
                die;
            }
			$useridgift = $arr['id'];
			$userseedbonus = $arr['seedbonus'];
//			$receiverbonuscomment = $arr['bonuscomment'];
			if (!is_numeric($points) || $points < $bonusarray['points']) {
				//write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking bonus system",'mod');
				stdmsg($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed']);
				stdfoot();
				die();
			}
			if($CURUSER['seedbonus'] >= $points) {
				$points2= number_format($points,1);
//				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as gift to ".htmlspecialchars(trim($_POST["username"])).".\n " .htmlspecialchars($bonuscomment);

				$aftertaxpoint = $points;
				if ($taxpercentage_bonus)
					$aftertaxpoint -= $aftertaxpoint * $taxpercentage_bonus * 0.01;
				if ($basictax_bonus)
					$aftertaxpoint -= $basictax_bonus;

				$points2receiver = number_format($aftertaxpoint,1);
//				$newreceiverbonuscomment = date("Y-m-d") . " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]).".\n " .htmlspecialchars($receiverbonuscomment);
				if ($userid==$useridgift){
					stdmsg($lang_mybonus['text_huh'], $lang_mybonus['text_karma_self_giving_warning'], 0);
					stdfoot();
					die;
				}

//				sql_query("UPDATE users SET seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE, $points2 . " Points as gift to ".htmlspecialchars(trim($_POST["username"])));
				sql_query("UPDATE users SET seedbonus = seedbonus + $aftertaxpoint WHERE id = ".sqlesc($useridgift));
                \App\Models\BonusLogs::add($useridgift, $userseedbonus, $aftertaxpoint, $userseedbonus + $aftertaxpoint, " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]), \App\Models\BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT);

				//===send message
                $locale = get_user_locale($useridgift);
				$subject = nexus_trans("bonus.msg_someone_loves_you", [], $locale);
				$added = sqlesc(date("Y-m-d H:i:s"));
				$msg = nexus_trans("bonus.msg_you_have_been_given", [], $locale).$points2.nexus_trans("bonus.msg_after_tax", [], $locale).$points2receiver.nexus_trans("bonus.msg_karma_points_by", [], $locale).$CURUSER['username'];
				if ($message)
				{
					$msg .= "\n".nexus_trans("bonus.msg_personal_message_from", [], $locale).$CURUSER['username'].nexus_trans("bonus.msg_colon", [], $locale).$message;
				}
				\App\Models\Message::add([
					'sender' => 0,
					'subject' => $subject,
					'added' => now(),
					'msg' => $msg,
					'receiver' => $useridgift,
				]);
				$usernamegift = unesc($_POST["username"]);
                nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=transfer");
			}
			else{
				print("<table width=\"97%\"><tr><td class=\"colhead\" align=\"left\" colspan=\"2\"><h1>".$lang_mybonus['text_oups']."</h1></td></tr>");
				print("<tr><td align=\"left\"></td><td align=\"left\">".$lang_mybonus['text_not_enough_karma']."<br /><br /></td></tr></table>");
			}
		} elseif ($art == 'cancel_hr') {
		    if (empty($_POST['hr_id'])) {
		        stderr("Error","Invalid H&R ID: " . ($_POST['hr_id'] ?? ''), false, false);
            }
            $bonusRep->consumeToCancelHitAndRun($userid, $_POST['hr_id']);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=cancel_hr");
//        } elseif ($art == 'buy_medal') {
//            if (empty($_POST['medal_id'])) {
//                stderr("Error","Invalid Medal ID: " . ($_POST['medal_id'] ?? ''), false, false);
//            }
//            $bonusRep->consumeToBuyMedal($userid, $_POST['medal_id']);
//            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=buy_medal");
        } elseif ($art == 'attendance_card') {
            $bonusRep->consumeToBuyAttendanceCard($userid);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=attendance_card");
        } elseif ($art == 'rainbow_id') {
            $bonusRep->consumeToBuyRainbowId($userid);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=rainbow_id");
        } elseif ($art == 'change_username_card') {
            $bonusRep->consumeToBuyChangeUsernameCard($userid);
            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=change_username_card");
        }
	}
}
stdfoot();
?>
