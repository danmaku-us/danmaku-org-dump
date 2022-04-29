<?php
class Player
{
	private $playerUrl;
	private $desc;
	private $width; // 像素
	private $height;// 像素
	private $Invisible = TRUE;
	
	public static $playerBase = 'http://danmaku.us/static/players/';
	
	public function __construct($fileName, $desc, $width, $height, $Invisible = TRUE)
	{
		$this->playerUrl = self::$playerBase.$fileName;
		$this->desc = $desc;
		$this->width = intval($width);
		$this->height = intval($height);
		$this->Invisible = $Invisible;
	}
	
	public function __get($name)
	{
		return $this->$name;
	}
}