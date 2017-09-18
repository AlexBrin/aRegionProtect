aRegionProtect
==============

Приват регионов
Присутствует API и продажа регионов

Пример:
```
use AlexBrin\RegionProtect;

$regionProtect = RegionProtect::getInstance();

/** $var AlexBrin\utils\Region $region */
$region = $regionProtect->findRegion($player->getPosition());

// P.S. Проще посмотреть самому :)
```

События:
```
RegionCreateEvent
RegionDeleteEvent
RegionEntryEvent
RegionEscapeEvent
RegionSellEvent
```