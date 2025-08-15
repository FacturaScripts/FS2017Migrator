<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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
 * Description of EpigrafeMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EpigrafesMigrator extends MigratorBase
{
    protected function getCodepigrafe(array $data): string
    {
        if (!empty($data['idpadre'])) {
            $sql = "SELECT * FROM co_epigrafes WHERE idepigrafe = '" . $data['idpadre'] . "'";
            foreach ($this->dataBase->select($sql) as $row) {
                return $row['codepigrafe'];
            }
        }

        if (!empty($data['idgrupo'])) {
            $sql = "SELECT * FROM co_gruposepigrafes WHERE idgrupo = '" . $data['idgrupo'] . "'";
            foreach ($this->dataBase->select($sql) as $row) {
                return $row['codgrupo'];
            }
        }

        return '';
    }

    protected function migrationProcess(int &$offset = 0): bool
    {
        $sql = "SELECT * FROM co_epigrafes ORDER BY idepigrafe ASC";
        $rows = $this->dataBase->selectLimit($sql, 300, $offset);
        foreach ($rows as $row) {
            if (false === $this->newCuenta($row['codejercicio'], $this->getCodepigrafe($row), $row['codepigrafe'], $row['descripcion'])) {
                return false;
            }

            $offset++;
        }

        return true;
    }
}
