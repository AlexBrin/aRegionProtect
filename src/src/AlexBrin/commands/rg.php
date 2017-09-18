<?php

namespace AlexBrin\commands;

use AlexBrin\RegionProtect;
use AlexBrin\utils\Region;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\Config;

class rg extends Command implements PluginIdentifiableCommand {
    /* @var RegionProtect $plugin */
    private $plugin;

    public static $prefix;
    public static $help;

    public $select = [];

    public function __construct($plugin, $prefix, $name, $description = "") {
        $this->plugin = $plugin;
        self::$prefix = $prefix;
        parent::__construct($name, $description);

        $this->getPlugin()->saveResource('help.yml');
        self::$help = (new Config($this->getPlugin()->getDataFolder() . 'help.yml', Config::YAML))
            ->getAll()['help'];

        $this->setPermission('arp.command.use');
    }

    public function execute(CommandSender $sender, $label, array $args) {
        if(!$sender instanceof Player) {
            $sender->sendMessage('§cOnly for players');
            return;
        }

        $nickname = mb_strtolower($sender->getName());

        $action = array_shift($args);

        switch($action) {
            case 'list':
                    $regions = [];
                    foreach(RegionProtect::getInstance()->getRegions() as $_world => $world)
                        foreach($world as $region)
                            if($nickname == $region['owner'])
                                $regions[] = $this->getMessage('list.line', [$region['name'], $_world], false);
                    $sender->sendMessage(
                        $this->getMessage('list.title') . "\n" .
                        implode(
                            str_replace('\n', "\n", $this->getMessage('list.glue', [], false)),
                            $regions
                        )
                    );
                break;

            case 'pos1':
                    $pos = $sender->getPosition();
                    $pos->x = round($pos->x);
                    $pos->y = round($pos->y);
                    $pos->z = round($pos->z);
                    $this->selectPosition($nickname, $pos, 1);
                    $sender->sendMessage($this->getMessage('position.select', [
                        1, $pos->x, $pos->y, $pos->z
                    ]));
                break;

            case 'pos2':
                    $pos = $sender->getPosition();
                    $pos->x = round($pos->x);
                    $pos->y = round($pos->y);
                    $pos->z = round($pos->z);
                    $this->selectPosition($nickname, $pos, 2);
                    $sender->sendMessage($this->getMessage('position.select', [
                        2, $pos->x, $pos->y, $pos->z
                    ]));
                break;

            case 'create':
                    if(!isset($this->select[$nickname]) || !isset($this->select[$nickname])) {
                        $sender->sendMessage($this->getMessage('create.pos'));
                        return;
                    }

                    $size = RegionProtect::calculateSize($this->select[$nickname]);
                    $perms = array_reverse($this->getParam('size', []));
                    foreach($perms as $perm) {
                        if($sender->hasPermission($perm)) {
                            $maxSize = array_pop(explode('.', $perm));
                            if($maxSize == 'unlim')
                                break;
                            $maxSize = (int) $maxSize;
                            if($size > $maxSize) {
                                $sender->sendMessage($this->getMessage('create.size', [
                                    $maxSize, $size, $size - $maxSize
                                ]));
                                return;
                            }
                            break;
                        }
                    }

                    $count = count(Region::findByOwner($nickname));
                    $perms = array_reverse($this->getParam('count', []));
                    foreach($perms as $perm) {
                        if($sender->hasPermission($perm)) {
                            $maxCount = array_pop(explode('.', $perm));
                            if($maxCount == 'unlim')
                                break;
                            $maxCount = (int) $maxCount;
                            if($count > $maxCount) {
                                    $sender->sendMessage($this->getMessage('create.count', [$maxCount]));
                                    return;
                                }
                            break;
                        }
                    }

                    $_region = array_shift($args);

                    if(!$_region) {
                        $sender->sendMessage($this->getMessage('use.create'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if($region) {
                        $sender->sendMessage($this->getMessage('rgEx', [$_region]));
                        return;
                    }

                    Region::create($_region, $sender, $this->select[$nickname][1], $this->select[$nickname][2]);
                    $sender->sendMessage($this->getMessage('create.success', [$_region]));
                    unset($this->select[$nickname]);
                break;

            case 'delete':
            case 'remove':
                    $_region = array_shift($args);
                    if(!$_region) {
                        $sender->sendMessage($this->getMessage('use.delete'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                        $sender->sendMessage($this->getMessage('rgEx', [$_region]));
                        return;
                    }
                    if(!$region->isOwner($nickname, true)) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $region->delete($sender);
                    $sender->sendMessage($this->getMessage('delete.success', [$region->getName()]));
                    unset($region);
                break;

            case 'flag':
                    $_region = array_shift($args);
                    $flag = array_shift($args);
                    $value = array_shift($args);
                    if(!$flag || !$value) {
                        $sender->sendMessage($this->getMessage('use.flag'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                        $sender->sendMessage($this->getMessage('rgNotEx', [$_region]));
                        return;
                    }
                    if(!$region->isOwner($nickname)) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $value = $value == 'allow' ? 'allow' : 'deny';

                    $region->updateFlag($flag, $value == 'allow' ? true : false);
                    $region->save();
                    unset($region);
                    $sender->sendMessage($this->getMessage('flag.update', [
                        $flag, $_region, $value
                    ]));
                break;

            case 'flags':
                    $_region = array_shift($args);
                    if(!$_region) {
                        $sender->sendMessage($this->getMessage('use.flags'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                        $sender->sendMessage($this->getMessage('rgNotEx', [$_region]));
                        return;
                    }
                    if(!$region->isOwner($nickname)) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $message = [];
                    $message[] = $this->getMessage('flag.title', [$_region]);
                    foreach($region->getFlags() as $flag => $value)
                        $message[] = $this->getMessage('flag.info', [$flag, $value], false);

                    $sender->sendMessage(implode("\n",$message));
                break;

            case 'addmember':
                    $_region = array_shift($args);
                    $member = array_shift($args);
                    if(!$_region || !$member) {
                        $sender->sendMessage($this->getMessage('use.addMember'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                            $sender->sendMessage($this->getMessage('rgNotEx', [$_region]));
                            return;
                        }
                    if(!$region->isOwner($nickname)) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $region->addMember($member);
                    $region->save();
                    unset($region);
                    $sender->sendMessage($this->getMessage('member.add', [$member, $_region]));
                break;

            case 'deletemember':
            case 'delmember':
            case 'removemember':
                    $_region = array_shift($args);
                    $member = array_shift($args);
                    if(!$_region || !$member) {
                        $sender->sendMessage($this->getMessage('use.removeMember'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                        $sender->sendMessage($this->getMessage('rgNotEx', [$_region]));
                        return;
                    }
                    if(!$region->isOwner($nickname)) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $region->removeMember($member);
                    $region->save();
                    unset($region);
                    $sender->sendMessage($this->getMessage('member.remove', [$member, $_region]));
                break;

            case 'addowner';
                    $_region = array_shift($args);
                    $owner = array_shift($args);
                    if(!$_region || !$owner) {
                        $sender->sendMessage($this->getMessage('use.addOwner'));
                        return;
                    }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                        $sender->sendMessage($this->getMessage('rgNotEx', [$_region]));
                        return;
                    }
                    if($region->getOwner() !== $nickname) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $region->addOwner($owner);
                    $region->save();
                    unset($region);
                    $sender->sendMessage('owner.add', [$owner, $_region]);
                break;

            case 'deleteowner':
            case 'delowner':
            case 'removeowner':
                    $_region = array_shift($args);
                $owner = array_shift($args);
                if(!$_region || !$owner) {
                    $sender->sendMessage($this->getMessage('use.removeOwner'));
                    return;
                }

                    $region = Region::findRegionByRgName($_region);
                    if(!$region) {
                        $sender->sendMessage($this->getMessage('rgNotEx', [$_region]));
                        return;
                    }
                    if($region->getOwner() !== $nickname) {
                        $sender->sendMessage($this->getMessage('notOwner'));
                        return;
                    }

                    $region->removeMember($owner);
                    $region->save();
                    unset($region);
                    $sender->sendMessage($this->getMessage('owner.remove', [$owner, $_region]));
                break;

            case 'up':
                    if(!isset($this->select[$nickname][1]) || !isset($this->select[$nickname][2])) {
                        $sender->sendMessage($this->getMessage('up.pos'));
                        return;
                    }

                    $blocks = (int) array_shift($args);
                    if(!$blocks) {
                        $sender->sendMessage($this->getMessage('use.up'));
                        return;
                    }

                    $this->select[$nickname][1]->y += $blocks;

                    $sender->sendMessage($this->getMessage('up.success', [
                        $blocks, RegionProtect::calculateSize($this->select[$nickname])
                    ]));
                break;

            case 'down':
                    if(!isset($this->select[$nickname][1]) || !isset($this->select[$nickname][2])) {
                        $sender->sendMessage($this->getMessage('up.pos'));
                        return;
                    }

                    $blocks = (int) array_shift($args);
                    if(!$blocks) {
                        $sender->sendMessage($this->getMessage('use.down'));
                        return;
                    }

                    if($blocks > $this->select[$nickname][2]->y) {
                        $sender->sendMessage($this->getMessage('down.minimum'));
                        return;
                    }

                    $this->select[$nickname][2]->y -= $blocks;

                    $sender->sendMessage($this->getMessage('up.success', [
                        $blocks, RegionProtect::calculateSize($this->select[$nickname])
                    ]));
                break;

            case 'help':
            default:
                    $page = array_shift($args);
                    if(!$page)
                        $page = 1;
                    $sender->sendMessage(self::help($page));
                break;
        }

    }

    /**
     * @param string|Player $nickname
     * @param Position      $pos
     * @param int           $num
     */
    private function selectPosition($nickname, Position $pos, int $num) {
        if($nickname instanceof Player)
            $nickname = mb_strtolower($nickname->getName());

        if(!isset($this->select[$nickname]))
            $this->select[$nickname] = [];

        $this->select[$nickname][$num] = $pos;
        $this->sortPosition($nickname);
    }

    /**
     * @param string $nickname
     */
    private function sortPosition($nickname) {
        if(!isset($this->select[$nickname][1]) || !isset($this->select[$nickname][2]))
            return;

        $pos1 = $this->select[$nickname][1];
        $pos2 = $this->select[$nickname][2];

        if($pos1->x > $pos2->x) {
            $x = $pos1->x;
            $pos1->x = $pos2->x;
            $pos2->x = $x;
        }

        if($pos1->y > $pos2->y) {
            $y = $pos1->y;
            $pos1->y = $pos2->y;
            $pos2->y = $y;
        }

        if($pos1->z > $pos2->z) {
            $z = $pos1->z;
            $pos1->z = $pos2->z;
            $pos2->z = $z;
        }

        $this->select[$nickname][1] = $pos1;
        $this->select[$nickname][2] = $pos2;
    }

    public static function help($page = 1, $perPage = 5) {
        if($page < 1)
            $page = 1;

        $_help = count(self::$help);
        $pages = round($_help / $perPage);
        if($page > $pages)
            $page = $pages;

        $start = $page * $perPage - $perPage;
        $end = $start + $perPage;
        $lines = [
            self::$prefix . 'Помощь (' . $page . ' из ' . $pages . '):',
        ];
        for($i = $start; $i < $end; $i++)
            if(isset(self::$help[$i]))
                $lines[] = self::$help[$i];

        return implode("\n", $lines);
    }

    public function getMessage($node, $params = [], $prefix = true) {
        return $this->getPlugin()->getMessage($node, $params, $prefix);
    }

    public function getParam($node, $default) {
        return $this->getPlugin()->getParam($node, $default);
    }

    public function getPlugin() {
        return $this->plugin;
    }

}