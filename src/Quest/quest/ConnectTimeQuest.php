<?php
declare(strict_types=1);

namespace Quest\quest;

use ConnectTime\ConnectTime;
use OnixUtils\OnixUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;

class ConnectTimeQuest extends Quest{

	protected $time;

	public function __construct(string $name, array $rewards, array $players = [], array $playingPlayers = [], string $type = self::TYPE_COLLECT_ITEM, int $time){
		parent::__construct($name, $rewards, $players, $playingPlayers, $type);
		$this->time = $time;
	}

	public function jsonSerialize() : array{
		return array_merge(parent::jsonSerialize(), ["time" => $this->time]);
	}

	public static function jsonDeserialize(array $data) : ConnectTimeQuest{
		return new ConnectTimeQuest($data["name"], $data["reward"], $data["players"], $data["playingPlayers"], $data["type"], $data["time"]);
	}

	public function getTime() : int{
		return $this->time;
	}

	public function setTime(int $time) : void{
		$this->time = $time * 60;
	}

	public function getFailMessage(Player $player) : string{
		return "총 " . OnixUtils::convertTimeToString($this->time) . " 중 " . OnixUtils::convertTimeToString(ConnectTime::getInstance()->getTimeForToday($player->getName())) . " 접속했습니다.";
	}

	public function getDescription() : string{
		return OnixUtils::convertTimeToString($this->time) . " 동안 접속하기.\n\n보상: " . implode(", ", array_map(function(Item $item) : string{
				return $item->getName() . " " . $item->getCount() . "개";
			}, $this->rewards));
	}

	public function canComplete(Player $player) : bool{
		return ConnectTime::getInstance()->getTimeForToday($player->getName()) >= $this->time;
	}

	public function getProgress(Player $player) : float{
		$total = $this->time;

		$my = ConnectTime::getInstance()->getTimeForToday($player->getName());

		if($total === 0 || $my === 0)
			return 0.0;

		if($my > $total){
			return (float) 1;
		}

		return (float) ($my / $total);
	}
}