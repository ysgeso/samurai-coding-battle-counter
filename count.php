<?php
$outputtype = 0;
$pagebegin = 1;
$pageend = 10000000;
$fromtime = 0;
$totime = strtotime("2099/12/31");
$outputfilename = "";
$noainame = false;
$anum = 1;
$outputdata = "";
$playername = "";
$outputbattleresult = false;
while ($anum < $argc || $argc == 1) {
  if ($argc == 1 || $argv[$anum] == "-h") {
    print "Battle Result のページの戦闘結果を tsv 形式で出力するプログラム。\n";
    print "  -n オプションをつけない場合は、以下の順にtsv形式で出力する。\n    戦闘時刻、先手のユーザ名、先手のAI名、後手のユーザ名、後手のAI名、\n    勝敗（0:先手勝利、1:後手勝利、2:引き分け）、戦闘結果のURL\n\n";
    print "  注意：php-xml が必要です。インストールされていない場合は apt-get などを使ってインストールしてください。\n\n";
    print "使い方\n";
    print "count.php [-h] [-p pstart pend] [-t fromtime totime] [-f outputfile] [-a ainame] [-s] outputfile\n";
    print "  -h\n    ヘルプの表示。\n";
    print "  -p pstart pend\n    Battle Result のページの、pstart から pend ページまでの結果を集計する。\n";
    print "  -t fromtime totime\n  　Battle Result のページの、fromtime から totime までの結果を集計する。\n";
    print "    時間は例えば y/m/d h:m:s など、phpのstrtotimeが認識できるフォーマットで記述する。\n    このオプションを一番最後に記述し、totime を省略した場合は、最新のデータまで集計する。\n";
    print "  -n plname\n    plname のプレーヤーのデータのみ集計する。\n    この場合、出力データは以下の形式になる。先手後手や勝敗はいずれもplnameから見た結果。\n";
    print "      戦闘時刻、相手のユーザ名、相手のAI名、0:先手、1:後手、勝敗、戦闘結果のURL\n";
    print "  -b\n    戦闘結果の占領数を追加で出力する。(-n をつけた場合は自分、敵の順で出力する）\n";
    print "  -noainame\n    AI名を出力しない。\n";
    print "  outputfile\n    結果を出力するファイル名（省略不可）。\n\n";
    print "使用例\n  php count.php -n playername output.tsv -t \"2017/2/6 12:0:0\"\n　　2017/2/6 12:0:0 以降の playername の戦闘結果の一覧を output.tsv に出力する。\n";
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
  else {
    $outputfilename = $argv[$anum];
    $anum++;
  }
}

if ($outputfilename == "") {
  print "出力ファイルを指定しください。\n";
  return;
}

print "start battle count\npage = " . $pagebegin . " - " . $pageend . "\ntime = " . date("Y/m/d H:i:s", $fromtime) . " - " . date("Y/m/d H:i:s", $totime) . "\n";

for ($page = $pagebegin ; $page <= $pageend ; $page++) {
  print "start loading page " . $page . " ";
  $html = file_get_contents("https://arena.ai-comp.net/contests/1?battle_results_page=" . $page . "#battle_results");
  $html = str_replace("Win: <span>", "Win: </span>", $html);
  $html = str_replace("Win: <span>", "Win: </span>", $html);
  $html = str_replace("Watch", "<span>Watch</span>", $html);
  $html = preg_replace("/<header([^>]*)>/", "<div$1>", $html);
  $html = str_replace("</header>", "</div>", $html);
  $html = preg_replace("/<footer([^>]*)>/", "<div$1>", $html);
  $html = str_replace("</footer>", "</div>", $html);
  $html = preg_replace("/(Win: <\/span>)\s*([^<]*[^\s])\s*(<span class=\"split\">\/<\/span>)\s*([^<]*[^\s])\s*(<\/a>)/", "$1<span>$2</span>$3<span>$4</span>$5", $html);
  $html = preg_replace("/(<a[^>]*>)\s*([^<]*[^\s])\s*(<span class=\"split\">\/<\/span>)\s*([^<]*[^\s])\s*(<\/a>)/", "$1<span>$2</span>$3<span>$4</span>$5", $html);

  $document = new DOMDocument();
  $document->loadHTML($html);
  $xml = $document->saveXML();
  $xmlobj = simplexml_load_string($xml);
  $xmlarray = json_decode(json_encode($xmlobj), true);
  $table = $xmlarray['body']['div'][1]['div'][1]['div']['div']['div'][1]['table']['tbody']['tr'];
  if (count($table) == 0) {
    break;
  }
  $count = 0;
  foreach ($table as $key => $var) {
    $time = strtotime($var['td'][0]);
    if ($count == 0) {
      print date("Y/m/d H:i:s", $time) . "\n";
    }
    $count++;
    if ($time < $fromtime) {
			file_put_contents($outputfilename, $outputdata);
      return;
    }
    if ($time > $totime) {
      continue;
    }
    if (!is_string($var['td'][2])) {
      $win = 2;
      for ($i = 0 ; $i < 2 ; $i++) {
        $data = $var['td'][1]['a'][$i];
        $count = count($data['span']);
        $ainame[$i] = $data['span'][$count - 3];
        $plname[$i] = $data['span'][$count - 1];
        if ($count == 4) {
          $win = $i;
        } 
      }

      if ($playername != "" && $playername != $plname[0] && $playername != $plname[1]) {
        continue;
      }

      $outputdata .= date("Y/m/d H:i:s", $time) . "\t";
      if ($playername == "") {
        for ($i = 0 ; $i < 2 ; $i++) {
          if ($noainame == false) {
            $outputdata .= $ainame[$i] . "\t";
          }
          $outputdata .= $plname[$i] . "\t";
          if ($count == 4) {
            $win = $i;
          } 
        }
      }
      else {
        if ($playername == $plname[0]) {
          $i = 1;
        }
        else {
          $i = 0;
          if ($win != 2) {
            $win = 1 - $win;
          }
        }
        if ($noainame == false) {
          $outputdata .= $ainame[$i] . "\t";
        }
        $outputdata .= $plname[$i] . "\t" . (1 - $i) . "\t";
      }
      $outputdata .= $win . "\t" . "https://arena.ai-comp.net" . $var['td'][2]['a']['@attributes']['href'] . "\t";

      if ($outputbattleresult == true) {
        $battlehtml = file_get_contents("https://arena.ai-comp.net" . $var['td'][2]['a']['@attributes']['href']);
        preg_match("/territories\":\[(\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\]}\]\}/", $battlehtml, $matches);
        if ($playername == "" || $i == 1) {
          $outputdata .= ($matches[1] + $matches[2] + $matches[3]) . "\t" . ($matches[4] + $matches[5] + $matches[6]) . "\t";
        }
        else {
          $outputdata .= ($matches[4] + $matches[5] + $matches[6]) . "\t" . ($matches[1] + $matches[2] + $matches[3]) . "\t";
        }
      }
      $outputdata .= "\n";
    }
  }
}
file_put_contents($outputfilename, $outputdata);
?>
