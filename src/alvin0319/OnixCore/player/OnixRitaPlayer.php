<?php

declare(strict_types=1);

namespace alvin0319\OnixCore\player;

use alvin0319\AFK\AFK;
use alvin0319\Area\area\Area;
use alvin0319\Area\AreaLoader;
use alvin0319\LevelAPI\LevelAPI;
use ConnectTime\ConnectTime;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use MySetting\MySetting;
use MySetting\Setting;
use onebone\economyapi\EconomyAPI;
use OnixUtils\OnixUtils;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function count;
use function strtolower;

class OnixRitaPlayer extends Player{
	/** @var Setting */
	protected Setting $settings;
	/** @var CompoundTag */
	protected CompoundTag $chestInvTag;
	/** @var int */
	protected int $flyTime = 0;
	/** @var Area|null */
	protected ?Area $area = null;

	public function doFirstSpawn() : void{
		//$method = new ReflectionMethod(InventoryManager::class, "add");
		//$method->setAccessible(true);
		//$method->invoke($this->getNetworkSession()->getInvManager(), ContainerIds::OFFHAND, $this->offhandInventory);
		//$this->getNetworkSession()->getInvManager()->syncContents($this->offhandInventory);
		parent::doFirstSpawn();
		$this->settings = MySetting::getInstance()->getSetting($this);
		//$this->offhandInventory->sendOffhand($this);
		//foreach($this->getViewers() as $viewer){
		//	$this->offhandInventory->sendOffhand($viewer);
		//}
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		//$this->offhandInventory = new OffhandInventory($this);
		//if($nbt->hasTag("offhand", CompoundTag::class)){
		//	$this->offhandInventory->setItemInOffhand(Item::nbtDeserialize($nbt->getCompoundTag("offhand")));
		//}else{
		//	$this->offhandInventory->setItemInOffhand(ItemFactory::air());
		//}

		if($nbt->getTag("VirtualChest") instanceof CompoundTag){
			$this->chestInvTag = $nbt->getCompoundTag("VirtualChest");
		}else{
			$virtualCompound = CompoundTag::create()->setTag("Inventory", new ListTag([], NBT::TAG_Compound))->setInt("Slot", 1);
			$this->chestInvTag = $virtualCompound;
		}

		if($nbt->getTag("flyTime") instanceof IntTag){
			$this->flyTime = $nbt->getInt("flyTime", 0);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setTag("VirtualChest", $this->chestInvTag);

		$nbt->setInt("flyTime", $this->flyTime);

		return $nbt;
	}

	public function getSettings() : Setting{
		return $this->settings;
	}

	public function getArea() : ?Area{
		return $this->area;
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		if($currentTick % 20 === 0){
			$this->sendScoreBoard();
			$this->checkArea();
			$this->checkFly();
			if(!AFK::getInstance()->isAFK($this)){
				ConnectTime::getInstance()->addTime($this);
			}
		}
		return $hasUpdate;
	}

	protected function sendScoreBoard() : void{
		if(!$this->getNetworkSession()->isConnected()){
			return;
		}

		$this->removeScoreBoard();

		$pk = new SetDisplayObjectivePacket();
		$pk->objectiveName = $this->getName();
		$pk->displayName = "§d< §f내 정보 §d>";
		$pk->sortOrder = 0;
		$pk->criteriaName = "dummy";
		$pk->displaySlot = "sidebar";
		$this->getNetworkSession()->sendDataPacket($pk);

		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_CHANGE;
		$texts = [
			"§f내 레벨: §d" . LevelAPI::getInstance()->getLevel($this),
			"§f내 돈: §d" . EconomyAPI::getInstance()->koreanWonFormat(EconomyAPI::getInstance()->myMoney($this)),
			"§f동접: §d" . count($this->getServer()->getOnlinePlayers()) . "§f명",
		];
		foreach($texts as $c => $str){
			$entry = new ScorePacketEntry();
			$entry->objectiveName = $this->getName();
			$entry->score = $c;
			$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
			$entry->scoreboardId = $c;
			$entry->customName = $str;
			$pk->entries[] = $entry;
		}
		$this->getNetworkSession()->sendDataPacket($pk);
	}

	protected function removeScoreBoard() : void{
		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $this->getName();
		$this->getNetworkSession()->sendDataPacket($pk);
	}

	public function getLowerCaseName() : string{
		return strtolower($this->getName());
	}

	public function openVirtualChest() : void{
		$menu = InvMenu::create(InvMenu::TYPE_CHEST);
		$menu->setName($this->getName() . "님의 가상창고");

		$virtualTag = $this->chestInvTag;
		$inventoryTag = $virtualTag->getListTag("Inventory");
		$slotTag = $virtualTag->getInt("Slot");

		/** @var CompoundTag $value */
		foreach($inventoryTag->getValue() as $value){
			$slot = $value->getByte("Slot");
			$item = Item::nbtDeserialize($value);
			$menu->getInventory()->setItem($slot, $item);
		}
		for($i = $slotTag; $i < 27; $i++){
			$unusedSlot = ItemFactory::getInstance()->get(ItemIds::IRON_BARS, 0, 1);
			$unusedSlot->setCustomName("§f사용할 수 없는 §d{$i}§f 슬롯");
			$unusedSlot->getNamedTag()->setTag("unused", new StringTag("unused"));
			$unusedSlot->setLore(["이 슬롯을 구매하려면 15만 원이 필요합니다."]);
			$menu->getInventory()->setItem($i, $unusedSlot);
		}
		$menu->setListener(function(InvMenuTransaction $action) : InvMenuTransactionResult{
			if($action->getOut()->getNAmedTag()->getTag("unused") !== null){
				return $action->discard();
			}
			return $action->continue();
		});

		$menu->setInventoryCloseListener(function(Player $player) use ($menu) : void{
			$inventoryTag = new ListTag();
			foreach($menu->getInventory()->getContents(false) as $slot => $item){
				if(!$item->getNamedTag()->getTag("unused") !== null){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}
			$this->chestInvTag->setTag("Inventory", $inventoryTag);
		});
		$menu->send($this);
	}

	public function increaseVirtualSlot(int $slot) : void{
		$now = $this->chestInvTag->getInt("Slot", 1);
		$this->chestInvTag->setInt("Slot", $now + $slot);
	}

	public function getVirtualSlot() : int{
		return $this->chestInvTag->getInt("Slot");
	}

	private function checkArea() : void{
		static $am = null;
		if($am === null){
			$am = AreaLoader::getInstance()->getAreaManager();
		}
		$currentArea = $am->getArea($this->getPosition(), $this->getWorld());
		if($currentArea === null){
			$this->area = null;
			return;
		}
		if($this->area === null){
			if($currentArea === null){
				return;
			}
			if(!$this->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
				if(!$currentArea->isResident($this->getName()) && !$currentArea->getAreaProperties()->getAllowEnter()){
					$this->sendPopup("§c땅 접근이 거부되었습니다.");
					$this->teleport(new Position($currentArea->getMaxX() + 2, $currentArea->getCenter()->getY(), $currentArea->getMaxZ() + 2, $this->getWorld()));
					return;
				}
			}
			$this->area = $currentArea;
			return;
		}
		if($currentArea->equals($this->area)){
			return;
		}
		if(!$this->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
			if(!$currentArea->isResident($this->getName()) && !$currentArea->getAreaProperties()->getAllowEnter()){
				$this->sendPopup("§c땅 접근이 거부되었습니다.");
				$this->teleport(new Position($currentArea->getMaxX() + 2, $currentArea->getCenter()->getY(), $currentArea->getMaxZ() + 2, $this->getWorld()));
				return;
			}
		}
		$this->area = $currentArea;
	}

	private function checkFly() : void{
		if(!$this->getGamemode()->equals(GameMode::SURVIVAL())){
			return;
		}
		if($this->flyTime < 1){
			if($this->isFlying() || $this->getAllowFlight()){
				$this->setFlying(false);
				$this->setAllowFlight(false);
			}
			return;
		}
		$area = $this->area;
		if($area === null || !$area->isResident($this->getName())){
			if($this->isFlying() || $this->getAllowFlight()){
				$this->setFlying(false);
				$this->setAllowFlight(false);
			}
			return;
		}
		if(!$this->getAllowFlight()){
			$this->setAllowFlight(true);
		}
		if($this->area !== null && $this->area->isResident($this->getName())){
			if($this->isFlying()){
				$this->sendPopup("§f플라이 해제까지 남은 시간: §d" . OnixUtils::convertTimeToString($this->flyTime));
				--$this->flyTime;
			}
		}
	}

	public function addFlyTime(int $duration) : void{
		$this->flyTime += $duration;
	}
}