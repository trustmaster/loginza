<?php

function lz_autologin($row)
{
	global $usr, $sys, $cfg, $redirect, $db_users, $db_online, $rremember, $db, $_SESSION;


	$rusername = $row['user_name'];
	$rmdpass = $row['user_password'];
	if ($row['user_maingrp'] == -1)
	{
		cot_log("Log in attempt, user inactive : " . $rusername, 'usr');
		cot_redirect(cot_url('message', 'msg=152', '', true));
	}
	if ($row['user_maingrp'] == 2)
	{
		cot_log("Log in attempt, user inactive : " . $rusername, 'usr');
		cot_redirect(cot_url('message', 'msg=152', '', true));
	}
	elseif ($row['user_maingrp'] == 3)
	{
		if ($sys['now'] > $row['user_banexpire'] && $row['user_banexpire'] > 0)
		{
			$sql = $db->query("UPDATE $db_users SET user_maingrp='4' WHERE user_id={$row['user_id']}");
		}
		else
		{
			cot_log("Log in attempt, user banned : " . $rusername, 'usr');
			cot_redirect(cot_url('message', 'msg=153&num=' . $row['user_banexpire'], '', true));
		}
	}

	$ruserid = $row['user_id'];
	// $rdeftheme = $row['user_theme'];
	// $rdefscheme = $row['user_scheme'];

	$token = cot_unique(16);

	$sid = hash_hmac('sha256', $rmdpass . $row['user_sidtime'], $cfg['secret_key']);

	if (empty($row['user_sid']) || $row['user_sid'] != $sid
		|| $row['user_sidtime'] + $cfg['cookielifetime'] < $sys['now'])
	{
		// Generate new session identifier
		$sid = hash_hmac('sha256', $rmdpass . $sys['now'], $cfg['secret_key']);
		$update_sid = ", user_sid = " . $db->quote($sid) . ", user_sidtime = " . $sys['now'];
	}
	else
	{
		$update_sid = '';
	}

	$db->query("UPDATE $db_users SET user_lastip='{$usr['ip']}', user_lastlog = {$sys['now']}, user_logcount = user_logcount + 1, user_token = '$token' $update_sid WHERE user_id={$row['user_id']}");

	// Hash the sid once more so it can't be faked even if you  know user_sid
	$sid = hash_hmac('sha1', $sid, $cfg['secret_key']);

	$u = base64_encode($ruserid.':'.$sid);

	if ($rremember)
	{
		cot_setcookie($sys['site_id'], $u, time() + $cfg['cookielifetime'], $cfg['cookiepath'], $cfg['cookiedomain'], $sys['secure'], true);
	}
	else
	{
		$_SESSION[$sys['site_id']] = $u;
	}
	/* === Hook === */
	foreach (cot_getextplugins('users.auth.check.done') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$res = $db->query("SHOW TABLES LIKE '$db_online'");
	if (!empty($db_online) && $res->rowCount() == 1)
	{
		$db->query("DELETE FROM $db_online WHERE online_userid='-1' AND online_ip='" . $usr['ip'] . "' LIMIT 1");
	}
	cot_uriredir_apply($cfg['redirbkonlogin']);
	cot_uriredir_redirect(empty($redirect) ? cot_url('index') : base64_decode($redirect));
	exit;
}

function Nickname($txt)
{

	$patterns = array(
		'([^\.]+)\.ya\.ru',
		'openid\.mail\.ru\/[^\/]+\/([^\/?]+)',
		'my\.mail\.ru\/[^\/]+\/([^\/?]+)',
		'openid\.yandex\.ru\/([^\/?]+)',
		'([^\.]+)\.myopenid\.com'
	);
	foreach ($patterns as $pattern)
	{
		if (preg_match('/^https?\:\/\/' . $pattern . '/i', $txt, $result))
		{
			return $result[1];
		}
	}
	return false;
}

function RandomPassword($len=9, $char_list='a-z,0-9')
{
	$chars = array();
	$chars['a-z'] = 'qwertyuiopasdfghjklzxcvbnm';
	$chars['A-Z'] = strtoupper($chars['a-z']);
	$chars['0-9'] = '0123456789';
	$chars['~'] = '~!@#$%^&*()_+=-:";\'/\\?><,.|{}[]';

	$charset = '';
	$password = '';

	if (!empty($char_list))
	{
		$char_types = explode(',', $char_list);

		foreach ($char_types as $type)
		{
			if (array_key_exists($type, $chars))
			{
				$charset .= $chars[$type];
			}
			else
			{
				$charset .= $type;
			}
		}
	}

	for ($i = 0; $i < $len; $i++)
	{
		$password .= $charset[rand(0, strlen($charset) - 1)];
	}

	return $password;
}

?>
