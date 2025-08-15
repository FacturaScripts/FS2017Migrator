<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2020-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Description of RegImpuestosMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class RegImpuestosMigrator extends MigratorBase
{
    protected function migrationProcess(int &$offset = 0): bool
    {
        if (false === $this->dataBase->tableExists('co_regiva')) {
            return true;
        }

        $sql = 'SELECT * FROM co_regiva ORDER BY idregiva ASC';
        $rows = $this->dataBase->select($sql);
        foreach ($rows as $row) {
            $newRegImp = new RegularizacionImpuesto();
            if ($newRegImp->load($row['idregiva'])) {
                continue;
            }

            $newRegImp->loadFromData($row);
            $newRegImp->bloquear = true;

            if (false === $newRegImp->getAccountingEntry()->exists()) {
                $newRegImp->idasiento = null;
            }

            $newRegImp->disableAdditionalTest(true);
            if (false === $newRegImp->save()) {
                return false;
            }

            $offset++;
        }

        return true;
    }
}
