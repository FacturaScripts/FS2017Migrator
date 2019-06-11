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

use FacturaScripts\Dinamic\Model\LiquidacionComision;

/**
 * Description of AlbaranesClienteMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AlbaranesClienteMigrator extends AlbaranesProveedorMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    public function migrate(&$offset = 0)
    {
        return $this->migrateInTransaction($offset);
    }

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function transactionProcess(&$offset = 0)
    {
        if (0 === $offset && !$this->fixLinesTable('lineasalbaranescli')) {
            return false;
        }

        if (0 === $offset && !$this->setModelCompany('AlbaranCliente')) {
            return false;
        }

        if (0 === $offset && !$this->setModelStatusAll('AlbaranCliente', 'idfactura', true)) {
            return false;
        }

        if (0 === $offset) {
            /// needed dependency
            new LiquidacionComision();
        }

        $sql = "SELECT * FROM lineasalbaranescli"
            . " WHERE idpedido IS NOT null"
            . " AND idpedido != '0'"
            . " ORDER BY idlinea ASC";

        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            $done = $this->newDocTransformation(
                'PedidoCliente', $row['idpedido'], $row['idlineapedido'], 'AlbaranCliente', $row['idalbaran'], $row['idlinea']
            );
            if (!$done) {
                return false;
            }

            $offset++;
        }

        return true;
    }
}
