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

/**
 * Description of FacturasProveedorMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class FacturasProveedorMigrator extends AlbaranesProveedorMigrator
{

    /**
     * 
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        if (0 === $offset && !$this->fixLinesTable('lineasfacturasprov')) {
            return false;
        }

        if (0 === $offset && !$this->fixSuppliers('facturasprov')) {
            return false;
        }

        if (0 === $offset && !$this->fixAccounting('facturasprov')) {
            return false;
        }

        if (0 === $offset && !$this->setModelCompany('FacturaProveedor')) {
            return false;
        }

        if (0 === $offset && !$this->setModelStatusAll('FacturaProveedor')) {
            return false;
        }

        $sql = "SELECT * FROM lineasfacturasprov"
            . " WHERE idalbaran IS NOT null"
            . " AND idalbaran != '0'"
            . " ORDER BY idlinea ASC";

        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            $done = $this->newDocTransformation(
                'AlbaranProveedor', $row['idalbaran'], $row['idlineaalbaran'], 'FacturaProveedor', $row['idfactura'], $row['idlinea']
            );
            if (!$done) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    protected function fixAccounting($tableName)
    {
        if (!$this->dataBase->tableExists($tableName)) {
            return true;
        }

        $sql = "UPDATE " . $tableName . " SET idasiento = null WHERE idasiento IS NOT null"
            . " AND idasiento NOT IN (SELECT idasiento FROM asientos);"
            . "UPDATE " . $tableName . " SET idasientop = null WHERE idasientop IS NOT null"
            . " AND idasientop NOT IN (SELECT idasiento FROM asientos);"
            . "UPDATE " . $tableName . " SET pagada = false WHERE pagada IS null;";
        return $this->dataBase->exec($sql);
    }
}
