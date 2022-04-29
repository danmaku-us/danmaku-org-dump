<?php
define("COMMENTPOOL_HEADER", '<commentpool pid="%s" comment="%s" readonly="%s" >');
define("COMMENTPOOL_FOOTER", '</commentpool>');
define("COMMENTITEM", '<comment cmtid="%u" stime="%s" suser="%s" playTime="%s" mode="%u" fontsize="%u" color="%06X" %s>%s</comment>');

$HandleActions['ReadInfo'] = 'HandleReadInfo';
$HandleAuth['ReadInfo'] = 'read';

function HandleReadInfo($pn, $auth)
{
    $vpd = new VideoPageData($pn);
    $sl = htmlspecialchars($vpd->SourceLink, ENT_NOQUOTES);
    $vt= $vpd->VideoType->getType();
    $comment = trim(RetrieveAuthSection($pn, "#comment#commentend")."\r\n".RetrieveAuthSection($pn, "#partinfo#partend"));
    $comment = htmlspecialchars($comment, ENT_NOQUOTES);
    $title = htmlspecialchars(PageVar($vpd->Pagename, '$Title'), ENT_NOQUOTES);
    $videostr = htmlspecialchars($vpd->VideoStr, ENT_NOQUOTES);
    $g = $vpd->Group;
    $id = $vpd->DanmakuId;
    /*
    $a['VideoType']=$vpd->VideoType->getType();
    $a['SourceLink']=$vpd->SourceLink;
    $a['DanmakuId']= $id = $vpd->DanmakuId;
    $a['IsMuti'] = $vpd->IsMuti;
    $a['PageName'] = $vpd->Pagename;
    $a['Group'] = $g = $vpd->Group;
    $a['pagetext'] = ReadPage($vpd->Pagename)['text']; 
    
    $a['xmls'] = glob("./uploads/{$g}/{$id}*.xml");
    
    die(json_encode((object)$a));
  */
  
    $text = <<<HEADER
<?xml version="1.0" encoding="UTF-8"?>
<DMF target="{$g}" encrypted="false">
  <meta>
    <srcurl>{$sl}</srcurl>
    <dmfsrc>{$pn}</dmfsrc>
    <title>{$title}</title>
    <comment>{$comment}</comment>
  </meta>
  <video type="{$vt}">{$videostr}</video>
HEADER;

    foreach (glob("./uploads/{$g}/{$id}*.xml") as $cmtfile) {
      $text .= XMLConverter(simplexml_load_file($cmtfile), pathinfo($cmtfile, PATHINFO_FILENAME));
    }
    $text .= '</DMF>';
    die($text);
}

function timeStampToDate($ts)
{
  return date("c", $ts);
}

function secToDuration($sec)
{
  $mod = fmod($sec, 60);
  $min = ($sec - $mod)/60;
  return sprintf("%02u:%06.3F", $min, $mod);
}

function XMLConverter(SimpleXmlElement $xml, $poolName, $comment = "", $readonly = false)
{
  static $boolArray = Array(false => 'false', true => 'true');
  
  $poolText = sprintf(COMMENTPOOL_HEADER,
    $poolName, $comment, $boolArray[$readonly]);
  
  $query = $xml->xpath("comment[1]/attr[@hideeffect]");
  $is2DLand = !empty($query);
  
  foreach ($xml->xpath('//comment') as $cmt) {
    if ($is2DLand) {
      $ExtAttr = sprintf('showeffect="%d" hideeffect="%d" fonteffect="%d" ',
        (string) $cmt->attr['showeffect'],
        (string) $cmt->attr['hideeffect'],
        (string) $cmt->attr['fonteffect']
        );
    } else {
      $ExtAttr = "";
    }
    
    $poolText .= sprintf(COMMENTITEM,
      $cmt['id'],
      timeStampToDate((string) $cmt['sendtime']),
      $cmt['userhash'],
      secToDuration((string) $cmt->attr['playtime']),
      $cmt->attr['mode'],
      $cmt->attr['fontsize'],
      (string) $cmt->attr['color'],
      $ExtAttr,
       htmlspecialchars((string) $cmt->text, ENT_NOQUOTES));
  }
  $poolText .= COMMENTPOOL_FOOTER;
  return $poolText;
}
