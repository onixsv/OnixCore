<?php
declare(strict_types=1);

namespace alvin0319\OnixCore\command;

use alvin0319\OnixCore\player\OnixRitaPlayer;
use onebone\economyapi\EconomyAPI;
use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\inventory\Inventory;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\IntTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use function is_int;
use function is_numeric;
use function trim;

class VirtualChestCommand extends Command{

	public function __construct(){
		parent::__construct("가상창고");
		$this->setDescription("가상창고 명령어입니다.");
	}


	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		/** @var OnixRitaPlayer $sender */
		if(!$sender instanceof Player){
			return false;
		}
		switch($args[0] ?? "x"){
			case "열기":
				$sender->openVirtualChest();
				break;
			case "슬롯확장":
				$slot = $sender->getVirtualSlot();
				if($slot >= 27){
					OnixUtils::message($sender, "이미 최대치만큼 슬롯을 확장했습니다.");
					break;
				}

				$item = ItemFactory::getInstance()->get(ItemIds::PAPER, 0, 1);
				$item->getNamedTag()->setTag("increase", new IntTag(0));

				$slotIndex = $this->first($sender->getInventory());
				if($slotIndex > -1){
					$item = $sender->getInventory()->getItem($slotIndex);
					$slotValue = $item->getNamedTag()->getInt("increase");

					if($slot + $slotValue >= 27){
						$slotValue = ($slot + $slotValue) - 27;
					}
					//$sender->getInventory()->removeItem($item->setCount($item->getCount() - 1));
					$sender->getInventory()->setItem($slotIndex, $item->setCount($item->getCount() - 1));
					$sender->increaseVirtualSlot($slotValue);
					OnixUtils::message($sender, "가상창고의 슬롯을 §d{$slotValue}§f칸만큼 확장했습니다.");
					break;
				}

				if(EconomyAPI::getInstance()->reduceMoney($sender, 150000) !== EconomyAPI::RET_SUCCESS){
					OnixUtils::message($sender, "슬롯을 확장할 돈이 부족합니다.");
					break;
				}
				$sender->increaseVirtualSlot(1);
				OnixUtils::message($sender, "슬롯을 §d1§f칸 확장했습니다.");
				break;
			case "확장권얻기":
				if(!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
					break;
				}

				$slot = 1;
				if(trim($args[1] ?? "") !== ""){
					if(is_numeric($args[1]) && (int) $args[1] > 0){
						$slot = (int) $args[1];
					}
				}
				$item = ItemFactory::getInstance()->get(ItemIds::PAPER, 0, 1)->setCustomName("가상창고 슬롯 {$slot}칸 확장권");
				$item->getNamedTag()->setTag("increase", new IntTag($slot));
				$sender->getInventory()->addItem($item);
				OnixUtils::message($sender, "슬롯 {$slot}칸 확장권을 지급했습니다.");
				break;
			default:
				OnixUtils::message($sender, "/가상창고 열기 - 가상창고를 엽니다.");
				OnixUtils::message($sender, "/가상창고 슬롯확장 - 15만 원을 지불하고가상창고의 슬롯을 확장합니다.");
		}
		return true;
	}

	public function first(Inventory $inventory) : int{
		foreach($inventory->getContents(false) as $slot => $i){
			if($i->getNamedTag()->getTag("increase") !== null && is_int($i->getNamedTag()->getTag("increase")->getValue())){
				return $slot;
			}
		}
		return -1;
	}
}