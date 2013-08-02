<?php
// The sample of the Facebook application for Japanese 
// Facebookアプリのサンプル：名探偵メーカー 2012.08.21 森 雅秀 (masahide@techmori.jp)
// ※が2カ所あります。自分のアプリの基本設定画面の情報に変更して使って下さい。(合計で3つの値)

// ■初期化処理
mb_internal_encoding("UTF-8"); // 文字コード
// ※以下のAPP_URLは基本設定画面のFacebook上のアプリ：キャンバスページのURLに変更して下さい
$APP_URL = "http://apps.facebook.com/XXXXXXX"; // アプリ実行のURL

// ■Facebook提供 php-SDK の読み込み
require('../src/facebook.php');

// ■appIDとsecretを渡して php-SDK の使用開始
// ※以下のappIDとsecretは基本設定画面の値に変更して下さい
$facebook = new Facebook(array('appId' => 'YYYYYYYYYYYYYYYYY',
			       'secret' => 'ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ'));

// ■ユーザIDとアクセストークンを取得
$user = $facebook->getUser();
$access_token = $facebook->getAccessToken();

// ■Facebook認証のチェック
if ($user) { // ユーザーIDが取得出来ているので認証済み
  try {
    // プロフィール情報を日本語で取得
    $me = $facebook->api('/me?locale=ja');
    $name = $me["name"];
    // プロフィール画像を12個まで取得
    $FQL = 'SELECT pid,src_big FROM photo WHERE aid IN (SELECT aid FROM album WHERE owner=' . $user . ' and name="Profile Pictures") order by pid desc limit 12';
    $pics = $facebook->api('/fql?q=' . urlencode($FQL));
  } catch (FacebookApiException $e) {
    if (1) { // 開発中はここでエラー表示後に終了、0にするとFacebook提供サンプルと同じ処理に
      echo $e;
      exit();
    }
    error_log($e);
    $user = null;
  }
}

if (!$user) { // このアプリの認証画面を表示する
  // ■権限許可用URLの作成
  // http://fb.dev-plus.jp/reference/coreconcepts/api/permissions/
  // もし作成中に権限を変更した時には以下のアドレスで使用アプリから削除して、新規に実行して下さい。
  // https://www.facebook.com/settings/?tab=applications
  $permis = "user_photos"; // ユーザー写真（プロフィール）
  $url = $facebook->getLoginUrl(array('scope' => $permis));

  // ■アプリ未登録ユーザーなら facebook の認証ダイアログページへ遷移
  echo "<script type='text/javascript'>top.location.href = '$url';</script>";
  exit();
}

// ■Facebookとアプリ間の情報を取得
$signe = $facebook->getSignedRequest();
// ■もしFacebook外で呼ばれていたらFacebookのURLへ移動
if (!$signe["oauth_token"]) {
  echo "<script type='text/javascript'>top.location.href = '$APP_URL';</script>";
  exit;
}

// ◆画像格納用フォルダのチェック
$dir = "temp";
if (file_exists($dir) == FALSE) { // $dirが存在していない場合には作成
  mkdir($dir, 0777);
}

// ◆使用するプロフ画像の選択
$p = 0;
if ( ($_GET["P"] >= 1) && ($_GET["P"] <= 12) ) {
  $p = intval($_GET["P"]);
}

// ◆使用する画像ファイルをサーバーに保存する
$picture = $pics["data"][$p]["src_big"];
$img_name = $user  . "." . substr($picture, -3);
file_put_contents($img_name, file_get_contents($picture));

// ◆イメージマジックで使用する画像ファイルを読み込む
$im = new Imagick($img_name);
// ◆イメージマジックで書き出す画像サイズを200x200に
$im->cropThumbnailImage(200, 200);
$im->setImagePage(200, 200, 0, 0);

// ◆イメージマジックでプロフ画像を加工する
// イメージマジックの各関数については以下を参照
// http://www.php.net/manual/ja/book.imagick.php

// ◆セピア加工
$rr = mt_rand(7, 10) * 10; // 70, 80, 90, 100
$im->sepiaToneImage($rr);
// ◆集中線の描画
$draw = new ImagickDraw(); // 線描画用の設定
$draw->setStrokeColor(new ImagickPixel('black'));
$p2 = 2 * 3.141592; // 2π
// 円の一周分を乱数でちらしながら線を描画
for ($i = 0.0; $i < $p2; $i += (0.1 / mt_rand(2, 8))) {
  $r0 = mt_rand(80, 120);
  $x0 = cos($i) * $r0;
  $y0 = sin($i) * $r0;
  $x1 = cos($i) * 200;
  $y1 = sin($i) * 200;
  $draw->line(100 + $x0, 100 + $y0, 100 + $x1, 100 + $y1);
}
$im->drawImage($draw);

// ◆イメージマジックで加工した画像を書き出す
$out_name = $dir . "/" . date("ymdHis") . "_" . $img_name;
$im->writeImage($out_name);
$im->destroy();
unlink($img_name); // 読み込み用画像ファイル削除

// ◆プロフ画像の選択
$D = '<table class="tbl"><tr valign="top">';
for ($i = $j = 0; $pics["data"][$i]["src_big"] != ""; $i++) {
  $D .= '<td><a href="' . $APP_URL . '?P=' . $i . '" target="_top">';
  $D .= '<img src="' . $pics["data"][$i]["src_big"] . '" border="0" width="120">';
  $D .= '</a></td>';
  if ($j++ > 4) {
    $D .= '</tr><tr valign="top">';
    $j = 0;
  }
}
$D .= '</tr></table><br><br>';

