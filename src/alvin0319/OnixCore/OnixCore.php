<?php

declare(strict_types=1);

namespace alvin0319\OnixCore;

use alvin0319\Jewelry\Jewelry;
use alvin0319\LevelAPI\LevelAPI;
use alvin0319\OnixCore\command\FlyCommand;
use alvin0319\OnixCore\command\VirtualChestCommand;
use alvin0319\OnixCore\player\OnixRitaPlayer;
use OnixUtils\OnixUtils;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Crops;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\entity\EntityDataHelper;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkPopulateEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\IntTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\BiomeArray;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\World;
use function mt_rand;

class OnixCore extends PluginBase{

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvent(PlayerCreationEvent::class, function(PlayerCreationEvent $event) : void{
			$namedtag = $this->getServer()->getOfflinePlayerData($event->getNetworkSession()->getPlayerInfo()->getUsername());
			$worldManager = $this->getServer()->getWorldManager();

			if($namedtag !== null && ($world = $worldManager->getWorldByName($namedtag->getString("Level", ""))) !== null){
				$vec = EntityDataHelper::parseVec3($namedtag, "Pos", false);
			}else{
				$world = $worldManager->getDefaultWorld();
				$vec = $world->getSafeSpawn();
			}
			self::prepareChunk($world, $vec);
			$event->setPlayerClass(OnixRitaPlayer::class);
		}, EventPriority::HIGHEST, $this);

		$this->getServer()->getPluginManager()->registerEvent(EntityTeleportEvent::class, function(EntityTeleportEvent $event) : void{
			if($event->getEntity() instanceof Player){
				$to = $event->getTo();
				self::prepareChunk($to->getWorld(), $to);
			}
		}, EventPriority::NORMAL, $this, true);

		$this->getServer()->getPluginManager()->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			$player = $event->getPlayer();
			$block = $event->getBlock();

			if($block instanceof Crops){
				if($block->getAge() < 0x07){
					return;
				}

				LevelAPI::getInstance()->addExp($player, mt_rand(1, 3));

				$rand = mt_rand(0, 20);
				if($rand > 4 && $rand < 7){
					Jewelry::getInstance()->addJewelry($player, $jewelry = Jewelry::getInstance()->getRandomJewelryNon(), mt_rand(1, 2));
					OnixUtils::message($player, "§d{$jewelry}§f 보석을 발견했습니다.");
				}
			}
		}, EventPriority::HIGHEST, $this, false);

		$this->getServer()->getPluginManager()->registerEvent(ChunkLoadEvent::class, function(ChunkLoadEvent $event) : void{
			$event->getChunk()->setLightPopulated(null);
		}, EventPriority::NORMAL, $this);
		$this->getServer()->getPluginManager()->registerEvent(ChunkPopulateEvent::class, function(ChunkPopulateEvent $event) : void{
			$event->getChunk()->setLightPopulated(null);
		}, EventPriority::NORMAL, $this);
		$this->getServer()->getPluginManager()->registerEvent(BlockSpreadEvent::class, function(BlockSpreadEvent $event) : void{
			$block = $event->getSource();
			if($block instanceof Liquid){
				if(!$block->isStill()){
					$block->setStill(true);
				}
				$event->cancel();
			}
		}, EventPriority::NORMAL, $this, true);

		$this->getServer()->getCommandMap()->registerAll("onixcore", [
				new VirtualChestCommand(),
				new FlyCommand()
			]
		);

		$opCommand = $this->getServer()->getCommandMap()->getCommand("op");
		if($opCommand !== null){
			$opCommand->setPermission(DefaultPermissions::ROOT_CONSOLE);
		}
		$deopCommand = $this->getServer()->getCommandMap()->getCommand("deop");
		if($deopCommand !== null){
			$deopCommand->setPermission(DefaultPermissions::ROOT_CONSOLE);
		}
		/*
		$monitor = SimplePacketHandler::createMonitor($this);
		$monitor->monitorIncoming(function(InteractPacket $packet, NetworkSession $session) : bool{
			if($packet->action === PlayerActionPacket::ACTION_START_BREAK){
				if(!$session->getPlayer() instanceof Player){
					return false;
				}
				$pos = new Position($packet->x, $packet->y, $packet->z, $session->getPlayer()->getWorld());
				if(!$pos->getWorld()->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorX() >> 4)){
					if($pos->getWorld()->loadChunk($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4) === null){
						$pos->getWorld()->setChunk($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4, new Chunk([]));
					}
				}
			}
			return true;
		});
		*/

		$this->getServer()->getPluginManager()->registerEvent(PlayerItemUseEvent::class, function(PlayerItemUseEvent $event) : void{
			/** @var OnixRitaPlayer $player */
			$player = $event->getPlayer();
			$item = $event->getItem();
			if(!$item->getNamedTag()->getTag("flyDuration") instanceof IntTag){
				return;
			}
			$flyDuration = $item->getNamedTag()->getInt("flyDuration", 0);
			if($flyDuration > 0){
				$player->addFlyTime($flyDuration);
				$event->cancel();
				$player->getInventory()->removeItem($item->setCount(1));
			}
		}, EventPriority::NORMAL, $this, true);

		/*
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->getLogger()->info("Starting garbage collector...");
			$memory = memory_get_usage();
			$this->getServer()->getMemoryManager()->triggerGarbageCollector();
			$chunksCollected = 0;
			$entitiesCollected = 0;
			foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
				$diff = [count($world->getChunks()), count($world->getEntities())];
				$world->doChunkGarbageCollection();
				$world->unloadChunks(true);
				$chunksCollected += $diff[0] - count($world->getChunks());
				$entitiesCollected += $diff[1] - count($world->getEntities());
				$world->clearCache(true);
			}
			$freed = $memory - memory_get_usage();
			$this->getLogger()->info("Garbage collector memory freed: " . number_format($freed) . "B, cleared: {$chunksCollected}(chunk), {$entitiesCollected}(entity)");
		}), 1200 * 5);
		*/
	}

	/** 월드의 해당 위치에 청크가 없는 경우 청크 생성 요청 후 임시 청크를 할당 */
	public static function prepareChunk(World $world, Vector3 $vec) : void{
		/** @var Chunk $chunk */
		static $chunk;

		if(!isset($chunk)){
			// 투명 블럭으로 채워진 서브 청크 생성
			$subChunks = [];
			for($y = 0; $y < Chunk::MAX_SUBCHUNKS; ++$y){
				$subChunks[$y] = new SubChunk(BlockLegacyIds::INVISIBLE_BEDROCK << 4, []);
			}

			// 투명 블럭으로 채워진 청크 생성
			$chunk = new Chunk($subChunks, BiomeArray::fill(BiomeIds::PLAINS), false);

			/** @noinspection PhpInternalEntityUsedInspection */
			$chunk->setFullBlock(0, 0, 0, VanillaBlocks::INVISIBLE_BEDROCK()->getFullId());
			$chunk->setPopulated();
		}

		$chunkX = $vec->getFloorX() >> 4;
		$chunkZ = $vec->getFloorZ() >> 4;
		if($world->loadChunk($chunkX, $chunkZ) !== null)
			return;

		$world->orderChunkPopulation($chunkX, $chunkZ, null);
		$world->setChunk($chunkX, $chunkZ, clone $chunk);
	}

	public static function getFlyItem(int $duration) : Item{
		$item = VanillaItems::BOOK()
			->setCustomName("§d{$duration}§f초 플라이권")
			->setLore(["이 책을 사용시 플라이권이 사용됩니다."]);
		$item->getNamedTag()->setInt("flyDuration", $duration);
		return $item;
	}
}