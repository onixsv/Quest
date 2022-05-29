<?php
declare(strict_types=1);

namespace Quest;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\StringTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use Quest\quest\AreaEnterQuest;
use Quest\quest\ConnectTimeQuest;
use Quest\quest\CraftQuest;
use Quest\quest\ItemCollectQuest;
use Quest\quest\PvPQuest;
use Quest\quest\Quest;

class QuestPlugin extends PluginBase implements Listener{
	use SingletonTrait;

	/** @var Config */
	protected Config $config;

	protected array $db = [];

	/** @var Quest[] */
	protected array $quests = [];

	public function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML, [
			"quest" => [],
			"date" => (int) date("d")
		]);
		$this->db = $this->config->getAll();

		$c = 0;

		foreach($this->db["quest"] as $name => $questData){
			switch($questData["type"]){
				case Quest::TYPE_COLLECT_ITEM:
					$quest = ItemCollectQuest::jsonDeserialize($questData);
					break;
				case Quest::TYPE_FIND_AREA:
					$quest = AreaEnterQuest::jsonDeserialize($questData);
					break;
				case Quest::TYPE_CONNECTTIME:
					$quest = ConnectTimeQuest::jsonDeserialize($questData);
					break;
				case Quest::TYPE_PVP:
					$quest = PvPQuest::jsonDeserialize($questData);
					break;
				case Quest::TYPE_CRAFT:
					$quest = CraftQuest::jsonDeserialize($questData);
					break;
			}

			if(isset($quest)){
				$this->quests[$quest->getName()] = $quest;
				$c++;
			}
		}

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach(array_values($this->quests) as $quest)
				$quest->check();
		}), 20);

		$this->getLogger()->info("{$c}개의 퀘스트를 불러왔습니다.");

		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	protected function onDisable() : void{
		$arr = [];
		foreach(array_values($this->quests) as $quest){
			$arr[$quest->getName()] = $quest->jsonSerialize();
		}

		$this->config->setNested("quest", $arr);
		$this->config->save();
	}

	public function getQuest(string $name) : ?Quest{
		return $this->quests[$name] ?? null;
	}

	public function addQuest(Quest $quest) : void{
		$this->quests[$quest->getName()] = $quest;
	}

	public function removeQuest(Quest $quest) : void{
		unset($this->quests[$quest->getName()]);
	}

	public function openInventory(Player $player) : void{
		/*
		$inv = new QuestInventory();
		$inv->setMode("main");
		$c = 0;
		foreach(array_values($this->quests) as $quest){
			if($quest->hasPlayer($player)){
				$item = ItemFactory::get(ItemIds::ENCHANTED_BOOK);
				$left = ($quest->getClearTime($player) + (60 * 60 * 24)) - time();
				$str = "이미 이 퀘스트를 §a클리어§f 하셨습니다.\n\n퀘스트 해제까지 " . OnixUtils::convertTimeToString($left) . " 남았습니다.";
			}elseif($quest->hasPlayingPlayer($player)){
				$item = ItemFactory::get(ItemIds::BOOK);
				$str = "퀘스트를 §d진행§f중입니다.\n\n퀘스트 진행도: §d" . (round($quest->getProgress($player) * 100, 2)) . "§f%\n\n퀘스트: " . $quest->getDescription();
			}else{
				$item = ItemFactory::get(ItemIds::BOOK);
				$str = "퀘스트를 §a시작§f할 수 있습니다.\n\n퀘스트: " . $quest->getDescription();
			}
			$item->setCustomName("§d" . $quest->getName() . "§f\n\n" . $str);
			$inv->setItem($c, $item);
			$c++;
		}

		$player->addWindow($inv);
		*/

		$inv = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$inv->setName("퀘스트 인벤토리");
		$inv->setListener(function(InvMenuTransaction $transaction) use ($inv) : InvMenuTransactionResult{
			$slot = $transaction->getAction()->getSlot();
			if(($quest = $this->getQuestForIndex($slot)) instanceof Quest){
				$this->showQuestInfo($transaction->getPlayer(), $quest, $inv);
			}
			return $transaction->discard()->then(function(Player $player) : void{
				$player->getNetworkSession()->getInvManager()->syncSlot($player->getCursorInventory(), 0);
			});
		});
		$c = 0;
		foreach(array_values($this->quests) as $quest){
			if($quest->hasPlayer($player)){
				$item = ItemFactory::getInstance()->get(ItemIds::ENCHANTED_BOOK);
				$left = ($quest->getClearTime($player) + (60 * 60 * 24)) - time();
				$str = "이미 이 퀘스트를 §a클리어§f 하셨습니다.\n\n퀘스트 해제까지 " . OnixUtils::convertTimeToString($left) . " 남았습니다.";
			}elseif($quest->hasPlayingPlayer($player)){
				$item = ItemFactory::getInstance()->get(ItemIds::BOOK);
				$str = "퀘스트를 §d진행§f중입니다.\n\n퀘스트 진행도: §d" . (round($quest->getProgress($player) * 100, 2)) . "§f%\n\n퀘스트: " . $quest->getDescription();
			}else{
				$item = ItemFactory::getInstance()->get(ItemIds::BOOK);
				$str = "퀘스트를 §a시작§f할 수 있습니다.\n\n퀘스트: " . $quest->getDescription();
			}
			$item->setCustomName("§d" . $quest->getName() . "§f\n\n" . $str);
			$inv->getInventory()->setItem($c, $item);
			$c++;
		}
		$inv->send($player);
	}

	public function showQuestInfo(Player $player, Quest $quest, InvMenu $inv) : void{
		//31, 38, 42 [31: 퀘스트 아이템, 38, 41: 퀘스트에 따라 다름
		$inv->getInventory()->clearAll();

		for($i = 0; $i < $inv->getInventory()->getSize(); $i++){
			$inv->getInventory()->setItem($i, ItemFactory::getInstance()->get(ItemIds::BED_BLOCK)->setCustomName(""));
		}

		if($quest->hasPlayer($player)){
			$questItem = ItemFactory::getInstance()->get(ItemIds::WRITTEN_BOOK);
			$left = ($quest->getClearTime($player) + (60 * 60 * 24)) - time();
			$questItem->setCustomName("§l" . $quest->getName() . "\n\n이미 이 퀘스트를 §a클리어§f 하셨습니다.\n\n퀘스트 해제까지 " . OnixUtils::convertTimeToString($left) . " 남았습니다.");
			$denyItem = ItemFactory::getInstance()->get(ItemIds::STAINED_GLASS, 14);
			$denyItem->setCustomName("§l나가기");
			$denyItem->getNamedTag()->setTag("quit", new StringTag(""));
			$confirmItem = ItemFactory::getInstance()->get(ItemIds::STAINED_GLASS, 5);
			$confirmItem->setCustomName("§l나가기");
			$confirmItem->getNamedTag()->setTag("quit", new StringTag(""));
		}elseif($quest->hasPlayingPlayer($player)){
			$questItem = ItemFactory::getInstance()->get(ItemIds::WRITTEN_BOOK);
			$questItem->setCustomName("§l" . $quest->getName() . "\n\n퀘스트 진행도: §d" . (round($quest->getProgress($player) * 100, 2)) . "§f%\n\n퀘스트: " . $quest->getDescription());
			$denyItem = ItemFactory::getInstance()->get(ItemIds::STAINED_GLASS, 14);
			$denyItem->setCustomName("§l퀘스트 포기하기");
			$denyItem->getNamedTag()->setTag("giveup", new StringTag(""));
			$confirmItem = ItemFactory::getInstance()->get(ItemIds::STAINED_GLASS, 5);
			$confirmItem->setCustomName("§l퀘스트 완료하기");
			$confirmItem->getNamedTag()->setTag("done", new StringTag(""));
		}else{
			$questItem = ItemFactory::getInstance()->get(ItemIds::WRITTEN_BOOK);
			$questItem->setCustomName("§l" . $quest->getName() . "\n\n퀘스트를 §a시작§f할 수 있습니다.\n\n퀘스트: " . $quest->getDescription());
			$denyItem = ItemFactory::getInstance()->get(ItemIds::STAINED_GLASS, 14);
			$denyItem->setCustomName("§l나가기");
			$denyItem->getNamedTag()->setTag("quit", new StringTag(""));
			$confirmItem = ItemFactory::getInstance()->get(ItemIds::STAINED_GLASS, 5);
			$confirmItem->setCustomName("§l퀘스트 진행하기");
			$confirmItem->getNamedTag()->setTag("confirm", new StringTag(""));
		}

		$inv->getInventory()->setItem(31, $questItem);
		$inv->getInventory()->setItem(38, $denyItem);
		$inv->getInventory()->setItem(42, $confirmItem);

		$inv->setListener(function(InvMenuTransaction $action) use ($inv, $quest) : InvMenuTransactionResult{
			$item = $action->getItemClicked();
			if($item->getNamedTag()->getTag("quit") !== null){
				$inv->getInventory()->clearAll();
				$inv->onClose($action->getPlayer());
			}elseif($item->getNamedTag()->getTag("giveup") !== null){
				if($quest->hasPlayingPlayer($action->getPlayer())){
					$quest->removePlayingPlayer($action->getPlayer());
					$inv->getInventory()->clearAll();
					$inv->onClose($action->getPlayer());
					OnixUtils::message($action->getPlayer(), "퀘스트를 포기하였습니다.");
				}
			}elseif($item->getNamedTag()->getTag("done") !== null){
				if($quest->hasPlayingPlayer($action->getPlayer())){
					if($quest->canComplete($action->getPlayer())){
						$quest->complete($action->getPlayer());
						$inv->getInventory()->clearAll();
						$inv->onClose($action->getPlayer());
						OnixUtils::message($action->getPlayer(), "퀘스트를 클리어하였습니다.");
					}else{
						OnixUtils::message($action->getPlayer(), "퀘스트를 클리어할 수 없습니다.");
						OnixUtils::message($action->getPlayer(), $quest->getFailMessage($action->getPlayer()));
						$inv->getInventory()->clearAll();
						$inv->onClose($action->getPlayer());
					}
				}else{
					OnixUtils::message($action->getPlayer(), "퀘스트를 진행하고 있지 않습니다.");
					$inv->getInventory()->clearAll();
					$inv->onClose($action->getPlayer());
				}
			}elseif($item->getNamedTag()->getTag("confirm") !== null){
				if(!$quest->hasPlayingPlayer($action->getPlayer()) && !$quest->hasPlayer($action->getPlayer())){
					$quest->addPlayingPlayer($action->getPlayer());
					$inv->getInventory()->clearAll();
					$inv->onClose($action->getPlayer());
					OnixUtils::message($action->getPlayer(), "퀘스트를 받았습니다.");
				}else{
					OnixUtils::message($action->getPlayer(), "이미 이 퀘스트를 클리어 하였거나, 플레이 중입니다.");
					$inv->getInventory()->clearAll();
					$inv->onClose($action->getPlayer());
				}
			}
			return $action->discard()->then(function(Player $player) : void{
				$player->getNetworkSession()->getInvManager()->syncSlot($player->getCursorInventory(), 0);
			});
		});
	}

	public function getQuestForIndex(int $index) : ?Quest{
		$c = 0;
		foreach(array_values($this->quests) as $quest){
			if($c === $index)
				return $quest;
			$c++;
		}
		return null;
	}

	public function handleInventoryTransaction(InventoryTransactionEvent $event){
		/*
		$player = $event->getTransaction()->getSource();

		foreach($event->getTransaction()->getActions() as $action){
			if($action instanceof SlotChangeAction){
				$inv = $action->getInventory();

				if($inv instanceof QuestInventory){
					$event->setCancelled();
					$slot = $action->getSlot();
					$q = $inv->getQuest();
					switch($inv->getMode()){
						case "main":
							if(($quest = $this->getQuestForIndex($slot)) instanceof Quest){
								$this->showQuestInfo($player, $quest, $inv);
							}
							break;
						case "info":
							$item = $action->getSourceItem();
							if($item->getNamedTagEntry("quit") !== null){
								$inv->clearAll();
								$inv->onClose($player);
								break;
							}
							if($item->getNamedTagEntry("giveup") !== null){
								if($q->hasPlayingPlayer($player)){
									$q->removePlayingPlayer($player);
									$inv->clearAll();
									$inv->onClose($player);
									OnixUtils::message($player, "퀘스트를 포기하였습니다.");
									break;
								}
							}
							if($item->getNamedTagEntry("done") !== null){
								if($q->hasPlayingPlayer($player)){
									if($q->canComplete($player)){
										$q->complete($player);
										$inv->clearAll();
										$inv->onClose($player);
										OnixUtils::message($player, "퀘스트를 클리어하였습니다.");
									}else{
										OnixUtils::message($player, "퀘스트를 클리어할 수 없습니다.");
										OnixUtils::message($player, $q->getFailMessage($player));
										$inv->clearAll();
										$inv->onClose($player);
									}
								}else{
									OnixUtils::message($player, "퀘스트를 진행하고 있지 않습니다.");
									$inv->clearAll();
									$inv->onClose($player);
								}
							}

							if($item->getNamedTagEntry("confirm") !== null){
								if(!$q->hasPlayingPlayer($player) && !$q->hasPlayer($player)){
									$q->addPlayingPlayer($player);
									$inv->clearAll();
									$inv->onClose($player);
									OnixUtils::message($player, "퀘스트를 받았습니다.");
								}else{
									OnixUtils::message($player, "이미 이 퀘스트를 클리어 하였거나, 플레이 중입니다.");
									$inv->clearAll();
									$inv->onClose($player);
								}
							}
							break;
					}
				}
			}
		}
		*/
	}

	public function onCraft(CraftItemEvent $event) : void{
		$player = $event->getPlayer();
		$result = $event->getOutputs();
		$result = array_pop($result);
		if($result instanceof Item){
			foreach(array_values($this->quests) as $quest){
				if($quest instanceof CraftQuest){
					if($quest->hasPlayingPlayer($player)){
						$quest->addProgress($player, ItemFactory::getInstance()->get($result->getId(), $result->getMeta() ?? 0, $result->getCount() ?? 1, $result->getNamedTag()));
					}
				}
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			if($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
				switch($args[0] ?? "x"){
					case "생성":
						if(trim($args[1] ?? "") !== ""){
							if(!$this->getQuest($args[1]) instanceof Quest){
								if(in_array($args[2] ?? "", [
									"접속시간",
									"아이템모으기",
									"킬",
									"조합"
								])){
									$item = $sender->getInventory()->getItemInHand();
									if(!$item->isNull()){
										switch($args[2]){
											case "접속시간":
												$quest = new ConnectTimeQuest($args[1], [$item->jsonSerialize()], [], [], Quest::TYPE_CONNECTTIME, 60 * 60);
												break;
											case "아이템모으기":
												$quest = new ItemCollectQuest($args[1], [$item->jsonSerialize()], [], [], Quest::TYPE_COLLECT_ITEM, ItemFactory::getInstance()->get(ItemIds::DIRT, 0, 10));
												break;
											case "킬":
												$quest = new PvPQuest($args[1], [$item->jsonSerialize()], [], [], Quest::TYPE_PVP, 0);
												break;
											case "조합":
												$quest = new CraftQuest($args[1], [$item->jsonSerialize()], [], [], Quest::TYPE_CRAFT, ItemFactory::getInstance()->get(ItemIds::DIRT, 0, 1));
												break;
										}

										if(isset($quest)){
											$this->addQuest($quest);
											OnixUtils::message($sender, "퀘스트가 추가되었습니다. 퀘스트 타입에 따라 퀘스트의 요구 사항을 명령어로 설정해주세요.");
										}else{
											OnixUtils::message($sender, "잘못된 퀘스트 형식입니다.");
										}
									}else{
										OnixUtils::message($sender, "보상 아이템은 공기가 아니어야 합니다.");
									}
								}else{
									OnixUtils::message($sender, "잘못된 퀘스트 형식입니다. 가능한 형식: 아이템모으기, 접속시간, 킬, 조합");
								}
							}else{
								OnixUtils::message($sender, "해당 이름의 퀘스트가 이미 존재합니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 생성 [이름] [형식] - 퀘스트를 생성합니다.");
							OnixUtils::message($sender, "퀘스트 형식: 아이템모으기, 접속시간, 킬, 조합");
						}
						break;
					case "제거":
						if(trim($args[1] ?? "") !== ""){
							if(($q = $this->getQuest($args[1])) instanceof Quest){
								$this->removeQuest($q);
								OnixUtils::message($sender, "퀘스트를 제거했습니다.");
							}else{
								OnixUtils::message($sender, "해당 이름의 퀘스트가 존재하지 않습니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 제거 [이름] - 퀘스트를 제거합니다.");
						}
						break;
					case "목록":
						if(count($this->quests) === 0){
							OnixUtils::message($sender, "퀘스트가 존재하지 않습니다.");
							break;
						}

						OnixUtils::message($sender, "퀘스트 목록: " . implode(", ", array_map(function(Quest $quest) : string{
								return $quest->getName();
							}, array_values($this->quests))));
						break;
					case "보상추가":
						if(trim($args[1] ?? "") !== ""){
							if(trim($args[2] ?? "") !== "" && is_numeric($args[2]) && (int) $args[2] > 0){
								if(($q = $this->getQuest($args[1])) instanceof Quest){
									$item = $sender->getInventory()->getItemInHand();
									if(!$item->isNull()){
										$item->setCount((int) $args[2]);
										$q->addReward($item);
										OnixUtils::message($sender, "보상을 추가했습니다.");
									}else{
										OnixUtils::message($sender, "아이템은 공기가 아니어야 합니다.");
									}
								}else{
									OnixUtils::message($sender, "해당 이름의 퀘스트가 존재하지 않습니다.");
								}
							}else{
								OnixUtils::message($sender, "갯수는 숫자여야 합니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 보상추가 [이름] [갯수] - 퀘스트에 내 손에 든 아이템을 보상으로 추가합니다.");
						}
						break;
					case "요청아이템":
						if(trim($args[1] ?? "") !== ""){
							if(trim($args[2] ?? "") !== "" && is_numeric($args[2]) && (int) $args[2] > 0){
								if(($q = $this->getQuest($args[1])) instanceof Quest){
									if($q instanceof ItemCollectQuest){
										$item = $sender->getInventory()->getItemInHand();
										if(!$item->isNull()){
											$item->setCount((int) $args[2]);
											$q->setRequestItem($item);
											OnixUtils::message($sender, "요청 아이템을 설정하였습니다.");
										}else{
											OnixUtils::message($sender, "아이템은 공기가 아니어야 합니다.");
										}
									}else{
										OnixUtils::message($sender, "해당 퀘스트는 아이템모으기 퀘스트가 아닙니다.");
									}
								}else{
									OnixUtils::message($sender, "해당 이름의 퀘스트가 존재하지 않습니다.");
								}
							}else{
								OnixUtils::message($sender, "갯수는 숫자여야 합니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 요청아이템 [이름] [갯수] - 퀘스트에 내 손에 든 아이템을 요청 아이템으로 설정합니다.");
						}
						break;
					case "접속시간":
						if(trim($args[1] ?? "") !== ""){
							if(($q = $this->getQuest($args[1])) instanceof Quest){
								if($q instanceof ConnectTimeQuest){
									if(trim($args[2] ?? "") !== "" && is_numeric($args[2]) && (int) $args[2] > 0){
										$q->setTime((int) $args[2]);
										OnixUtils::message($sender, "접속시간을 설정했습니다.");
									}else{
										OnixUtils::message($sender, "접속시간을 입력해주세요.");
									}
								}else{
									OnixUtils::message($sender, "접속시간은 숫자여야 합니다.");
								}
							}else{
								OnixUtils::message($sender, "해당 이름의 퀘스트가 존재하지 않습니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 접속시간 [이름] [시간(분)] - 퀘스트의 접속시간을 설정합니다.");
						}
						break;
					case "킬":
						if(trim($args[1] ?? "") !== ""){
							if(is_numeric($args[2] ?? "") && (int) $args[2] > 0){
								if(($q = $this->getQuest($args[1])) instanceof Quest){
									if($q instanceof PvPQuest){
										$q->setkill((int) $args[2]);
										OnixUtils::message($sender, "킬 수를 설정했습니다.");
									}else{
										OnixUtils::message($sender, "해당 퀘스트는 킬 퀘스트가 아닙니다.");
									}
								}else{
									OnixUtils::message($sender, "해당 이름의 퀘스트가 존재하지 않습니다.");
								}
							}else{
								OnixUtils::message($sender, "킬 수는 숫자여야 합니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 킬 [킬] - 퀘스트의 킬을 설정합니다.");
						}
						break;
					case "조합":
						if(trim($args[1] ?? "") !== ""){
							if(is_numeric($args[2] ?? "") && (int) $args[2] > 0){
								if(($q = $this->getQuest($args[1])) instanceof Quest){
									if($q instanceof CraftQuest){
										$item = $sender->getInventory()->getItemInHand();
										if(!$item->isNull()){
											$q->setOutput($item->setCount(intval($args[2])));
											OnixUtils::message($sender, "퀘스트의 조합 아이템을 설정했습니다.");
										}else{
											OnixUtils::message($sender, "아이템은 공기가 아니어야 합니다.");
										}
									}else{
										OnixUtils::message($sender, "해당 퀘스트는 조합 퀘스트가 아닙니다.");
									}
								}else{
									OnixUtils::message($sender, "해당 이름의 퀘스트가 존재하지 않습니다.");
								}
							}else{
								OnixUtils::message($sender, "아이템 수는 숫자여야 합니다.");
							}
						}else{
							OnixUtils::message($sender, "/퀘스트 조합 [갯수] - 조합 퀘스트의 조합 아이템을 설정합니다.");
						}
						break;
					case "열기":
						$this->openInventory($sender);
						break;
					default:
						OnixUtils::message($sender, "/퀘스트 생성 [이름] [형식] - 퀘스트를 생성합니다.");
						OnixUtils::message($sender, "퀘스트 형식: 아이템모으기, 접속시간, 킬");
						OnixUtils::message($sender, "/퀘스트 제거 [이름] - 퀘스트를 제거합니다.");
						OnixUtils::message($sender, "/퀘스트 목록 - 퀘스트의 목록을 봅니다.");
						OnixUtils::message($sender, "/퀘스트 보상추가 [이름] [갯수] - 퀘스트에 내 손에 든 아이템을 보상으로 추가합니다.");
						OnixUtils::message($sender, "/퀘스트 요청아이템 [이름] [갯수] - 퀘스트에 내 손에 든 아이템을 요청 아이템으로 설정합니다.");
						OnixUtils::message($sender, "/퀘스트 접속시간 [이름] [시간(분)] - 퀘스트의 접속시간을 설정합니다.");
						OnixUtils::message($sender, "/퀘스트 킬 [킬] - 퀘스트의 킬을 설정합니다.");
						OnixUtils::message($sender, "/퀘스트 조합 [갯수] - 조합 퀘스트의 조합 아이템을 설정합니다.");
						OnixUtils::message($sender, "/퀘스트 열기 - 퀘스트 인벤토리를 엽니다.");
				}
			}else{
				$this->openInventory($sender);
			}
		}
		return true;
	}
}