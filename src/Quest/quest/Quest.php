<?php
declare(strict_types=1);

namespace Quest\quest;

use OnixUtils\OnixUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use Quest\event\QuestClearEvent;

abstract class Quest{

	public const TYPE_COLLECT_ITEM = "collect";

	public const TYPE_FIND_AREA = "area";

	public const TYPE_CONNECTTIME = "connecttime";

	public const TYPE_PVP = "pvp";

	public const TYPE_CRAFT = "craft";

	protected string $name;

	protected array $players = [];

	protected array $playingPlayers = [];

	protected string $type;

	/** @var Item[] */
	protected array $rewards = [];

	public function __construct(string $name, array $rewards, array $players = [], array $playingPlayers = [], string $type = self::TYPE_COLLECT_ITEM){
		$this->name = $name;
		foreach($rewards as $reward)
			$this->rewards[] = Item::jsonDeserialize($reward);
		$this->players = $players;
		$this->playingPlayers = $playingPlayers;
		$this->type = $type;
	}

	public function getName() : string{
		return $this->name;
	}

	public function getPlayingPlayers() : array{
		return $this->playingPlayers;
	}

	public function getPlayers() : array{
		return $this->players;
	}

	public function getRewards() : array{
		return $this->rewards;
	}

	public function addReward(Item $reward) : void{
		$this->rewards[] = $reward;
	}

	public function addPlayer(Player $player) : void{
		$this->players[$player->getName()] = time();
	}

	public function removePlayer(string $player) : void{
		unset($this->players[$player]);
	}

	public function addPlayingPlayer(Player $player) : void{
		$this->playingPlayers[$player->getName()] = time();
	}

	public function removePlayingPlayer(Player $player) : void{
		unset($this->playingPlayers[$player->getName()]);
	}

	public function canComplete(Player $player) : bool{
		return false;
	}

	public function complete(Player $player) : void{
		$time = time() - $this->playingPlayers[$player->getName()];

		OnixUtils::message($player, date("d일 H시간 i분 s초", $time) . "만에 클리어하셨습니다.");
		($ev = new QuestClearEvent($player, $this, $this->rewards))->call();
		foreach($ev->getRewards() as $reward)
			$player->getInventory()->addItem($reward);
		$this->removePlayingPlayer($player);
		$this->addPlayer($player);
	}

	public function check() : void{
		foreach($this->players as $name => $time){
			if(time() >= $time + (60 * 60 * 24)){
				unset($this->players[$name]);
			}
		}
	}

	public function jsonSerialize() : array{
		return [
			"name" => $this->name,
			"reward" => array_map(function(Item $item) : array{
				return $item->jsonSerialize();
			}, $this->rewards),
			"players" => $this->players,
			"playingPlayers" => $this->playingPlayers,
			"type" => $this->type
		];
	}

	public function getClearTime(Player $player) : int{
		return $this->players[$player->getName()];
	}

	/**
	 * @param array $data
	 *
	 * @return Quest
	 */
	abstract public static function jsonDeserialize(array $data);

	abstract public function getFailMessage(Player $player) : string;

	abstract public function getDescription() : string;

	public function hasPlayingPlayer(Player $player) : bool{
		return isset($this->playingPlayers[$player->getName()]);
	}

	public function hasPlayer(Player $player) : bool{
		return isset($this->players[$player->getName()]);
	}

	public function getProgress(Player $player) : float{
		return 0.0;
	}
}