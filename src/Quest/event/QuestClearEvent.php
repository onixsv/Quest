<?php
declare(strict_types=1);

namespace Quest\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use Quest\quest\Quest;

class QuestClearEvent extends PlayerEvent{
	/** @var Quest */
	protected Quest $quest;
	/** @var Item[] */
	protected array $rewards = [];

	public function __construct(Player $player, Quest $quest, array $rewards){
		$this->player = $player;
		$this->quest = $quest;
	}

	public function getQuest() : Quest{
		return $this->quest;
	}

	/**
	 * @return Item[]
	 */
	public function getRewards() : array{
		return $this->rewards;
	}

	public function setRewards(array $rewards) : void{
		$this->rewards = $rewards;
	}
}