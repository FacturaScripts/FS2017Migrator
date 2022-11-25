<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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
 * Description of BalancesMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class BalancesMigrator extends MigratorBase
{
    /**
     * @param int $offset
     *
     * @return bool
     */
    protected function migrationProcess(&$offset = 0): bool
    {
        switch ($offset) {
            case 0:
                $offset++;
                return $this->dataBase->tableExists('balances') ||
                    $this->renameTable('co_codbalances08', 'balances');

            case 1:
                $offset++;
                return $this->dataBase->tableExists('balancescuentas') ||
                    $this->renameTable('co_cuentascb', 'balancescuentas');

            case 2:
                $offset++;
                return $this->dataBase->tableExists('balancescuentasabreviadas') ||
                    $this->renameTable('co_cuentascbba', 'balancescuentasabreviadas');
        }

        return true;
    }
}
