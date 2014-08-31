<?php
/*
 * promoCodeProviderPHP by Phy25
 *
 * Please change the following settings before you put it into operation.
 * Or your promo code can be easily revealed.
 *
 * If you are serious about the risk of "Violent Test", you have to add
 *   a protection module by DIY in this code. I am sorry that I would
 *   not add it in this version.
 * 
 * Currently the program only supports less than 1000 codes (3 digits of ID).
 *   If you want to enlarge it, please change the code format (6 digits) in get_code().
 * 
 * Please note that the UI template SHOULD be customized by yourself.
 *   No need to preserve Bootstrap; however the current design is a Bootstrapped one.
 */
/*
 * HASH is used to generate your promo code. Can be anything but it should be long enough.
 */
define('HASH', 'YOUR HASH');

/*
 * PASSWORD is your login password submitted in the "code" field.
 * TOKEN is your login certificate saved in you browser cookie.
 *   It can be long and complex, as you don't need to recognize it.
 */
define('PASSWORD', '0000000');
define('TOKEN', 'YOUR TOKEN');

/*
 * PATH is the data folder. The default value is the d folder.
 * BASEPATH is used to locate your path in the HTML. Slash (/) is required after it.
 * You have to change URIREGEXP according to BASEPATH.
 */
define('PATH', './d/');
define('BASEPATH', '/productname/c/');
define('URIREGEXP', "/^\\/productname\\/c(\\/([a-zA-Z0-9]{6})\\/?)?/i");
date_default_timezone_set('Asia/Beijing');

/*
 * That is all above! 
 */

header("Cache-Control: no-cache, must-revalidate");

function logger($type, $id){
	$content = $type.'|||'.$id.'|||'.date('Y-m-d H:i:s').'|||'.$_SERVER['HTTP_USER_AGENT'].'|||'.get_IP()."\n";
	$data_f = fopen(PATH.'log.txt', 'a');
	$res = fwrite($data_f, $content);
	fclose($data_f);
}
function get_IP(){
	if(getenv('HTTP_CLIENT_IP')) { 
	$onlineip = getenv('HTTP_CLIENT_IP');
	} elseif(getenv('HTTP_X_FORWARDED_FOR')) { 
	$onlineip = getenv('HTTP_X_FORWARDED_FOR');
	} elseif(getenv('REMOTE_ADDR')) { 
	$onlineip = getenv('REMOTE_ADDR');
	} else { 
	$onlineip = $HTTP_SERVER_VARS['REMOTE_ADDR'];
	}
	return $onlineip;
}
function get_code_checksum($id){
	$a = substr(trim(eregi_replace("[^0-9]","",md5(HASH.$id))), 0, 3);
	return sprintf("%03d", $a);
}
function get_code($id){
	$ida = str_split($id);
	$checksum = get_code_checksum($id);
	return $ida[2].$checksum.$ida[1].$ida[0];//[ID3, checksum1-3, ID2, ID1]
}


preg_match_all(URIREGEXP, $_SERVER["REQUEST_URI"], $match);
$pagetype = 'index';
if(count($match[0]) && $match[2][0]){
	$c = $match[2][0];
	$pagetype = 'code';
}else if($_REQUEST['code']){
	preg_match_all("/^([a-zA-Z0-9]{6,12})$/i", $_REQUEST['code'], $match);
	if(count($match[0]) && $match[1][0]){
		$c = $match[1][0];
		$pagetype = 'code';
	}else{
		$pagetype = 'error';
	}
	if($match[1][0] == PASSWORD){
		$pagetype = 'login';
	}
}

if($pagetype == 'login'){
	if($_COOKIE['pcpphp_admin'] != TOKEN){
		setcookie('pcpphp_admin', TOKEN, time()+60*60);
		$loggedin = true;
	}else{
		setcookie('pcpphp_admin', '', time());
		$loggedin = false;
	}
}else if($_COOKIE['pcpphp_admin'] == TOKEN){
	$loggedin = true;
}else{
	$loggedin = false;
}

if($loggedin && $_REQUEST['action'] == 'admin'){
	$pagetype = 'admin';
	if($_REQUEST['edit']){
		if($_POST['content']){
			$data_f = fopen(PATH.'data.txt.php', 'w');
			$res = fwrite($data_f, $_POST['content']);
			fclose($data_f);
		}
		$data = file(PATH.'data.txt.php');
		$pagetype = 'adminedit';
	}
}

