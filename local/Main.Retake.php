<?php if (!defined('PmWiki')) exit();
define("BRIDGE_UN",'suphycsy@gmail.com');
define("BRIDGE_PW",'SephirothC');
//define("BRIDGE_UN",'nossy0926@yahoo.co.jp');
//define("BRIDGE_PW",'s0411038');
define("BRIDGE_NAME", "Sephiroth");
define("BRIDGE_COOKIE_FILE","../72e0fd9c6bd0.txt");
define("NICOVIDEO_LOGIN_URL", 'https://secure.nicovideo.jp/secure/login?site=niconico');
define("NICOVIDEO_LOGIN_VER", 'https://account.nicovideo.jp/my/account');
define("BRIDGE_USER_AGENT", 'Mozilla/5.0 (Windows; U; Windows NT 6.1; zh-CN; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3 ( .NET CLR 3.5.30729; .NET4.0E)');


$NoHTMLCache = 1;
$HandleActions['nekomimimode'] = 'HandleNicoBridge';
$HandleAuth['nekomimimode'] = 'read';
include_once('./cookbook/attachdel.php');

function HandleNicoBridge($pagename, $auth= 'read')
{
	ob_end_clean();
	header("Cache-Control: no-cache");
	header("Content-type: text/plain; charset=UTF-8");
	
	echo("DMF N1co Bridge V2.1.0 .\r\n");
	
	$matched = 
		preg_match("{(?:http://www.nicovideo.jp/watch/)?(?P<vid>(?P<type>sm|nm|so|ca|ax|yo|nl|ig|na|cw|z[a-e]|om|sk|yk)?\d{1,14})}i",
		trim($_POST['vid']),
		$matches);
	
	if ($matched == 0 )
		die("解析视频ID失败。");

	$type = $matches['type'];
	$vid  = $matches['vid'];
	
	if (!isLoggedIn()) 
	{
		echo "cookie已经失效，尝试登录。\r\n";
		$b = doLogin();
		if ($b)
		{
			echo "登录成功\r\n";
		} else {
			die("登录nicovideo失败。");
		}
	} else {
        echo  "登录检查完毕。\r\n";
    }

	viewPage($vid);
	$url = getFlvUrl($vid, $type);
	if (empty($url))
		die("解析下载地址失败。");
	echo("Downloading $vid ($url) \r\n");
	doDownloadVideo($url, $vid);
}

function doDownloadVideo($url,$vid)
{
	global $rFile,$fileext;
	
	$downloadTemp = "./uploads/Main/"."IMCOMPLETE_$vid.mp4";
	
	$fileExists = file_exists($downloadTemp);
	$rFile = fopen($downloadTemp, "a+");
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_COOKIEFILE, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_USERAGENT, BRIDGE_USER_AGENT);
	curl_setopt($ch, CURLOPT_TIMEOUT, 50);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'read_header');
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, "myPoorProgressFunc"); 
	
	if ($fileExists)
	{
		if (flock($rFile,LOCK_EX | LOCK_NB))
		{
			echo "尝试启用断点续传\r\n";
			curl_setopt($ch, CURLOPT_RANGE, filesize($downloadTemp)."-");
		} else {
			fclose($rFile);
			die("共享违例 :: 文件已被其他任务占用。");
		}
	}
	
	curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);
	
	flock($rFile, LOCK_UN);
	fclose($rFile);
	if (!empty($error))
	{
		echo "CURL错误 :: $error \r\n";
		echo "超时错误请直接F5刷新 重新提交下载请求\r\n";
		exit;
	}
	$downloadTarget = "./uploads/Main/"."$vid.$fileext";
	
	if (file_exists($downloadTemp))
	{
		rename($downloadTemp, $downloadTarget);
		echo "下载地址: http://danmaku.us/uploads/Main/".$vid.".".$fileext."\r\n";
		echo "完毕。\r\n";
	} else {
		echo "下载失败。\r\n";
	}	

}


function read_header($ch, $string) 
{
	global $totalsize, $fileext;
	if(!strncasecmp($string, "Content-Length:",15)) {$totalsize = round(trim(substr($string,16)) / 1024 / 1024,2);echo "Total size:$totalsize MB\r\n";}
	if(!strncasecmp($string, "Content-Disposition:",20)) {$fileext = trim(substr($string,45,3));echo "File extension:.$fileext \r\n";}
	return strlen($string);
}


