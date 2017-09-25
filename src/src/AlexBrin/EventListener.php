<?php

namespace AlexBrin;

use AlexBrin\events\RegionEntryEvent;
use AlexBrin\events\RegionEscapeEvent;
use AlexBrin\events\RegionSellEvent;
use AlexBrin\utils\Region;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\Event;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Sign;

class EventListener implements Listener {
    private $plugin;

    private $inRegion = [];

//    public static $blocks = [
//        BlockIds::DOOR_BLOCK,
//        BlockIds::CHEST,
//    ];

    public function __construct(RegionProtect &$plugin) {
        $this->plugin = $plugin;
    }

    public function onBlockPlace(BlockPlaceEvent $event) {
        if($this->buildInRegion($event->getPlayer(), $event->getBlock())) {
            $event->setCancelled(true);
            $event->getPlayer()->sendMessage($this->getMessage('event.build.deny'));
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        if($this->buildInRegion($event->getPlayer(), $event->getBlock())) {
            $event->setCancelled(true);
            $event->getPlayer()->sendMessage($this->getMessage('event.build.deny'));
        }
    }

    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $nickname = mb_strtolower($player->getName());
        $region = static::findRegion($player->getPosition());
        if(!$region) {
            if(isset($this->inRegion[$nickname])) {
                $ev = new RegionEscapeEvent($player, new Region($this->inRegion[$nickname]));
                $this->callEvent($ev);
                if($ev->isCancelled())
                    return;
                if($this->getParam('move.out')) {
                    if($this->getParam('move.title'))
                        RegionProtect::sendTitle(
                            $player,
                            $this->getMessage('event.move.outRegion.title', [], false),
                            $this->getMessage('event.move.outRegion.success', [$this->inRegion[$nickname]], false)
                        );
                    else
                        $player->sendMessage(
                            $this->getMessage('event.move.outRegion.success', [$this->inRegion[$nickname]], false)
                        );
                }
                unset($this->inRegion[$nickname]);
            }
            return;
        }

        if(!$region->getFlag('entry')) {
            if(!$region->isOwner($player) || !$region->isMember($player)) {
                $event->setCancelled(true);
                $player->sendMessage($this->getMessage('event.move.inRegion.fail'));
            }
        }

        if($this->getParam('move.in')) {
            if(!isset($this->inRegion[$nickname])) {
                $ev = new RegionEntryEvent($player, $region);
                $this->callEvent($ev);
                if($ev->isCancelled())
                    return;
                if($this->getParam('move.title'))
                    RegionProtect::sendTitle(
                        $player,
                        $this->getMessage('event.move.inRegion.title', [], false),
                        $this->getMessage('event.move.inRegion.success', [$region->getName()], false)
                    );
                else
                    $player->sendMessage($this->getMessage('event.move.inRegion.success', [$region->getName()], false));
                $this->inRegion[$nickname] = $region->getName();
            }
        }
    }

    /**
     * @param EntityDamageEvent|EntityDamageByEntityEvent $event
     */
    public function onEntityDamage(EntityDamageEvent $event) {
        if(!$event instanceof EntityDamageByEntityEvent)
            return;
        if(!($event->getDamager() instanceof Player) || !($event->getEntity() instanceof Player))
            return;

        $region = Region::findRegionByPos($event->getEntity()->getPosition());
        if(!$region)
            return;

        if(!$region->getFlag('pvp')) {
            $event->setCancelled(true);
            $event->getDamager()->sendMessage($this->getMessage('event.pvp.deny'));
        }
    }

    public function onUse(PlayerInteractEvent $event) {
        if($event->getAction() != PlayerInteractEvent::RIGHT_CLICK_BLOCK)
            return;

        $block = $event->getBlock();
        $pos = new Position(
            $block->x,
            $block->y,
            $block->z,
            $block->level
        );
        $region = Region::findRegionByPos($pos);
        if(!$region)
            return;

        $player = $event->getPlayer();

        if($event->getItem()->getId() == $this->getParam('info.id')) {
            $message = [];
            foreach($region->getFlags() as $flag => $value)
                $message[] = $this->getMessage('flag.info', [$flag, $value], false);

            $player->sendMessage(
                $this->getMessage('info.title', [$region->getName()]) . "\n" .
                $this->getMessage('info.info', ['Владелец', $region->getOwner()], false) . "\n" .
                $this->getMessage('info.info', ['Флаги', ''], false) . "\n" .
                str_replace(
                    "\\n", "\n",
                    implode(
                        $this->getMessage('info.glue', [], false),
                        $message
                    )
                )
            );
            return;
        }

        if($region->isOwner($player) || $region->isMember($player))
            return;

        switch($block->getId()) {

            case BlockIds::SIGN_POST:
            case BlockIds::WALL_SIGN:
                    /* @var Sign $tile */
                    $tile = $block->level->getTile($pos);
                    if(!$tile instanceof Sign)
                        return;

                    $text = $tile->getText();
                    if($text[0] != $this->getMessage('sign.line1', [], false))
                        return;

                    $price = $text[3];

                    $money = $this->getEco()->getMoney($player);
                    if($money < $price) {
                        $player->sendMessage($this->getMessage('sign.sell.money'));
                        return;
                    }

                    $ev = new RegionSellEvent($player, $region);
                    $this->callEvent($ev);
                    if($ev->isCancelled())
                        return;

                    $this->getEco()->addMoney($region->getOwner(), $price);
                    $this->getEco()->reduceMoney($player, $price);
                    $region->setOwner($player);
                    $region->save();
                    $block->level->setBlock($pos, Block::get(0));
                    $player->sendMessage($this->getMessage('sign.sell.selled', [$region->getName(), $price]));
                break;

            case BlockIds::ITEM_FRAME_BLOCK:
                    if(!$this->hasPermission($region, $player, 'frame')) {
                        $event->setCancelled(true);
                        $player->sendMessage($this->getMessage('event.interact.deny'));
                    }
                break;

            case BlockIds::CHEST:
            case BlockIds::ENDER_CHEST:
            case BlockIds::TRAPPED_CHEST:
                    if(!$this->hasPermission($region, $player, 'chest')) {
                        $event->setCancelled(true);
                        $player->sendMessage($this->getMessage('event.interact.deny'));
                    }
                break;

            case 64:
                    if(!$this->hasPermission($region, $player, 'door')) {
                        $event->setCancelled(true);
                        $player->sendMessage($this->getMessage('event.interact.deny'));
                    }
                break;

            case BlockIds::ANVIL:
            case BlockIds::CRAFTING_TABLE:
            case BlockIds::BED_BLOCK:
            case BlockIds::COMMAND_BLOCK:
            case BlockIds::FURNACE:
            case BlockIds::FLOWER_POT_BLOCK:
            case BlockIds::STONE_BUTTON:
            case BlockIds::WOODEN_BUTTON:
            case BlockIds::TNT:
                    if(!$this->hasPermission($region, $player, 'use')) {
                        $event->setCancelled(true);
                        $player->sendMessage($this->getMessage('event.interact.deny'));
                    }
                break;

            default:
                    return;
                break;
        }
    }

