<?php
/**
 * This file is part of FS2017Migrator plugin for FacturaScripts
 * Copyright (C) 2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\Balance;
use FacturaScripts\Dinamic\Model\BalanceCuenta;
use FacturaScripts\Dinamic\Model\BalanceCuentaA;

/**
 * Description of BalancesMigrator
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class BalancesMigrator extends MigratorBase
{

    /**
     * 
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

            case 3:
                $offset++;
                new Balance();
                return $this->dataBase->tableExists('balances');

            case 4:
                $offset++;
                return $this->checkBalanceCuentas();

            case 5:
                $offset++;
                return $this->checkBalanceCuentasA();
        }

        return true;
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function checkBalanceCuentas(string $tableName = 'balancescuentas')
    {
        if (false === $this->dataBase->tableExists($tableName)) {
            new BalanceCuenta();
            return $this->dataBase->tableExists($tableName);
        }

        $sql = 'DELETE FROM ' . $tableName . ' WHERE codbalance NOT IN (SELECT codbalance FROM balances)';
        if (false === $this->dataBase->exec($sql)) {
            return false;
        }

        new BalanceCuenta();
        return $this->dataBase->tableExists($tableName);
    }

    /**
     * 
     * @param string $tableName
     *
     * @return bool
     */
    private function checkBalanceCuentasA(string $tableName = 'balancescuentasabreviadas')
    {
        if (false === $this->dataBase->tableExists($tableName)) {
            new BalanceCuentaA();
            return $this->dataBase->tableExists($tableName);
        }

        $sql = 'DELETE FROM ' . $tableName . ' WHERE codbalance NOT IN (SELECT codbalance FROM balances)';
        if (false === $this->dataBase->exec($sql)) {
            return false;
        }

        new BalanceCuentaA();
        return $this->dataBase->tableExists($tableName);
    }
}
