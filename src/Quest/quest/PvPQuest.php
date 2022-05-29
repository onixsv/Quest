<?php
declare(strict_types=1);

namespace Quest\quest;

use pocketmine\item\Item;
use pocketmine\player\Player;

class PvPQuest extends Quest{

	protected int $kill;

	protected array $playingPlayers = [];

	public function __construct(string $name, array $rewards, array $players = [], array $playingPlayers = [], string $type = self::TYPE_COLLECT_ITEM, int $kill = 0){
		parent::__construct($name, $rewards, $players, $playingPlayers, $type);
		$this->kill = $kill;
	}

	public function addPlayingPlayer(Player $player) : void{
		$this->playingPlayers[$player->getName()] = 0;
	}

	public function addKill(Player $player) : void{
		$this->playingPlayers[$player->getName()] += 1;
	}

	public function setKill(int $kill) : void{
		$this->kill = $kill;
	}

	public function getDescription() : string{
		return "PvP를 하여 §d" . $this->kill . "§f킬을 달성하세요.\n\n보상: " . implode(", ", array_map(function(Item $item) : string{
				return $item->getName() . " " . $item->getCount() . "개";
			}, $this->rewards));
	}

	public function getFailMessage(Player $player) : string{
		return "아직 §d" . $this->kill . "§f킬을 달성하지 못하였습니다. 현재 내 킬: §d" . $this->playingPlayers[$player->getName()] . "§f킬";
	}

	public function jsonSerialize() : array{
		return array_merge(parent::jsonSerialize(), ["kill" => $this->kill]);
	}

	public static function jsonDeserialize(array $data) : PvPQuest{
		return new PvPQuest($data["name"], $data["reward"], $data["players"], $data["playingPlayers"], $data["type"], $data["kill"]);
	}
}