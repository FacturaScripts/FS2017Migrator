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

/**
 * Description of EpigrafeMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EpigrafesMigrator extends MigratorBase
{

    /**
     * 
     * @param int $offset
     *
     * @return int
     */
    public function migrate($offset = 0)
    {
        $newOffset = 0;
        $sql = "SELECT * FROM co_epigrafes ORDER BY idepigrafe ASC";
        foreach ($this->dataBase->selectLimit($sql, 100, $offset) as $row) {
            $this->newCuenta($row['codejercicio'], $this->getCodepigrafe($row), $row['codepigrafe'], $row['descripcion']);
            $newOffset += empty($newOffset) ? 1 + $offset : 1;
        }

        return $newOffset;
    }

    /**
     * 
     * @param array $data
     *
     * @return string
     */
    private function getCodepigrafe($data)
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
}
