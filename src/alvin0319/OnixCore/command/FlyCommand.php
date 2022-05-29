<?php

declare(strict_types=1);

namespace alvin0319\OnixCore\command;

use alvin0319\OnixCore\OnixCore;
use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function array_shift;
use function count;
use function is_numeric;

class FlyCommand extends Command{

	public function __construct(){
		parent::__construct("플라이", "플라이 명령어입니다.");
		$this->setPermission("onixcore.command.fly");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return false;
		}
		if(!$sender instanceof Player){
			return false;
		}
		if(count($args) < 1){
			OnixUtils::message($sender, "사용법: /플라이 <시간(초)> - 플라이북을 얻습니다.");
			return false;
		}
		$duration = array_shift($args);
		if(!is_numeric($duration ?? "") || ($duration = (int) $duration) < 0){
			OnixUtils::message($sender, "잘못된 시간을 입력했습니다.");
			return false;
		}
		$sender->getInventory()->addItem(OnixCore::getFlyItem($duration));
		OnixUtils::message($sender, "지급했습니다.");
		return true;
	}
}