<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

/**
 * Description of FacturasClienteMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class FacturasClienteMigrator extends FacturasProveedorMigrator
{

    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset && false === $this->fixLinesTable('lineasfacturascli')) {
            return false;
        }

        if (0 === $offset && false === $this->fixCustomers('facturascli')) {
            return false;
        }

        if (0 === $offset && false === $this->fixVencimiento()) {
            return false;
        }

        if (0 === $offset && false === $this->fixAccounting('facturascli')) {
            return false;
        }

        if (0 === $offset && false === $this->setModelCompany('FacturaCliente')) {
            return false;
        }

        if (0 === $offset && false === $this->setModelStatusAll('FacturaCliente')) {
            return false;
        }

        $sql = "SELECT * FROM lineasfacturascli"
            . " WHERE idalbaran IS NOT null"
            . " AND idalbaran != '0'"
            . " ORDER BY idlinea ASC";

        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            if (false === $this->newDocTransformation('AlbaranCliente', $row['idalbaran'], $row['idlineaalbaran'], 'FacturaCliente', $row['idfactura'], $row['idlinea'])) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    private function fixVencimiento(): bool
    {
        $sql = "update facturascli set vencimiento = '1999-12-31' where vencimiento < '1999-01-01';";
        return $this->dataBase->exec($sql);
    }
}
