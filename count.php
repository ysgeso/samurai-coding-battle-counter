<?php
// 読み込むページの範囲。初期設定ではかなり大きなページまで設定しておく
$pagebegin = 1;
$pageend = 10000000;
// 読み込む戦闘記録の時間の範囲。初期設定ではかなり未来まで設定しておく
$fromtime = 0;
$totime = strtotime("2099/12/31");
// 結果を出力するファイル名
$outputfilename = "";
// AI名を出力するかどうか（初期設定では出力しない）
$noainame = false;
// 特定のプレーヤーのデータのみ集計する場合の、プレーヤー名
$playername = "";
// 戦闘結果の占領数を出力するかどうか（初期設定では出力しない）
$outputbattleresult = false;
// 順位やレーティングを出力するかどうか（初期設定では出力しない）
$outputrating = false;

// ファイルに出力するデータの初期化
$outputdata = "";

// プログラムを実行した際に渡されたパラメータの処理を行う
// これからチェックするパラメータの番号
$anum = 1;
while ($anum < $argc || $argc == 1) {
  if ($argc == 1 || $argv[$anum] == "-h") {
    print "Battle Result のページの戦闘結果を tsv 形式で出力するプログラム。\n";
    print "  -n オプションをつけない場合は、以下の順にtsv形式で出力する。\n    戦闘時刻、先手のユーザ名、先手のAI名、後手のユーザ名、後手のAI名、\n    勝敗（0:先手勝利、1:後手勝利、2:引き分け）、戦闘結果のURL\n\n";
    print "  注意：php-xml が必要です。インストールされていない場合は apt-get などを使ってインストールしてください。\n\n";
    print "使い方\n";
    print "count.php [-h] [-p pstart pend] [-t fromtime totime] [-n plname] [-b] [-r] [-noainame] outputfile\n";
    print "  -h\n    ヘルプの表示。\n";
    print "  -p pstart pend\n    Battle Result のページの、pstart から pend ページまでの結果を集計する。\n";
    print "  -t fromtime totime\n   Battle Result のページの、fromtime から totime までの結果を集計する。\n";
    print "    時間は例えば y/m/d h:m:s など、phpのstrtotimeが認識できるフォーマットで記述する。\n    このオプションを一番最後に記述し、totime を省略した場合は、最新のデータまで集計する。\n";
    print "  -n plname\n    plname のプレーヤーのデータのみ集計する。\n    この場合、出力データは以下の形式になる。先手後手や勝敗はいずれもplnameから見た結果。\n";
    print "      戦闘時刻、相手のユーザ名、相手のAI名、0:先手、1:後手、勝敗、戦闘結果のURL\n";
    print "  -b\n    戦闘結果の占領数を追加で出力する。(-n をつけた場合は自分、相手の順で出力する）\n";
    print "  -r\n    先手、後手の順で、AIの順位、レーティングを追加で出力する。(-n をつけた場合は相手のみ出力する）\n";
    print "  -noainame\n    AI名を出力しない。\n";
    print "  outputfile\n    結果を出力するファイル名（省略不可）。\n\n";
    print "使用例\n  php count.php -n playername output.tsv -t \"2017/2/6 12:0:0\"\n  2017/2/6 12:0:0 以降の playername の戦闘結果の一覧を output.tsv に出力する。\n";
    return;
  }
  else if ($argv[$anum] == "-p") {
    $anum++;
    if ($anum < $argc) {
      $pagebegin = intval($argv[$anum]);
      if ($pagebegin < 1) {
        $pagebegin = 1;
      }
      $anum++;
    }
    if ($anum < $argc) {
      $pageend = intval($argv[$anum]);
      $anum++;
    }   
  }
  else if ($argv[$anum] == "-t") {
    $anum++;
    if ($anum < $argc) {
      $fromtime = strtotime($argv[$anum]); 
      $anum++;
    }
    if ($anum < $argc) {
      $totime = strtotime($argv[$anum]); 
      $anum++;
    }
  }
  else if ($argv[$anum] == "-n") {
    $anum++;
    $playername = $argv[$anum];
    $anum;
  }
  else if ($argv[$anum] == "-b") {
    $anum++;
    $outputbattleresult = true;
  }
  else if ($argv[$anum] == "-noainame") {
    $anum++;
    $noainame = true;
  }
  else if ($argv[$anum] == "-r") {
    $anum++;
    $outputrating = true;
  }
  else {
    $outputfilename = $argv[$anum];
    $anum++;
  }
}

