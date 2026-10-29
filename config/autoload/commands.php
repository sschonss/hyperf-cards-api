<?php

declare(strict_types=1);

use Hyperf\Database\Commands\CommandCollector;

/**
 * No Hyperf 3.2 os comandos de migration e seed não entram sozinhos
 * pelo ConfigProvider. O coletor do pacote database é a lista oficial.
 */
return CommandCollector::getAllCommands();
