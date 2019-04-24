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

use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\PedidoProveedor;

/**
 * Description of ComprasMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ComprasMigrator extends MigratorBase
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
                return $this->fixLinesTable('lineaspedidosprov');

            case 1:
                $offset++;
                return $this->fixLinesTable('lineasalbaranesprov');

            case 2:
                $offset++;
                return $this->fixLinesTable('lineasfacturasprov');

            case 3:
                $offset++;
                new PedidoProveedor();
                break;

            case 4:
                $offset++;
                new AlbaranProveedor();
                break;

            case 5:
                $offset++;
                new FacturaProveedor();
                break;
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixLinesTable($tableName)
    {
        if (!$this->dataBase->tableExists($tableName)) {
            return true;
        }

        $values = [];
        foreach (array_keys($this->impuestos) as $value) {
            $values[] = "'" . $value . "'";
        }

        $sql = "UPDATE " . $tableName . " SET recargo = 0 WHERE recargo IS null;"
            . " UPDATE " . $tableName . " SET codimpuesto = null WHERE codimpuesto IS NOT null"
            . " AND codimpuesto NOT IN (" . implode(',', $values) . ");";
        return $this->dataBase->exec($sql);
    }
}