// 出力ファイルが指定されていない場合は終了
if ($outputfilename == "") {
  print "出力ファイルを指定しください。\n";
  return;
}

// 開始メッセージ。バージョンと、いくつかの設定された条件なども出力する
print "start battle count ver 1.6 last update 2017/02/11\npage = " . $pagebegin . " - " . $pageend . "\ntime = " . date("Y/m/d H:i:s", $fromtime) . " - " . date("Y/m/d H:i:s", $totime) . "\n";

// レーティングを出力する場合、ランキングのページからランキングのデータを収集する
if ($outputrating) {
  // 何ページあるかわからないので、最大 1000 ページ分、ランキングのページを順番に読み込む
  for ($page = 1 ; $page < 1000 ; $page++) {
  print "start loading ranking page " . $page . "\n";
    // ページを読み込み、$rankinghtml に記録する
    $rankinghtml = file_get_contents("https://arena.ai-comp.net/contests/1?ranking_page=" . $page . "#ranking");
    // simplexml_load_string は、 <div><img ...>aaa</div> のようなデータを読み込んだ場合 aaa の部分が無視されてしまうようなので、
    // <div><img ...><span>aaa</span> のように置換する。ついでに、その前後の空白や改行を削除する
    $rankinghtml = preg_replace("/(<img[^>]*>)\s*(.*[^\s])\s*(<\/a>)/", "$1<span>$2</span>$3", $rankinghtml);
    // header, footer タグがあると simplexml_load_string で warning がでるので、それらのタグを div に置き換える 
    $rankinghtml = preg_replace("/<header([^>]*)>/", "<div$1>", $rankinghtml);
    $rankinghtml = str_replace("</header>", "</div>", $rankinghtml);
    $rankinghtml = preg_replace("/<footer([^>]*)>/", "<div$1>", $rankinghtml);
    $rankinghtml = str_replace("</footer>", "</div>", $rankinghtml);
    // xml に変換する。これをしないと元の ｈｔｍｌ の内容に変な所があった場合、simplexm_load_string でエラーやワーニングが出る。
    $document = new DOMDocument();
    $document->loadHTML($rankinghtml);
    $xml = $document->saveXML();
    $xmlobj = simplexml_load_string($xml);
    // 得られたデータはこのままでは扱いにくいので、一旦 json の形式に変換してから戻すことで、連想配列の形式に変換する
    $xmlarray = json_decode(json_encode($xmlobj), true);
    // ランキングの情報が入っているテーブル
    $rankingtable = $xmlarray['body']['div'][1]['div'][1]['div']['div']['div'][0]['table']['tbody'];
    // これが空ならそのページにランキングの情報がないので、終了する
    if (count($rankingtable) == 0) {
      break;
    }
    // テーブルの列（ｔｒ）が１つしかない場合は配列にならないようなので、このあと foreach を使えるように配列にする
    if (count($rankingtable['tr']) == 1) {
      $rankingtable['tr'] = [ $rankingtable['tr'] ];
    }
    // 各列のデータから、ユーザ名、AI名、順位、レーティング、戦闘回数の情報を取得し、連想配列 $ranking に記録する
    // test のような同じユーザ名、AI名が複数存在する可能性があるので、「ユーザ名：：：AI名」を連想配列のインデックスとする
    // （ユーザ名、AI名共に同じものがあった場合のことは想定しない)
    foreach ($rankingtable['tr'] as $key => $var) {
      $user = trim($var['td'][1]);
      $ai = $var['td'][2]['a']['span'];
      $index = $user . ":::" . $ai;
      $ranking[$index]['rank'] = intval($var['td'][0]);
      $ranking[$index]['user'] = $user;
      $ranking[$index]['ai'] = $ai;
      $ranking[$index]['rating'] = intval($var['td'][3]);
      // 使わないが、今後のバージョンで使うかもしれないので、戦闘回数も記録しておく
      $ranking[$index]['bconunt'] = intval($var['td'][4]);
    }
  }
}