function myPoorProgressFunc($ch,$str)
{
	global $rFile , $downloaded , $totalsize , $t2 ;
	$len = fwrite($rFile,$str);;
	$downloaded += $len;$t2 += $len;
	if ($t2 >= 5*1024*1024) {
		$t = round($downloaded / 1024 / 1024,2);
		$downper = round ($t / $totalsize * 100 , 2);
		echo "已完成: $t MB / $totalsize MB   $downper % \r\n";
		$t2 = 0 ;
	}
	return $len;
} 



function getFlvUrl($vid, $type)
{
	if (strtolower($type) == 'nm')
	{
		echo("尝试启用nm下载模式\r\n");
		$query = "v=".$vid.'&as3=1';
	} else {
		echo("尝试普通视频模式\r\n");
		$query = "v=$vid";
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://flapi.nicovideo.jp/api/getflv");
	curl_setopt($ch, CURLOPT_COOKIEFILE, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_USERAGENT, BRIDGE_USER_AGENT);
	curl_setopt($ch, CURLOPT_REFERER   , "http://www.nicovideo.jp/watch/$vid");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$str=curl_exec($ch);
	parse_str($str);
	curl_close($ch);
	return $url;
}



function viewPage($vid)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://www.nicovideo.jp/watch/$vid");
	curl_setopt($ch, CURLOPT_COOKIEFILE, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_COOKIEJAR, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_USERAGENT, BRIDGE_USER_AGENT);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_exec($ch);
	curl_close($ch);
}

function doLogin()
{
    //die("Nico方面SSL连接超时。暂停服务。");
    $ch_pre = curl_init();
	curl_setopt($ch_pre, CURLOPT_URL, NICOVIDEO_LOGIN_URL);
	curl_setopt($ch_pre, CURLOPT_COOKIEFILE, BRIDGE_COOKIE_FILE);
	curl_setopt($ch_pre, CURLOPT_COOKIEJAR, BRIDGE_COOKIE_FILE);
	curl_setopt($ch_pre, CURLOPT_USERAGENT, BRIDGE_USER_AGENT);
	curl_setopt($ch_pre, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch_pre, CURLOPT_SSL_VERIFYHOST, FALSE);	
	curl_setopt($ch_pre, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');	
	curl_setopt($ch_pre, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch_pre, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch_pre, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch_pre, CURLOPT_MAXREDIRS, 6);
	curl_exec($ch_pre);
	$real_login_url = curl_getinfo($ch_pre, CURLINFO_EFFECTIVE_URL);
	curl_close($ch_pre);
	
    echo("Real login url is : {$real_login_url}");
    
    //die($real_login_url);
    $un = BRIDGE_UN;
    $pw = BRIDGE_PW;
    $post= 
    "show_button_facebook=0&show_button_twitter=0&_use_valid_error_code=0&nolinks=0&next_url=&mail_tel={$un}&password={$pw}";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, NICOVIDEO_LOGIN_URL);
	curl_setopt($ch, CURLOPT_COOKIEFILE, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_COOKIEJAR, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_REFERER   , $real_login_url);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);	
	curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');		
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
	$str=curl_exec($ch);
	curl_close($ch);

	return string_contains($str, '/watch/sm');
}

function isLoggedIn()
{
	if (!file_exists(BRIDGE_COOKIE_FILE))
		return false;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, NICOVIDEO_LOGIN_VER);
	curl_setopt($ch, CURLOPT_COOKIEFILE, BRIDGE_COOKIE_FILE);
    curl_setopt($ch, CURLOPT_COOKIEJAR, BRIDGE_COOKIE_FILE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);	
	curl_setopt($ch, CURLOPT_SSLVERSION, 3);	
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
	$str=curl_exec($ch);
	curl_close($ch);
	return false;
	//return string_contains($str, BRIDGE_NAME);
}

function string_contains($src, $needle) {
    if (stripos($src, $needle) !== true) {
        return true;
    } else {
        return false;
    }
}

