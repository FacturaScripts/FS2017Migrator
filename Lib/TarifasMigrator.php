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

use FacturaScripts\Core\Model\Tarifa;

/**
 * Description of TarifasMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class TarifasMigrator extends InicioMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        if (!$this->dataBase->tableExists('tarifas')) {
            return true;
        }

        $tarifaModel = new Tarifa();
        foreach ($tarifaModel->all([], [], 0, 0) as $tarifa) {
            if (!isset($tarifa->aplicar_a)) {
                continue;
            }

            switch ($tarifa->aplicar_a) {
                case 'coste':
                    $tarifa->aplicar = 'coste';
                    $tarifa->valorx = (float) $tarifa->incporcentual;
                    $tarifa->valory = (float) $tarifa->inclineal;
                    break;

                case 'pvp':
                    $tarifa->aplicar = 'pvp';
                    $tarifa->valorx = 0 - (float) $tarifa->incporcentual;
                    $tarifa->valory = 0 - (float) $tarifa->inclineal;
                    break;
            }

            $tarifa->save();
        }

        return true;
    }
}