// 戦闘結果のページから戦闘結果を読み込み、条件にあったものをファイルに出力する
// 指定されたページ数のページの戦闘結果を順番に読み込む
for ($page = $pagebegin ; $page <= $pageend ; $page++) {
  // 途中経過のメッセージ
  print "start loading page " . $page . " ";
  // HTMLを読み込み、$htmlに記録する
  $html = file_get_contents("https://arena.ai-comp.net/contests/1?battle_results_page=" . $page . "#battle_results");
  // Win: <span> の部分が間違っているので正しい Win: </span> に置換する
  $html = str_replace("Win: <span>", "Win: </span>", $html);
  // 上記と同様の、間違った HTML の修正
  $html = str_replace("Watch", "<span>Watch</span>", $html);
  // header, footer タグがあると simplexml_load_string で warning がでるので、それらのタグを div に置き換える 
  $html = preg_replace("/<header([^>]*)>/", "<div$1>", $html);
  $html = str_replace("</header>", "</div>", $html);
  $html = preg_replace("/<footer([^>]*)>/", "<div$1>", $html);
  $html = str_replace("</footer>", "</div>", $html);
  // 勝利した場合の ユーザ名、AI名 の部分を <span> </span> で囲う。また、その前後の空白や改行を削除する
  $html = preg_replace("/(Win: <\/span>)\s*([^<]*[^\s])\s*(<span class=\"split\">\/<\/span>)\s*([^<]*[^\s])\s*(<\/a>)/", "$1<span>$2</span>$3<span>$4</span>$5", $html);
  // 勝利していない場合の ユーザ名/AI名 の部分を <span> </span> で囲う。また、その前後の空白や改行を削除する
  $html = preg_replace("/(<a[^>]*>)\s*([^<]*[^\s])\s*(<span class=\"split\">\/<\/span>)\s*([^<]*[^\s])\s*(<\/a>)/", "$1<span>$2</span>$3<span>$4</span>$5", $html);
  // xml に変換し、連想配列の形に変換する
  $document = new DOMDocument();
  $document->loadHTML($html);
  $xml = $document->saveXML();
  $xmlobj = simplexml_load_string($xml);
  $xmlarray = json_decode(json_encode($xmlobj), true);
  // 戦闘結果のテーブルの列を表す配列
  $table = $xmlarray['body']['div'][1]['div'][1]['div']['div']['div'][1]['table']['tbody']['tr'];
  // 列の数が 0 の場合は終了する
  if (count($table) == 0) {
    break;
  }
  // テーブルの最初のデータかどうかを表すフラグ
  $firstflag = true;
  foreach ($table as $key => $var) {
    // テーブルの Time (戦闘時刻）の列のデータを取得し、数値比較可能な形式に変換する
    $time = strtotime($var['td'][0]);
    // テーブルの最初のデータに限り、その戦闘時刻を途中経過メッセージとして表示する
    if ($firstflag == true) {
      print date("Y/m/d H:i:s", $time) . "\n";
    }
    $firstflag = false;
    // 戦闘時刻が時間の条件より前であればファイルにデータを出力して終了する
    if ($time < $fromtime) {
	file_put_contents($outputfilename, $outputdata);
	return;
    }
    // 戦闘時刻が時間の条件より後であれば、この記録は飛ばす
    if ($time > $totime) {
      continue;
    }
    // テーブルの Replay の列のデータが文字列であれば、その戦闘は行われている
    // （エラーの場合は、ここが文字列にはならないのでこれでエラーが起きた戦闘を排除できる）
    if (!is_string($var['td'][2])) {
      // 勝敗結果の初期化（引き分けにして置く）
      $win = 2;
      // 先手、後手の順でプレーヤー名、AI名を取得する
      for ($i = 0 ; $i < 2 ; $i++) {
        // 各プレーヤーのデータ。以下のデータが配列で入っている
        // 勝っていた場合 0: 「Win:」 １： プレーヤー名 2: 「/」 3: AI 名
        // 勝っていない場合 0: プレーヤー名 1: 「/」 2: AI 名
        $data = $var['td'][1]['a'][$i];
        // span がいくつかるか数える
        $count = count($data['span']);
        // $count - 1 番めに プレーヤー名が、$count - 3 番目に AI 名が記録されている
        $plname[$i] = $data['span'][$count - 1];
        $ainame[$i] = $data['span'][$count - 3];
        // レーティングを出力する場合、順位とレーティングを記録する
        if ($outputrating == true) {
          $index = $plname[$i] . ":::" . $ainame[$i];
          // 投稿中などの理由でそのAIがランキングに一時的に入っていない可能性がある。
          // その場合は '?' を記録することにする
          if (array_key_exists($index, $ranking)) {
            $rank[$i] = $ranking[$index]['rank'];
            $rating[$i] = $ranking[$index]['rating'];
          }
          else {
            $rank[$i] = "?";
            $rating[$i] = "?";
          }
        }
        // 勝っていた場合、どちらが勝ったかを記録する
        if ($count == 4) {
          $win = $i;
        } 
      }

      // プレーヤー名を指定していた場合で、どちらのプレーヤーとも一致しない場合は、このデータは飛ばす
      if ($playername != "" && $playername != $plname[0] && $playername != $plname[1]) {
        continue;
      }

      // 出力データに戦闘時刻を追加する
      $outputdata .= date("Y/m/d H:i:s", $time) . "\t";
      // プレーヤー名を指定していない場合は、先手、後手の順で出力する
      if ($playername == "") {
        for ($i = 0 ; $i < 2 ; $i++) {
          // プレーヤー名を出力する
          $outputdata .= $plname[$i] . "\t";
          // 必要があればAI名を出力する
          if ($noainame == false) {
            $outputdata .= $ainame[$i] . "\t";
          }
        }
      }
      // プレーヤー名を指定していた場合
      else {
        // 指定したプレーヤーが先手か後手かを調べ、$plに記録する（0:先手、1:後手）
        if ($playername == $plname[0]) {
          $pl = 0;
        }
        else {
          $pl = 1;
          // 後手の場合、$win を指定したプレーヤーから見た勝利に変更する
          if ($win != 2) {
            $win = 1 - $win;
          }
        }
        // 相手のプレーヤー名を出力する
        $outputdata .= $plname[1 - $pl] . "\t" . $pl . "\t";
        // 必要があれば相手のAI名を出力する
        if ($noainame == false) {
          $outputdata .= $ainame[1 - $pl] . "\t";
        }
      }
      // どちらが勝ったか、戦闘記録のURLを出力する
      $outputdata .= $win . "\t" . "https://arena.ai-comp.net" . $var['td'][2]['a']['@attributes']['href'] . "\t";

      // 戦闘結果の占領数を出力する場合
      if ($outputbattleresult == true) {
        print "  loading battle " . $var['td'][2]['a']['@attributes']['href'] . "\n";
        // 戦闘結果のページを読み込む
        $battlehtml = file_get_contents("https://arena.ai-comp.net" . $var['td'][2]['a']['@attributes']['href']);
        // 占領数はHTMLの最後の territories から取ってこれるので、取ってくる
        preg_match("/territories\":\[(\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\]}\]\}/", $battlehtml, $matches);
        // 「先手または、プレーヤーを指定した場合のプレーヤーの占領数」、「もう片方の占領数」の順で出力する
        if ($playername == "" || $pl == 0) {
          $outputdata .= ($matches[1] + $matches[2] + $matches[3]) . "\t" . ($matches[4] + $matches[5] + $matches[6]) . "\t";
        }
        // 「後手または、プレーヤーを指定した場合の相手の占領数」、「もう片方の占領数」の順で出力する
        else {
          $outputdata .= ($matches[4] + $matches[5] + $matches[6]) . "\t" . ($matches[1] + $matches[2] + $matches[3]) . "\t";
        }
      }
      // レーティングを出力する場合
      if ($outputrating == true) {
        // プレーヤーを指定していない場合、先手、後手の順で、順位、レーティングを出力する
        if ($playername == "") {
          $outputdata .= $rank[0] . "\t" . $rating[0] . "\t" . $rank[1] . "\t" . $rating[1] . "\t";
        }
        // プレーヤーを指定している場合、相手の順位、レーティングを出力する
        else {
          if ($pl == 0) {
            $outputdata .= $rank[1] . "\t" . $rating[1] . "\t";
          }
          else {
            $outputdata .= $rank[0] . "\t" . $rating[0] . "\t";
          }
        }
      }
      // 改行を出力し、この戦闘結果のデータの出力を終了する
      $outputdata .= "\n";
    }
  }
}
// すべて終了したのでファイルに出力する
file_put_contents($outputfilename, $outputdata);
?>
