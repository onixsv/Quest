<?php
declare(strict_types=1);

namespace Quest\quest;

use pocketmine\item\Item;
use pocketmine\player\Player;

class ItemCollectQuest extends Quest{

	/** @var Item */
	protected ?Item $request;

	public function __construct(string $name, array $rewards, array $players = [], array $playingPlayers = [], string $type = self::TYPE_COLLECT_ITEM, Item $request = null){
		parent::__construct($name, $rewards, $players, $playingPlayers, $type);
		$this->request = $request;
	}

	public function getRequestItem() : Item{
		return $this->request;
	}

	public function setRequestItem(Item $item) : void{
		$this->request = $item;
	}

	public function getProgress(Player $player) : float{
		$cnt = 0;

		foreach($player->getInventory()->getContents(false) as $item){
			if($this->request->equals($item, true, true)){
				$cnt += $item->getCount();
			}
		}

		if($cnt === 0)
			return 0.0;

		return (float) max(0, $cnt / $this->request->getCount());
	}

	public function getFailMessage(Player $player) : string{
		$my = 0;

		foreach($player->getInventory()->getContents(false) as $item){
			if($this->request->equals($item, true, true)){
				$my += $item->getCount();
			}
		}

		return $this->request->getCount() . "개중 " . $my . "개를 모았습니다.";
	}

	public function canComplete(Player $player) : bool{
		return $player->getInventory()->contains($this->request);
	}

	public function getDescription() : string{
		return "아이템 " . ($this->request->getName()) . " " . ($this->request->getCount()) . "개를 모으시오.\n\n보상: " . implode(", ", array_map(function(Item $item) : string{
				return $item->getName() . " " . $item->getCount() . "개";
			}, $this->rewards));
	}

	public static function jsonDeserialize(array $data) : ItemCollectQuest{
		return new ItemCollectQuest($data["name"], $data["reward"], $data["players"], $data["playingPlayers"], $data["type"], Item::jsonDeserialize($data["request"]));
	}

	public function jsonSerialize() : array{
		return array_merge(parent::jsonSerialize(), ["request" => $this->request->jsonSerialize()]);
	}
}