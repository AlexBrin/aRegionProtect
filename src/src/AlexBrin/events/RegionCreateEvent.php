<?php

namespace AlexBrin\events;

use AlexBrin\utils\Region;
use pocketmine\event\Cancellable;
use pocketmine\event\player\PlayerEvent;
use pocketmine\Player;

class RegionCreateEvent extends PlayerEvent implements Cancellable {
    /**
     * @var Region $region
     */
    private $region;

    public static $handlerList = null;

    public function __construct(Player $player, Region $region) {
        $this->player = $player;
        $this->region = $region;
    }

    public function getRegion(): Region {
        return $this->region;
    }

}