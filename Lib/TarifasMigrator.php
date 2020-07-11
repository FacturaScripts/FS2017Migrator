<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2020 Carlos Garcia Gomez <carlos@facturascripts.com>
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
class TarifasMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if ($this->dataBase->tableExists('tarifasav')) {
            $this->migrateTarifasAv();
        }

        if (!$this->dataBase->tableExists('tarifas')) {
            return true;
        }

        $tarifaModel = new Tarifa();
        foreach ($tarifaModel->all([], [], 0, 0) as $tarifa) {
            if (!empty($tarifa->aplicar) || !isset($tarifa->aplicar_a)) {
                continue;
            }

            switch ($tarifa->aplicar_a) {
                case Tarifa::APPLY_COST:
                    $tarifa->aplicar = Tarifa::APPLY_COST;
                    $tarifa->valorx = (float) $tarifa->incporcentual;
                    $tarifa->valory = (float) $tarifa->inclineal;
                    break;

                case Tarifa::APPLY_PRICE:
                    $tarifa->aplicar = Tarifa::APPLY_PRICE;
                    $tarifa->valorx = 0 - (float) $tarifa->incporcentual;
                    $tarifa->valory = 0 - (float) $tarifa->inclineal;
                    break;
            }

            $tarifa->save();
        }

        return true;
    }

    protected function migrateTarifasAv()
    {
        $sql = "SELECT * FROM tarifasav WHERE madre IS NULL;";
        foreach ($this->dataBase->select($sql) as $row) {
            $tarifa = new Tarifa();
            if ($tarifa->loadFromCode($row['codtarifa'])) {
                continue;
            }

            $tarifa->codtarifa = $row['codtarifa'];
            $tarifa->nombre = $row['nombre'];

            if ($this->toolBox()->utils()->str2bool($row['margen'])) {
                $tarifa->aplicar = Tarifa::APPLY_COST;
                $tarifa->valorx = (float) $row['incporcentual'];
                $tarifa->valory = (float) $row['inclineal'];
                $tarifa->save();
                continue;
            }

            $tarifa->aplicar = Tarifa::APPLY_PRICE;
            $tarifa->valorx = 0 - (float) $row['incporcentual'];
            $tarifa->valory = 0 - (float) $row['inclineal'];
            $tarifa->save();
        }
    }
}