if($pagetype == 'code'){
	$ca = str_split($c);//[ID3, checksum1-3, ID2, ID1]
	$id = $c[5].$c[4].$c[0];
	$checksum = $c[1].$c[2].$c[3];
	$data = file(PATH.'data.txt.php');
	$cur_code = false;
	$cur_code_usage = false;
	foreach ($data as $line_num => $line) {
		$line_d = explode(',', $line); //[ID,signer,title,note]
		if($id == $line_d[0] && $checksum == get_code_checksum($id)){
			$cur_code = $line_d;
			if(file_exists(PATH.$id.'.txt.php')){
				$cur_code_usage_f = file(PATH.$id.'.txt.php');
				if(is_array($cur_code_usage_f) && $cur_code_usage_f[0] != ''){
					$cur_code_usage = explode('|||', $cur_code_usage_f[0]); // [ID, usage01, viewCount, lastViewed, lastUA, lastIP]
					if($cur_code_usage[1] == '') $cur_code_usage[1] = '0';
				}
			}
		}
	}
	if($cur_code == false){
		$pagetype = 'error';
	}else{
		if($_REQUEST['action'] == 'reset' && $loggedin){
			$res_delete = unlink(PATH.$id.'.txt.php');
		}
		if($_REQUEST['action'] == 'modify_usage' && $loggedin){
			$cur_code_usage_new = array();
			$cur_code_usage_new[0] = $id;
			$cur_code_usage_new[1] = $_REQUEST['usage']?'1':'0';
			$cur_code_usage_new[2] = is_array($cur_code_usage)? (int)($cur_code_usage[2]) + 1:1;
			$cur_code_usage_new[3] = date('Y-m-d H:i:s');
			$cur_code_usage_new[4] = $_SERVER['HTTP_USER_AGENT'];
			$cur_code_usage_new[5] = get_IP();

			$usage_h = fopen(PATH.$id.'.txt.php', 'w');
			fwrite($usage_h, implode('|||', $cur_code_usage_new));
			fclose($usage_h);
			logger('modefy_usage_'.$cur_code_usage_new[1], $id);

			$cur_code_usage = $cur_code_usage_new;
		}else if(!$loggedin){
			// 写信息
			$cur_code_usage_new = array();
			$cur_code_usage_new[0] = $id;
			$cur_code_usage_new[1] = (is_array($cur_code_usage) && $cur_code_usage[1] != '')?$cur_code_usage[1]:'0';
			$cur_code_usage_new[2] = is_array($cur_code_usage)? (int)($cur_code_usage[2]) + 1:1;
			if($cur_code_usage_new[1] == '0'){
				$cur_code_usage_new[3] = date('Y-m-d H:i:s');
				$cur_code_usage_new[4] = $_SERVER['HTTP_USER_AGENT'];
				$cur_code_usage_new[5] = get_IP();
			}else{
				$cur_code_usage_new[3] = $cur_code_usage[3];
				$cur_code_usage_new[4] = $cur_code_usage[4];
				$cur_code_usage_new[5] = $cur_code_usage[5];
			}

			$usage_h = fopen(PATH.$id.'.txt.php', 'w');
			fwrite($usage_h, implode('|||', $cur_code_usage_new));
			fclose($usage_h);
		}
	}
}

if($pagetype == 'error' && $c) logger('code_check_error', $c);
?>
<!DOCTYPE html>
<html lang="zh">
<head><meta charset="utf-8" />
<title>[产品名] 优惠码</title>
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="application-name" content="[产品名] 优惠码" />
<meta name="viewport" content="width=device-width, user-scalable=no" />
<link rel="stylesheet" href="<?php echo BASEPATH; ?>bootstrap.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo BASEPATH; ?>style.css" type="text/css" />
<!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!--[if lt IE 9]>
  <script src="<?php echo BASEPATH; ?>html5shiv.min.js"></script>
  <script src="<?php echo BASEPATH; ?>respond.min.js"></script>
<![endif]-->