    public function onBucketEmpty(PlayerBucketEmptyEvent $event) {
        $this->bucketEvent($event);
    }

    public function onBucketFill(PlayerBucketFillEvent $event) {
        $this->bucketEvent($event);
    }

    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event) {
        $player = $event->getPlayer();
        $region = Region::findRegionByPos(new Position(
            $player->x, $player->y, $player->z, $player->level
        ));
        if(!$region)
            return;

        if(!$this->hasPermission($region, $player, 'cmd')) {
            $event->setCancelled(true);
            $player->sendMessage($this->getMessage('event.cmd.deny'));
        }
    }

    public function onEntityBurn(EntityCombustEvent $event) {
        $entity = $event->getEntity();
        $region = Region::findRegionByPos(new Position(
            $entity->x, $entity->y, $entity->z, $entity->level
        ));
        if(!$region)
            return;

        if(!$region->getFlag('burn'))
            $event->setCancelled(true);
    }

    public function onExplode(EntityExplodeEvent $event) {
        $region = Region::findRegionByPos($event->getPosition());
        if(!$region)
            return;

        if(!$region->getFlag('explode'))
            $event->setCancelled(true);
    }

    /**
     * @param Region $region
     * @param Player $player
     * @param string $flag
     * @return bool
     */
    private function hasPermission(Region &$region, Player &$player, string $flag): bool {
        if(!$region->getFlag($flag))
            if(!$region->isOwner($player) || !$region->isMember($player))
                return false;
        return true;
    }

    private function buildInRegion(Player $player, Block $block) {
        $region = Region::findRegionByPos(new Position(
            $block->x,
            $block->y,
            $block->z,
            $block->level
        ));
        if(!$region)
            return false;

        if(!$region->getFlag('build')) {
            if($region->isOwner($player) || $region->isMember($player))
                return false;

            return true;
        }

        return false;
    }

    /**
     * @param PlayerBucketEmptyEvent|PlayerBucketFillEvent $event
     */
    public function bucketEvent(&$event) {
        $player = $event->getPlayer(); $block = $event->getBlockClicked()->getSide($event->getBlockFace());
        $region = Region::findRegionByPos(new Position(
            $block->x, $block->y, $block->z, $block->level
        ));
        if(!$region)
            return;

        if(!$this->hasPermission($region, $player, 'bucket')) {
            $event->setCancelled(true);
            $player->sendMessage($this->getMessage('event.interact.deny'));
        }
    }

    public function onSignChange(SignChangeEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        $region = Region::findRegionByPos(new Position(
            $block->x, $block->y, $block->z, $block->level
        ));
        if(!$region)
            return;

        if($region->getOwner() != mb_strtolower($player->getName()))
            return;

        $lines = $event->getLines();
        if($lines[0] != $this->getParam('sell.line1'))
            return;

        for($i = 0; $i < 4; $i++)
            $event->setLine($i, str_replace([
                '{price}', '{rgname}'
            ], [
                $lines[1], $region->getName()
            ], $this->getMessage('sign.line' . ($i+1), [], false)));

        $player->sendMessage($this->getMessage('sign.success', [$region->getName(), $lines[1]]));
    }

    public function getPlugin(): RegionProtect {
        return $this->plugin;
    }

    public static function findRegion(Position $pos) {
        return Region::findRegionByPos($pos);
    }

    /**
     * @return \AlexBrin\utils\EconomyManager
     */
    public function getEco() {
        return RegionProtect::getInstance()->getEco();
    }

    public function callEvent(Event $event) {
        RegionProtect::getInstance()->getServer()->getPluginManager()->callEvent($event);
    }

    /**
     * @param  string $node
     * @param  array  $params
     * @param  bool   $prefix
     * @return string
     */
    private function getMessage($node, $params = [], $prefix = true): string {
        return $this->getPlugin()->getMessage($node, $params, $prefix);
    }

    /**
     * @param $node
     * @param array $default
     * @return mixed
     */
    private function getParam($node, $default = []) {
        return $this->getPlugin()->getParam($node, $default);
    }

}