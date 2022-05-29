<?php
declare(strict_types=1);

namespace Quest\quest;

use pocketmine\item\Item;
use pocketmine\player\Player;

class CraftQuest extends Quest{

	protected ?Item $output;

	public function __construct(string $name, array $rewards, array $players = [], array $playingPlayers = [], string $type = self::TYPE_CRAFT, Item $output = null){
		parent::__construct($name, $rewards, $players, $playingPlayers, $type);
		$this->output = $output;
	}

	public function addPlayingPlayer(Player $player) : void{
		$this->playingPlayers[$player->getName()] = ["time" => time(), "count" => 0];
	}

	public function removePlayingPlayer(Player $player) : void{
		unset($this->playingPlayers[$player->getName()]);
	}

	public function hasPlayingPlayer(Player $player) : bool{
		return parent::hasPlayingPlayer($player); // TODO: Change the autogenerated stub
	}

	public function addProgress(Player $player, Item $result) : void{
		if($this->playingPlayers[$player->getName()]["count"] < $this->output->getCount()){
			$this->playingPlayers[$player->getName()]["count"] += $result->getCount();
		}
	}

	public function getProgress(Player $player) : float{
		if($this->playingPlayers[$player->getName()]["count"] === 0){
			return 0.0;
		}
		return max(0, $this->playingPlayers[$player->getName()]["count"] / $this->output->getCount());
	}

	public function canComplete(Player $player) : bool{
		return $this->playingPlayers[$player->getName()]["count"] >= $this->output->getCount();
	}

	public function getOutput() : Item{
		return $this->output;
	}

	public function setOutput(Item $output) : void{
		$this->output = $output;
	}

	public function jsonSerialize() : array{
		return array_merge(parent::jsonSerialize(), ["output" => $this->output->jsonSerialize()]);
	}

	public static function jsonDeserialize(array $data) : CraftQuest{
		return new CraftQuest($data["name"], $data["reward"], $data["players"], $data["playingPlayers"], $data["type"], Item::jsonDeserialize($data["output"]));
	}

	public function getDescription() : string{
		return "아이템 " . $this->output->getName() . " " . $this->output->getCount() . "개를 조합하세요.\n\n보상: " . implode(", ", array_map(function(Item $item) : string{
				return $item->getName() . " " . $item->getCount() . "개";
			}, $this->rewards));
	}

	public function getFailMessage(Player $player) : string{
		return "아직 아이템 " . $this->output->getName() . " " . $this->output->getCount() . "개를 조합하지 않았습니다.";
	}
}