<meta name="robots" content="noindex, nofollow" />
</head><body>
<header class="navbar navbar-default nav-static-top" role="banner">
  <div class="container">
    <!-- Brand and toggle get grouped for better mobile display -->
    <div class="navbar-header">
		<?php if($pagetype == 'index'){ ?>
		<a class="navbar-brand" href="<?php echo BASEPATH; ?>">[产品名]</a>
		<?php }else{ ?>
		<a class="navbar-brand" href="<?php echo BASEPATH; ?>">[产品名] 优惠码</a>
		<?php } ?>
    </div>

    <div class="collapse navbar-collapse hidden-xs" role="navigation">
	<ul class="nav navbar-nav">
        <li class="active"><a href="<?php echo BASEPATH; ?>">优惠码</a></li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
      </ul>
    </div><!-- /.navbar-collapse -->
  </div><!-- /.container -->
</header>
<div class="container">
<?php
if($pagetype == 'index'){
?>
	<h1>优惠码验证平台</h1>
	<p>如果你有优惠码，请在下面输入以验证。</p>
	<div class="row">
		<form action="<?php echo BASEPATH; ?>" class="form-inline" method="GET" id="form">
				<div class="form-group"><label class="sr-only" for="code">优惠码</label><input type="number" class="form-control" style="width:12em" name="code" id="code" maxlength="12" placeholder="六位数字优惠码" tabindex="1" required /></div>
				<button type="submit" class="btn btn-primary" tabindex="2">验证</button>
				<button type="reset" class="btn btn-default" tabindex="3">清除</button>
		</form>
	</div>
<?php
}
if($pagetype == 'login'){
?>
	<p><?php echo $loggedin?'登录':'退出'; ?>成功。<a href="<?php echo BASEPATH; ?>" class="btn btn-default">返回</a></p>
<?php
}
if($pagetype == 'admin'){
	echo '<p><a href="?action=admin&edit=1" class="btn btn-default btn-sm">修改</a> <a href="', BASEPATH, PATH, 'log.txt" class="btn btn-default btn-sm">日志</a></p><ul>';
	$data = file(PATH.'data.txt.php');
	foreach ($data as $line_num => $line) {
		$line_d = explode(',', $line); //[ID,signer,title,note]
		$id = $line_d[0];
		$cur_code_usage_f = array();
		if(file_exists(PATH.$id.'.txt.php')){
			$cur_code_usage_f = file(PATH.$id.'.txt.php');
			if(is_array($cur_code_usage_f) && $cur_code_usage_f[0] != ''){
				$cur_code_usage = explode('|||', $cur_code_usage_f[0]); // [ID, usage01, viewCount, lastViewed, lastUA, lastIP]
				if($cur_code_usage[1] == '') $cur_code_usage[1] = '0';
			}
		}
		if($cur_code_usage_f[0] == ''){
			$cur_code_usage = array();
			$cur_code_usage[1] = '0';
		}
?>
	<li>#<a href="?code=<?php echo get_code($line_d[0]); ?>"><?php echo $id; ?></a>：<?php echo $line_d[2]; ?>（<?php echo $cur_code_usage[1]=='0'?'<span class="text-muted">未使用</span>':'<span class="text-danger">已使用</span>'; ?> <span class="badge" title="查询次数"><?php echo $cur_code_usage[2]; ?></span>，<code><?php echo get_code($id); ?></code>）<ul><li><?php echo $line_d[3]; ?>（<?php echo $line_d[1]; ?>）</li></ul></li>
<?php
	}
?>
</ul>
<?php
}
if($pagetype == 'adminedit'){
?>
<form action="./?action=admin&amp;edit=1" method="POST" role="form" class="container">
	<div class="row">
		<div class="col-xs-12">
		<textarea class="form-control" name="content" id="content" rows="20"><?php echo implode("", $data); ?></textarea>
		</div>
	</div>
	<p>
		<?php if($res > 0){ ?><button class="btn btn-success" type="submit">已保存</button><?php }else if($res === FALSE){ ?>
		<button class="btn btn-warning" type="submit">保存失败</button>
		<?php }else{ ?>
		<button class="btn btn-primary" type="submit">保存</button>
		<?php } ?>
		<input type="hidden" name="action" value="admin" />
		<input type="hidden" name="edit" value="view" />
	</p>
	
</form>
<?php
}
if($pagetype == 'code'){
?>
	<?php if($loggedin){ ?>
	<div class="alert alert-warning">
		<p>
			<form action="./" method="POST" id="form-usage">
			<?php if($cur_code_usage === false || $cur_code_usage[1] === '0'){ ?>
			请先确认下面的使用条款。
			<button type="submit" class="btn btn-danger btn-sm">使用</button>
			<input type="hidden" name="usage" value="1" />
			<?php }else{ ?>
			<strong>优惠码已被使用。</strong>
			<button type="submit" class="btn btn-danger btn-sm" id="btn-unuse">解除</button>
			<input type="hidden" name="usage" value="0" />
			<?php } ?>
			<input type="hidden" name="action" value="modify_usage" />
			<input type="hidden" name="code" value="<?php echo $c; ?>" />
			</form>
		</p>
	</div>
	<?php } ?>
	<h1>优惠码 <?php echo $c; ?> <?php if($cur_code_usage === false || $cur_code_usage[1] === '0'){ ?><span class="label label-success label-small">未使用</span><?php }else{ ?><span class="label label-danger label-small">已使用</span><?php } ?></h1>
	<p>优惠项目：<strong><?php echo $cur_code[2]; ?></strong> <small>（请注意阅读备注）</small></p>
	<p class="text-info">只要在购买时出示优惠码，或直接给我们查看此页面，就可以在满足相应条件后，享受相应的优惠。</p>
	<?php if($cur_code_usage === false || $cur_code_usage[1] === '0'){ ?>
	<form action="./" method="POST" id="form-reset"><p>此优惠码之前被查询 <?php echo ($cur_code_usage[2] > 0)?$cur_code_usage[2]:0; ?> 次<?php if(is_array($cur_code_usage) && $cur_code_usage[2] > 0){ echo '，最后查询时间是 ',$cur_code_usage[3];} ?>。<?php if($loggedin){ ?><?php if($res_delete){echo '<a href="?code=',$c,'" class="btn btn-success btn-sm">已重置</a>';}else{echo '<button type="submit" class="btn btn-danger btn-sm">重置</button>';} ?><input type="hidden" name="action" value="reset" /><input type="hidden" name="code" value="<?php echo $c; ?>" /><?php } ?></p></form>
	<?php }else{ ?>
	<p><strong>此优惠码已被使用，使用时间为 <?php echo $cur_code_usage[3]; ?>。如有疑问请联系我们。</strong></p>
	<?php } ?>
	<blockquote>
		<p>备注：<?php echo $cur_code[3]; ?>（签发者 <?php echo $cur_code[1]; ?>）</p>
		<p>优惠码抵扣总额不能超过付款总额，不能返还为现金，且抵扣必须完整使用，不能折半。</p>
	</blockquote>
	<p><span class="label label-info label-small">ProTip</span> 把本页存到手机主屏幕，使用时更快捷！<small>（本页面暂不支持离线浏览）</small></p>
<?php
if($loggedin){
	echo "<pre>调试信息：\n";
	var_dump($cur_code_usage);
	var_dump($cur_code);
	echo '</pre>';
}
}
if($pagetype == 'error'){
?>
	<h1>出错了</h1>
	<p>您输入的优惠码<?php if($c){echo '（',$c,'）';} ?>无法识别。请重试，或联系我们。</p>
<?php
}
?>
	<footer class="clear">
		<p>
			&copy; [产品名]。
			<?php if($loggedin){ echo '<a href="?action=admin" class="btn btn-default btn-sm">管理</a> <a href="?code=',PASSWORD,'" class="btn btn-default btn-sm">退出</a>';} ?>
		</p>
	</footer>
</div>
<?php if($pagetype == 'index'){ ?>
<script type="text/javascript">
(function(){var a = document.getElementById('code'), b = document.getElementById('form');if((navigator.userAgent.indexOf('Android') != -1 || navigator.userAgent.indexOf('iPhone') != -1) && a){a.addEventListener('keydown', function(e){if(e.which==9){b.submit();}});}})();
</script>
<?php } ?>
<?php if($pagetype == 'code'){ ?>
<script type="text/javascript">
(function(){var a = document.getElementById('btn-unuse'), b = document.getElementById('form-usage'), c = document.getElementById('form-reset');if(a && b){b.addEventListener('submit', function(e){if(!confirm('这是很危险的操作，继续吗？')){e.preventDefault();return false;}});}if(c){c.addEventListener('submit', function(e){if(!confirm('这是很危险的操作，继续吗？')){e.preventDefault();return false;}});}})();
</script>
<?php } ?>
</body></html>