// ◇投稿テキストの作成用関数
function make_desc($name) {
  $r = mt_rand(0, 2); // 0, 1, 2
  if ($r == 0) { // 1/3の確率で
    $type = array('ボス', 'スナイパー', '研究者', '殺し屋', '運び屋', '怪盗',
		  '発明家', '壊し屋', '色仕掛け担当', '実行犯', '情報収集役');
    $code = array('泡盛', '芋焼酎', 'どぶろく', 'ギムレット', 'アイスブレーカー',
		  'アースクエイク', 'ヴェスパー', 'エンジェルフェイス', 'カミカゼ',
		  'キャロル', 'クレオパトラ', 'グラスホッパー', 'ゴッドファーザー',
		  'ゴッドマザー', 'サングリア', 'シルビア', 'スコーピオン', 'ダイキリ',
		  'スレッジハンマー', 'チャイナキッス', 'ソルティドッグ', 'ミモザ',
		  'ブルーハワイ', 'ブルームーン', 'ベルベッドキス', 'マイアミビーチ',
		  'マタドール', 'マティーニ', 'マルガリータ', 'モヒート', 'レッドアイ');
    $tcount = count($type) - 1;
    $ccount = count($code) - 1;
    $val[0] = '国際指名手配　' . $name;
    $val[1] = '黒ずくめの組織の' . $type[mt_rand(0, $tcount)] . '。';
    $val[1] .= 'コードネームは' . $code[mt_rand(0, $ccount)] . '、';
    $val[1] .= '使用した偽名として「' . $name . '」が確認されている。';
  } else { // 2/3の確率で
    $kodomo = array('子供', '私立探偵', 'マジシャン', '高校生', '凡人', '発明家',
		    'FBI', '刑事', '大学生', '大人', '中年', '老人', '不審者');
    $otona  = array('大人', '老人', 'コンピュータ', '居眠り', '子供', '居眠り',
		    '熱血', '冷静', 'ヤマカン', '明晰', 'おっさん', '少女', '策略家');
    $kcount = count($kodomo) - 1;
    $ocount = count($otona) - 1;
    $val[0] = '名探偵　' . $name;
    $val[1] = 'たった一つの真実見抜く！見た目は' . $kodomo[mt_rand(0, $kcount)] . '、';
    $val[1] .= '頭脳は' . $otona[mt_rand(0, $ocount)] . '、その名は、名探偵　' . $name;
  }
  return $val;
}

// ◆投稿テキストの作成実行
$descs = make_desc($name);

// ◆リンク投稿の画像URL
$dir = str_replace("index.php", "", $_SERVER["SCRIPT_NAME"]);
$url = "https://" . $_SERVER["HTTP_HOST"] . $dir . $out_name;

// ◆IEでの画像キャッシュ問題対応
$img_name .= "?" . date("mdHis");

// JavaScript部分に関するコメント
// FB.init : appIDを渡して JavaScript-SDK の使用開始
// FB.Canvas.setAutoGrow : 自動iframeサイズ調整
// FB.ui : Facebook投稿用ポップアップ呼び出し
?>
<!doctype html>
<html lang="ja">
<head>
<script type="text/javascript" src="https://connect.facebook.net/ja_JP/all.js"></script>
<script type="text/javascript">
window.fbAsyncInit = function() {
  FB.init({appId: '<?php echo $facebook->getAppId(); ?>', status: true, cookie: true, xfbml: true});
  FB.Canvas.setAutoGrow();
};
function postToFeed() {
  var obj = {
  method: 'feed',
  link: '<?php echo $APP_URL;?>',
  picture: '<?php echo $url;?>',
  name: '<?php echo $descs[0];?>',
  caption: '★名探偵メーカーからの投稿',
  description: '<?php echo $descs[1];?>'
  };
  function callback(response) {
    if (response && response.post_id) {
      alert('ウォールに投稿されました');
    }
  }
  FB.ui(obj, callback);
}
</script>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>名探偵メーカー</title>
</head>
<body>
<div id="fb-root" style="font-size:16px;">
<h1>名探偵メーカー</h1>
<big style="line-height:1.4;">
<?php echo '<strong>' . $descs[0] . '</strong><br>'; ?>
<?php echo $descs[1] . '<br><br>'; ?>
</big>
<img src="<?php echo $out_name;?>" height="140">
  
<button onClick="postToFeed();">ウォールに投稿する</button><br>
<br><br>
<?php if ($_GET["DBG"]) {
  for ($i = 0; $i < 20; $i++) {
    $descs = make_desc($name);
    echo $descs[0] . '<br>';
    echo $descs[1] . '<br><hr>';
  }
} ?>
<h2>プロフ画像の選択</h2>
使用したい画像をクリックして下さい。<br>
<?php echo $D;?>
<hr>
このFacebookアプリは以下のサンプルプログラムから作成されています。<br>
<a href="http://fb.dev-plus.jp/column2/column2_17/" target="_blank">
Facebookアプリ開発入門　サンプルアプリ特別編　プロフ画像の加工</a><br>
<br>
これと同じようなアプリが作れるようにプログラムソースの解説を行っています。<br>
こちらの記事にいいね！いただければ励みになります。よろしくお願いします。<br>
開発者：<a href="https://www.facebook.com/masazangi" target="_blank">森　雅秀</a>
</div>
</body>
</html>