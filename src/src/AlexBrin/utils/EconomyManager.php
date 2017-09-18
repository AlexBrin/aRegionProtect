<?php

namespace AlexBrin\utils;

use pocketmine\Player;
use pocketmine\plugin\Plugin;

class EconomyManager {
    private $eco = null;

    public function __construct(Plugin &$plugin) {
        $pManager  = $plugin->getServer()->getPluginManager();
        $this->eco = $pManager->getPlugin("EconomyAPI") ?? $pManager->getPlugin("PocketMoney") ?? $pManager->getPlugin("MassiveEconomy") ?? null;
        if($this->eco === null)
            $plugin->getLogger()->warning('§eПлагин на экономику отсутствует');
        else
            $plugin->getLogger()->info('§aНайден плагин на экономику: §d'.$this->eco->getName());
    }

    public function reduceMoney($player, $amount) {
        if($player instanceof Player)
            $player = $player->getName();
        return $this->setMoney($player, $this->getMoney($player) - $amount);
    }

    public function addMoney($player, $amount) {
        return $this->eco->addMoney($player, $amount);
    }

    public function getMoney($player) {
        switch(mb_strtolower($this->eco->getName())) {
            case 'economyapi':
                $balance = $this->eco->myMoney($player);
                break;
            default:
                $balance = $this->eco->getMoney($player);
        }

        return $balance;
    }

    public function setMoney(string $player, int $amount) {
        return $this->eco->setMoney($player, $amount);
    }

    public function getPlugin() {
        return $this->eco;
    }

}

?>