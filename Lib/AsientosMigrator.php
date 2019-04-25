<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\FS2017Migrator\Lib;

use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Diario;
use FacturaScripts\Dinamic\Model\Partida;

/**
 * Description of AsientosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AsientosMigrator extends InicioMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        switch ($offset) {
            case 0:
                $offset++;
                return $this->renameTable('co_asientos', 'asientos');

            case 1:
                $offset++;
                return $this->renameTable('co_partidas', 'partidas');

            case 2:
                $offset++;
                new Diario();
                new Asiento();
                return true;

            case 3:
                new Partida();
                return true;
        }

        return true;
    }
}
