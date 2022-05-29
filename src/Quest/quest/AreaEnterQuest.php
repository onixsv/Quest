<?php
declare(strict_types=1);

namespace Quest\quest;

use OnixUtils\OnixUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

class AreaEnterQuest extends Quest{

	protected array $posData = [];

	public function __construct(string $name, array $rewards, array $players = [], array $playingPlayers = [], string $type = self::TYPE_COLLECT_ITEM, array $posData = []){
		parent::__construct($name, $rewards, $players, $playingPlayers, $type);
		$this->posData = $posData;
	}

	public function getPosData() : array{
		return $this->posData;
	}

	public function check() : void{
		//parent::check(); // TODO: Change the autogenerated stub
	}

	public function canComplete(Player $player) : bool{
		$x1 = $this->posData["x1"];
		$x2 = $this->posData["x2"];
		$z1 = $this->posData["z1"];
		$z2 = $this->posData["z2"];
		$world = $this->posData["world"];

		return ($x1 <= $player->getLocation()->getX() && $x2 >= $player->getLocation()->getX()) && ($z1 <= $player->getLocation()->getZ() && $z2 >= $player->getLocation()->getZ()) && $player->getWorld()->getFolderName() === $world;
	}

	public function jsonSerialize() : array{
		return array_merge(parent::jsonSerialize(), ["pos" => $this->posData]);
	}

	public function getFailMessage(Player $player) : string{
		return "아직 목표 지역에 도달하지 못했습니다.";
	}

	public function getDescription() : string{
		return "좌표 " . OnixUtils::posToStr($this->getPosition1()) . "를 찾아가시오.\n\n보상: " . implode(", ", array_map(function(Item $item) : string{
				return $item->getName() . " " . $item->getCount() . "개";
			}, $this->rewards));
	}

	public function getPosition1() : Position{
		$x1 = $this->posData["x1"];
		$z1 = $this->posData["z1"];

		$world = Server::getInstance()->getWorldManager()->getWorldByName($this->posData["world"]);

		$y = $world->getHighestBlockAt($x1, $z1);

		return new Position($x1, $y, $z1, $world);
	}

	public function getPosition2() : Position{
		$x2 = $this->posData["x2"];
		$z2 = $this->posData["z2"];

		$world = Server::getInstance()->getWorldManager()->getWorldByName($this->posData["world"]);

		$y = $world->getHighestBlockAt($x2, $z2);

		return new Position($x2, $y, $z2, $world);
	}

	public static function jsonDeserialize(array $data) : AreaEnterQuest{
		return new AreaEnterQuest($data["name"], $data["reward"], $data["players"], $data["playingPlayers"], $data["type"], $data["pos"]);
	}
}